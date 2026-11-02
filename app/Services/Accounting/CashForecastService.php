<?php

namespace App\Services\Accounting;

use App\Models\Employee;
use App\Models\OperatingExpense;
use App\Models\Payroll;
use App\Services\Banking\BankingService;
use App\Services\Shared\SettingsService;
use App\Support\Money;
use App\Support\Period;
use Carbon\CarbonImmutable;

/**
 * 90-day cash-flow forecast (FR-ACC-85, new), weekly buckets. Deterministic, documented assumptions:
 *  - Opening = book cash today (all accounts + unassigned).
 *  - Inflows: open A/R × `forecast.collection_rate` (default 60 %) spread evenly over the next 4 weeks.
 *  - Outflows: unpaid bills and unpaid payroll in week 1; payroll for active employees (base salary)
 *    on each month end in the window; recurring expense templates on their day of month.
 */
class CashForecastService
{
    public function __construct(
        private readonly BankingService $banking,
        private readonly ReceivableService $receivables,
        private readonly PayrollService $payroll,
        private readonly RecurringExpenseService $recurring,
        private readonly SettingsService $settings,
    ) {}

    public function forecast(?CarbonImmutable $today = null, int $days = 90): array
    {
        $today ??= CarbonImmutable::now(config('app.timezone'))->startOfDay();
        $end = $today->addDays($days);
        $rate = (float) ($this->settings->get('forecast.collection_rate') ?? 0.6);
        $opening = $this->banking->balances($today->toDateString())['total'];
        $items = [];
        $push = function (CarbonImmutable $d, string $kind, string $label, float $amount) use (&$items) {
            $items[] = ['date' => $d->toDateString(), 'kind' => $kind, 'label' => $label, 'amount' => Money::round($amount)];
        };

        $ar = $this->receivables->balancesAt(null)->where('ar', '>', 0);
        foreach ($ar as $r) {
            for ($w = 0; $w < 4; $w++) {
                $push($today->addDays(7 * $w + 3), 'inflow', 'Collections: '.$r['name'], $r['ar'] * $rate / 4);
            }
        }
        foreach (OperatingExpense::query()->with('payments')->get() as $e) {
            $due = Money::round((float) $e->amount - (float) $e->payments->sum('amount'));
            if ($due > 0.01 && $e->subcategory !== 'Processing fee') {
                $push($today->addDays(2), 'outflow', 'Unpaid bill '.$e->code, -$due);
            }
        }
        foreach (Payroll::query()->with('employee')->where('period', '<', Period::of($today)->date())->get() as $pr) {
            $unpaid = $this->payroll->summary($pr)['unpaid'];
            if ($unpaid > 0.01) {
                $push($today->addDays(2), 'outflow', 'Unpaid salary '.$pr->employee->name, -$unpaid);
            }
        }
        $monthlyPayroll = (float) Employee::query()->where('is_active', true)->sum('base_salary');
        for ($p = Period::of($today); $p->start->lte($end); $p = $p->next()) {
            $payday = CarbonImmutable::parse($p->end());
            if ($payday->gte($today) && $payday->lte($end) && $monthlyPayroll > 0) {
                $push($payday, 'outflow', 'Payroll '.$p->label(), -$monthlyPayroll);
            }
            foreach ($this->recurring->activeFor($p) as $t) {
                $d = $p->start->setDay(min($t->day_of_month, $p->start->daysInMonth));
                if ($d->gte($today) && $d->lte($end)) {
                    $push($d, 'outflow', $t->name, -(float) $t->amount);
                }
            }
        }

        $weeks = [];
        $balance = $opening;
        $lowest = ['balance' => $opening, 'week' => null];
        for ($w = 0; $today->addDays(7 * $w)->lte($end); $w++) {
            $from = $today->addDays(7 * $w);
            $to = $from->addDays(6);
            $in = collect($items)->filter(fn ($i) => $i['amount'] > 0 && $i['date'] >= $from->toDateString() && $i['date'] <= $to->toDateString());
            $out = collect($items)->filter(fn ($i) => $i['amount'] < 0 && $i['date'] >= $from->toDateString() && $i['date'] <= $to->toDateString());
            $inflow = Money::sum($in->pluck('amount'));
            $outflow = Money::round(-Money::sum($out->pluck('amount')));
            $balance = Money::round($balance + $inflow - $outflow);
            $weeks[] = ['week' => $w + 1, 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'inflow' => $inflow, 'outflow' => $outflow, 'closing' => $balance];
            if ($balance < $lowest['balance']) {
                $lowest = ['balance' => $balance, 'week' => $w + 1];
            }
        }

        return [
            'today' => $today->toDateString(), 'until' => $end->toDateString(), 'opening' => $opening,
            'collection_rate' => $rate, 'monthly_payroll' => Money::round($monthlyPayroll),
            'weeks' => $weeks, 'lowest' => $lowest, 'closing' => $balance,
            'items' => collect($items)->sortBy('date')->values()->all(),
        ];
    }
}
