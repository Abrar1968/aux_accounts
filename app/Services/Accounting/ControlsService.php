<?php

namespace App\Services\Accounting;

use App\Models\CustomerPayment;
use App\Models\Employee;
use App\Models\ExpensePayment;
use App\Models\FixedAsset;
use App\Models\OperatingExpense;
use App\Models\Payroll;
use App\Models\Project;
use App\Models\Referral;
use App\Models\RevenueRecognition;
use App\Models\SalaryPayment;
use App\Services\Shared\PeriodService;
use App\Services\Shared\SettingsService;
use App\Support\Money;
use App\Support\Period;

/**
 * Checks tab port: accounting controls. Each control returns a result, a status and the
 * interpretation shown to the user. Controls never "fix" data.
 */
class ControlsService
{
    public function __construct(
        private readonly FinancialStatementService $statements,
        private readonly ProjectService $projects,
        private readonly PayrollService $payroll,
    ) {}

    /** @return list<array{key:string,label:string,result:mixed,status:string,note:string}> */
    public function run(Period $through): array
    {
        $months = Period::range(app(PeriodService::class)->modelStart(), $through);
        $balanceDiff = 0.0;
        $siamEarly = 0.0;
        $siamStart = Period::of(app(SettingsService::class)->get('siam.start'));
        foreach ($months as $m) {
            $s = $this->statements->forPeriod($m);
            $balanceDiff = max($balanceDiff, abs($s['balance_check']));
            if ($m->lt($siamStart)) {
                $siamEarly += $s['siam_share'];
            }
        }

        $c = [];
        $add = function (string $key, string $label, mixed $result, bool $ok, string $note, string $bad = 'REVIEW') use (&$c) {
            $c[] = ['key' => $key, 'label' => $label, 'result' => $result, 'status' => $ok ? 'OK' : $bad, 'note' => $note];
        };

        $add('balance', 'Monthly balance-sheet differences', $balanceDiff, $balanceDiff < 0.01, 'Must be zero for every reporting month.');
        $add('siam_early', 'Siam share before contract start', $siamEarly, $siamEarly == 0.0, 'No revenue share may accrue before the configured start.');

        $expenseWarnings = OperatingExpense::query()->with('payments')->get()
            ->filter(fn ($e) => app(ExpenseService::class)->status($e)['entry_check'] !== 'OK')->count();
        $add('expense_input', 'Expense input warnings', $expenseWarnings, $expenseWarnings === 0, 'Missing dates, invalid costs, duplicates or overpayments.', 'INPUT REQUIRED');

        $salaryWarnings = Payroll::query()->with('employee')->get()
            ->filter(fn ($p) => $this->payroll->summary($p)['entry_check'] !== 'OK')->count();
        $add('salary_input', 'Salary input warnings', $salaryWarnings, $salaryWarnings === 0, 'A blank salary amount is not confirmation that salary is zero.', 'INPUT REQUIRED');

        $missing = [];
        foreach ($months as $m) {
            $paid = Payroll::query()->where('period', $m->date())->pluck('employee_id')->all();
            $prev = Payroll::query()->where('period', $m->previous()->date())->where('gross_salary', '>', 0)->pluck('employee_id')->all();
            foreach (array_diff($prev, $paid) as $id) {
                if ($m->lt(Period::current())) { // the running month is not due yet
                    $missing[] = Employee::query()->find($id)?->name.' '.$m->label();
                }
            }
        }
        $add('missing_payroll', 'Employees missing a payroll row', count($missing), $missing === [], implode(', ', $missing) ?: 'Every employee paid last month has a payroll row.', 'INPUT REQUIRED');

        $queue = RevenueRecognition::query()->where('is_historical', false)->where('entry_check', '!=', 'OK')->count();
        $add('recognition_queue', 'Revenue recognition queue warnings', $queue, $queue === 0, 'Missing progress/dates, invalid links or duplicate project/month entries.', 'INPUT REQUIRED');

        $projRevenue = Money::sum(Project::query()->get()->map(fn ($p) => $this->projects->metrics($p)['revenue_recognized']));
        $ledger = Money::round(RevenueRecognition::query()->sum('recognized_amount'));
        $add('revenue_reconciliation', 'Project revenue-to-ledger reconciliation', Money::round($projRevenue - $ledger), abs($projRevenue - $ledger) < 0.01, 'Project recognised revenue must equal the recognition ledger.');

        $falsePaid = Project::query()->get()->filter(function ($p) {
            $m = $this->projects->metrics($p);

            return $m['payment_status'] === 'Paid' && abs($m['total_value'] - $m['gross_settlement']) > 0.01;
        })->count();
        $add('false_paid', 'False Paid project statuses', $falsePaid, $falsePaid === 0, 'Paid means contract value is settled; not that work was delivered.');

        $refNoDate = Referral::query()->where('paid_amount', '>', 0)->whereNull('paid_on')->count();
        $add('referral_dates', 'Referral payments without dates', $refNoDate, $refNoDate === 0, 'A commission payment must have its actual payment date.', 'INPUT REQUIRED');

        $refSync = Project::query()->whereNotNull('referrer')->whereDoesntHave('referral')->count();
        $add('referral_sync', 'Referral tracker sync integrity', $refSync, $refSync === 0, 'Every project with a referrer has exactly one referral row.');

        $evidence = Project::query()->whereNull('actual_delivery_date')->where('revenue_method', '!=', 'Over Time')
            ->whereHas('recognitions', fn ($q) => $q->where('recognized_amount', '>', 0))->count();
        $add('delivery_evidence', 'Historical projects awaiting delivery evidence', $evidence, $evidence === 0, 'Historical recognition is retained until delivery evidence is entered.', 'EVIDENCE REVIEW');

        $disposals = FixedAsset::query()->whereNotNull('disposal_date')->count();
        $add('disposals', 'Asset disposal records needing review', $disposals, $disposals === 0, 'Disposal requires proceeds, carrying value and gain/loss.', 'ACCOUNTING REVIEW');

        $opening = $this->statements->openingHistoricalBalance();
        $add('opening_balance', 'Historical opening balance remains provisional', $opening, false, 'A zero balance check does not independently verify pre-April books.', 'EVIDENCE REVIEW');

        $dupReq = ExpensePayment::query()->whereNotNull('request_id')->selectRaw('request_id')->groupBy('request_id')->havingRaw('COUNT(*) > 1')->count()
            + SalaryPayment::query()->whereNotNull('request_id')->selectRaw('request_id')->groupBy('request_id')->havingRaw('COUNT(*) > 1')->count();
        $add('duplicate_requests', 'Duplicate saved payment request IDs', $dupReq, $dupReq === 0, 'Each posted request appears at most once.');

        $unlinked = CustomerPayment::query()->whereNull('revenue_code')->count();
        $add('unlinked_payments', 'Payments without a linked Revenue ID', $unlinked, $unlinked === 0, 'An unmatched source cannot clear the correct receivable.', 'INPUT REQUIRED');

        return $c;
    }
}
