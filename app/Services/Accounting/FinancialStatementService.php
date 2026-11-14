<?php

namespace App\Services\Accounting;

use App\Repositories\Contracts\CapitalTransactionRepositoryInterface;
use App\Repositories\Contracts\CustomerPaymentRepositoryInterface;
use App\Repositories\Contracts\EmployeeAdvanceRepositoryInterface;
use App\Repositories\Contracts\ExpensePaymentRepositoryInterface;
use App\Repositories\Contracts\FixedAssetRepositoryInterface;
use App\Repositories\Contracts\LiabilityRepositoryInterface;
use App\Repositories\Contracts\MarketingServiceRepositoryInterface;
use App\Repositories\Contracts\OpeningReceivableRepositoryInterface;
use App\Repositories\Contracts\OperatingExpenseRepositoryInterface;
use App\Repositories\Contracts\PartnershipRevenueRepositoryInterface;
use App\Repositories\Contracts\PayrollRepositoryInterface;
use App\Repositories\Contracts\ReferralRepositoryInterface;
use App\Repositories\Contracts\RevenueRecognitionRepositoryInterface;
use App\Repositories\Contracts\SalaryPaymentRepositoryInterface;
use App\Services\Accounting\Engine\DepreciationCalculator;
use App\Services\Shared\PeriodService;
use App\Services\Shared\SettingsService;
use App\Support\Money;
use App\Support\Period;

/**
 * Monthly_Statements port: Income Statement, Cash Reconciliation and Balance Sheet.
 * Every line reproduces the workbook formula (see docs/01-workbook-analysis.md §2.10).
 */
class FinancialStatementService
{
    /** Named expense categories shown as their own statement line (order = workbook rows 11–18). */
    public const NAMED_CATEGORIES = [
        'rent' => 'Rent',
        'marketing_advertising' => 'Marketing & Advertising',
        'software_hosting' => 'Software & Hosting',
        'office_admin' => 'Office & Admin',
        'legal_license' => 'Legal & License',
        'bank_fees' => 'Bank & Transfer Fees',
        'repairs_equipment' => 'Repairs & Equipment',
        'bonuses' => 'Bonuses',
    ];

    public const PROCESSING_FEE = 'Processing fee';

    /** Liability codes with their own balance-sheet line (LIA-SIAM-SHARE settles the Siam share payable). */
    public const NAMED_LIABILITIES = ['LIA-DEF', 'LIA-AC', 'LIA-SIAM', 'LIA-SIAM-SHARE'];

    /** key => [label, section] */
    public const LINES = [
        'project_revenue' => ['Project Revenue', 'income'],
        'marketing_revenue' => ['Marketing Service Revenue', 'income'],
        'partnership_revenue' => ['Partnership Revenue', 'income'],
        'total_revenue' => ['Total Revenue', 'income'],
        'salary_expense' => ['Salary Expense', 'expense'],
        'referral_paid' => ['Referral Commissions Paid', 'expense'],
        'rent' => ['Rent', 'expense'],
        'marketing_advertising' => ['Marketing & Advertising', 'expense'],
        'software_hosting' => ['Software & Hosting', 'expense'],
        'office_admin' => ['Office & Admin', 'expense'],
        'legal_license' => ['Legal & License', 'expense'],
        'bank_fees' => ['Bank & Transfer Fees', 'expense'],
        'repairs_equipment' => ['Repairs & Equipment', 'expense'],
        'bonuses' => ['Bonuses', 'expense'],
        'other_operating' => ['Other Operating / New Categories', 'expense'],
        'depreciation' => ['Depreciation', 'expense'],
        'siam_share' => ['Siam Revenue Share', 'expense'],
        'total_expenses' => ['Total Expenses', 'expense'],
        'net_income' => ['Net Income / (Loss)', 'result'],
        'beginning_cash' => ['Beginning Cash / (Overdraft)', 'cash'],
        'customer_receipts' => ['Customer Cash Receipts', 'cash'],
        'capital_in' => ['Owner Capital Cash In', 'cash'],
        'liability_in' => ['Contract / Liability Cash In', 'cash'],
        'total_cash_in' => ['Total Cash In', 'cash'],
        'salary_referral_paid' => ['Salary & Referral Cash Paid', 'cash'],
        'expense_cash_paid' => ['Operating Expense Cash Paid', 'cash'],
        'advances_disbursed' => ['Employee Advances Disbursed', 'cash'],
        'liability_paid' => ['Liability / Financing Cash Paid', 'cash'],
        'capital_out' => ['Owner Distributions / Capital Cash Out', 'cash'],
        'asset_cash_paid' => ['Direct Asset Purchase Cash Paid', 'cash'],
        'total_cash_out' => ['Total Cash Out', 'cash'],
        'net_cash_movement' => ['Net Cash Movement', 'cash'],
        'ending_cash' => ['Ending Cash / (Overdraft)', 'cash'],
        'cash_bank' => ['Cash / Bank', 'asset'],
        'accounts_receivable' => ['Accounts Receivable', 'asset'],
        'advance_receivable' => ['Employee Advance Receivable', 'asset'],
        'net_fixed_assets' => ['Net Fixed Assets', 'asset'],
        'other_assets' => ['Other Current / Opening Assets', 'asset'],
        'total_assets' => ['Total Assets', 'asset'],
        'prior_dues' => ['Prior Company Dues', 'liability'],
        'ac_financing' => ['AC Financing Payable', 'liability'],
        'siam_liability' => ['Siam Contract Liability', 'liability'],
        'customer_advances' => ['Customer Advances / Contract Liability', 'liability'],
        'bank_overdraft' => ['Bank Overdraft', 'liability'],
        'payables' => ['Operating, Salary & Siam Share Payables', 'liability'],
        'other_liabilities' => ['Other Liabilities / Loans', 'liability'],
        'total_liabilities' => ['Total Liabilities', 'liability'],
        'paid_in_capital' => ['Paid-in Capital', 'equity'],
        'opening_balance' => ['Provisional Opening Historical Balance', 'equity'],
        'cumulative_net_income' => ['Cumulative Net Income / (Loss)', 'equity'],
        'total_equity' => ['Total Equity', 'equity'],
        'total_liabilities_equity' => ['Total Liabilities + Equity', 'equity'],
        'balance_check' => ['BALANCE CHECK', 'check'],
    ];

    /** @var array<string, array<string, float>> memo per request */
    private array $memo = [];

    public function __construct(
        private readonly PeriodService $periods,
        private readonly SettingsService $settings,
        private readonly ReceivableService $receivables,
        private readonly DepreciationCalculator $depreciation,
        private readonly RevenueRecognitionRepositoryInterface $recognitions,
        private readonly MarketingServiceRepositoryInterface $marketing,
        private readonly PartnershipRevenueRepositoryInterface $partnerships,
        private readonly PayrollRepositoryInterface $payrolls,
        private readonly ReferralRepositoryInterface $referrals,
        private readonly OperatingExpenseRepositoryInterface $expenses,
        private readonly ExpensePaymentRepositoryInterface $expensePayments,
        private readonly CustomerPaymentRepositoryInterface $payments,
        private readonly CapitalTransactionRepositoryInterface $capital,
        private readonly LiabilityRepositoryInterface $liabilities,
        private readonly SalaryPaymentRepositoryInterface $salaryPayments,
        private readonly EmployeeAdvanceRepositoryInterface $advances,
        private readonly FixedAssetRepositoryInterface $assets,
        private readonly OpeningReceivableRepositoryInterface $openingAr,
    ) {}

    public function flush(): void
    {
        $this->memo = [];
    }

    /** @return array<string, float> line key => amount */
    public function forPeriod(Period $p): array
    {
        if (isset($this->memo[$p->key()])) {
            return $this->memo[$p->key()];
        }
        $start = $this->periods->modelStart();
        if ($p->lt($start)) {
            throw new \InvalidArgumentException('Statements start at '.$start->label());
        }
        $prev = $p->equals($start) ? null : $this->forPeriod($p->previous());

        $s = $this->incomeStatement($p);
        $s += $this->cashReconciliation($p, $prev);
        $s += $this->balanceSheet($p, $s, $prev);

        return $this->memo[$p->key()] = $s;
    }

    /** @return list<array{key:string,label:string,section:string,amount:float}> */
    public function lines(Period $p): array
    {
        $s = $this->forPeriod($p);
        $out = [];
        foreach (self::LINES as $key => [$label, $section]) {
            $out[] = ['key' => $key, 'label' => $label, 'section' => $section, 'amount' => $s[$key]];
        }

        return $out;
    }

    /** Side-by-side grid (Monthly_Statements tab). @return array<string, array<string,float>> */
    public function grid(Period $from, Period $to): array
    {
        $out = [];
        foreach (Period::range($from, $to) as $p) {
            $out[$p->key()] = $this->forPeriod($p);
        }

        return $out;
    }

    // ---------------------------------------------------------------- income statement

    private function incomeStatement(Period $p): array
    {
        [$from, $to] = [$p->date(), $p->cut()];
        $s = [];
        $s['project_revenue'] = $this->recognitions->sumBetween('recognized_amount', 'recognition_month', $from, $to);
        $s['marketing_revenue'] = $this->marketing->sumBetween('revenue', 'service_month', $from, $to);
        $s['partnership_revenue'] = $this->partnerships->sumBetween('recognized_revenue', 'earned_month', $from, $to);
        $s['total_revenue'] = Money::sum([$s['project_revenue'], $s['marketing_revenue'], $s['partnership_revenue']]);

        $s['salary_expense'] = $this->payrolls->sumBetween('gross_salary', 'period', $from, $to);
        $s['referral_paid'] = $this->referrals->sumBetween('paid_amount', 'paid_on', $from, $to);

        $fees = $this->payments->sumBetween('processing_fee', 'paid_at', $from, $to);
        $byCategory = $this->expenses->query()
            ->where('accrual_month', '>=', $from)->where('accrual_month', '<', $to)
            ->selectRaw('category, SUM(amount) total, SUM(CASE WHEN subcategory = ? THEN 0 ELSE amount END) non_fee', [self::PROCESSING_FEE])
            ->groupBy('category')->get()->keyBy('category');

        $named = 0.0;
        foreach (self::NAMED_CATEGORIES as $key => $category) {
            $row = $byCategory[$category] ?? null;
            $s[$key] = $key === 'bank_fees'
                ? Money::sum([(float) ($row->non_fee ?? 0), $fees])
                : Money::round($row->total ?? 0);
            $named += $s[$key];
        }
        $allNonFee = Money::sum($byCategory->pluck('non_fee'));
        $s['other_operating'] = Money::round($allNonFee + $fees - $named);

        $s['depreciation'] = $this->depreciationFor($p)['dep'];
        $s['siam_share'] = $this->recognitions->sumBetween('siam_share', 'recognition_month', $from, $to);

        $s['total_expenses'] = Money::sum(array_map(fn ($k) => $s[$k], [
            'salary_expense', 'referral_paid', ...array_keys(self::NAMED_CATEGORIES), 'other_operating', 'depreciation', 'siam_share',
        ]));
        $s['net_income'] = Money::round($s['total_revenue'] - $s['total_expenses']);

        return $s;
    }

    // ---------------------------------------------------------------- cash reconciliation

    private function cashReconciliation(Period $p, ?array $prev): array
    {
        [$from, $to] = [$p->date(), $p->cut()];
        $s = [];
        $s['beginning_cash'] = $prev ? $prev['ending_cash'] : Money::round($this->settings->get('opening.cash'));
        $s['customer_receipts'] = $this->payments->sumBetween('cash_received', 'paid_at', $from, $to);
        $s['capital_in'] = $this->capital->sumBetween('cash_impact', 'period', $from, $to, ['cash_impact' => ['>', 0]]);
        $s['liability_in'] = $this->liabilities->sumBetween('cash_impact', 'period', $from, $to, ['cash_impact' => ['>', 0]]);
        $s['total_cash_in'] = Money::sum([$s['customer_receipts'], $s['capital_in'], $s['liability_in']]);

        $s['salary_referral_paid'] = Money::sum([
            $this->salaryPayments->sumBetween('amount', 'paid_on', $from, $to),
            $this->referrals->sumBetween('paid_amount', 'paid_on', $from, $to),
        ]);
        $s['expense_cash_paid'] = Money::round($this->expensePayments->query()
            ->where('paid_on', '>=', $from)->where('paid_on', '<', $to)
            ->sum(\DB::raw("CASE WHEN origin = 'Withheld fee' THEN 0 ELSE amount END")));
        $s['advances_disbursed'] = $this->advances->sumBetween('amount', 'txn_date', $from, $to, ['type' => 'Disbursement']);
        $s['liability_paid'] = -$this->liabilities->sumBetween('cash_impact', 'period', $from, $to, ['cash_impact' => ['<', 0]]);
        $s['asset_cash_paid'] = $this->assets->sumBetween('cash_paid', 'cash_payment_date', $from, $to);
        // owner distributions / drawings (negative cash on Capital) leave the bank like any payment
        $s['capital_out'] = abs($this->capital->sumBetween('cash_impact', 'period', $from, $to, ['cash_impact' => ['<', 0]]));
        $s['total_cash_out'] = Money::sum([$s['salary_referral_paid'], $s['expense_cash_paid'], $s['advances_disbursed'], $s['liability_paid'], $s['asset_cash_paid'], $s['capital_out']]);
        $s['net_cash_movement'] = Money::round($s['total_cash_in'] - $s['total_cash_out']);
        $s['ending_cash'] = Money::round($s['beginning_cash'] + $s['net_cash_movement']);

        return $s;
    }

    // ---------------------------------------------------------------- balance sheet

    private function balanceSheet(Period $p, array $s, ?array $prev): array
    {
        $cut = $p->cut();
        $b = [];
        $b['cash_bank'] = max(0.0, $s['ending_cash']);
        $recv = $this->receivables->totalsAt($cut);
        $b['accounts_receivable'] = $recv['ar'];
        $b['advance_receivable'] = Money::round(
            $this->advances->sumBefore('amount', 'txn_date', $cut, ['type' => 'Disbursement'])
            - $this->advances->sumBefore('amount', 'txn_date', $cut, ['type' => 'Recovery'])
        );
        $b['net_fixed_assets'] = $this->depreciationFor($p)['nbv'];

        [$prepaid, $payable] = $this->prepaidAndPayables($cut);
        $b['other_assets'] = Money::sum([$this->settings->get('opening.other_fixed_assets'), $this->settings->get('opening.other_assets'), $prepaid]);
        $b['total_assets'] = Money::sum([$b['cash_bank'], $b['accounts_receivable'], $b['advance_receivable'], $b['net_fixed_assets'], $b['other_assets']]);

        $b['prior_dues'] = $this->liabilityBalance('LIA-DEF', $cut);
        $b['ac_financing'] = $this->liabilityBalance('LIA-AC', $cut);
        $b['siam_liability'] = $this->liabilityBalance('LIA-SIAM', $cut);
        $b['customer_advances'] = $recv['advance'];
        $b['bank_overdraft'] = max(0.0, -$s['ending_cash']);
        $b['payables'] = $payable;
        $b['other_liabilities'] = $this->otherLiabilities($cut);
        $b['total_liabilities'] = Money::sum([$b['prior_dues'], $b['ac_financing'], $b['siam_liability'], $b['customer_advances'], $b['bank_overdraft'], $b['payables'], $b['other_liabilities']]);

        $b['paid_in_capital'] = $this->capital->sumBefore('equity_impact', 'period', $cut);
        $b['opening_balance'] = $this->openingHistoricalBalance();
        $b['cumulative_net_income'] = Money::round(($prev['cumulative_net_income'] ?? 0) + $s['net_income']);
        $b['total_equity'] = Money::sum([$b['paid_in_capital'], $b['opening_balance'], $b['cumulative_net_income']]);
        $b['total_liabilities_equity'] = Money::round($b['total_liabilities'] + $b['total_equity']);
        $b['balance_check'] = Money::round($b['total_assets'] - $b['total_liabilities_equity']);

        return $b;
    }

    /**
     * Monthly_Statements rows 43 & 50 (MAP/LAMBDA formulas): per bill / per payroll,
     * prepaid = max(0, paid − earned) and payable = max(0, earned − paid), plus Siam share payable.
     *
     * @return array{0: float, 1: float}
     */
    public function prepaidAndPayables(string $cut): array
    {
        $paidExp = $this->expensePayments->query()->whereNotNull('paid_on')->where('paid_on', '<', $cut)
            ->selectRaw('operating_expense_id k, SUM(amount) s')->groupBy('operating_expense_id')->pluck('s', 'k');
        $paidSal = $this->salaryPayments->query()->whereNotNull('paid_on')->where('paid_on', '<', $cut)
            ->selectRaw('payroll_id k, SUM(amount) s')->groupBy('payroll_id')->pluck('s', 'k');
        $recov = $this->advances->query()->where('type', 'Recovery')->whereNotNull('payroll_id')->where('txn_date', '<', $cut)
            ->selectRaw('payroll_id k, SUM(amount) s')->groupBy('payroll_id')->pluck('s', 'k');

        $prepaid = 0;
        $payable = 0;
        foreach ($this->expenses->query()->get(['id', 'accrual_month', 'amount']) as $e) {
            $earned = $e->accrual_month->toDateString() < $cut ? Money::paisa($e->amount) : 0;
            $paid = Money::paisa($paidExp[$e->id] ?? 0);
            $prepaid += max(0, $paid - $earned);
            $payable += max(0, $earned - $paid);
        }
        foreach ($this->payrolls->query()->get(['id', 'period', 'gross_salary']) as $pr) {
            $earned = $pr->period->toDateString() < $cut ? Money::paisa($pr->gross_salary) : 0;
            $settled = Money::paisa($paidSal[$pr->id] ?? 0) + Money::paisa($recov[$pr->id] ?? 0);
            $prepaid += max(0, $settled - $earned);
            $payable += max(0, $earned - $settled);
        }
        $siamAccrued = $this->recognitions->sumBefore('siam_share', 'recognition_month', $cut);
        $siamPaid = $this->liabilities->sumBefore('decrease', 'period', $cut, ['liability_code' => 'LIA-SIAM-SHARE']);
        $payable += max(0, Money::paisa($siamAccrued) - Money::paisa($siamPaid));

        return [$prepaid / 100, $payable / 100];
    }

    public function liabilityBalance(string $code, string $cut): float
    {
        $inc = $this->liabilities->sumBefore('increase', 'period', $cut, ['liability_code' => $code]);
        $dec = $this->liabilities->sumBefore('decrease', 'period', $cut, ['liability_code' => $code]);

        return max(0.0, Money::round($inc - $dec));
    }

    /**
     * Balance sheet before the first model month (31 Mar 2026): the same rules as balanceSheet(),
     * with no in-model activity. Used as the opening column of cash flow / trial balance reports.
     *
     * @return array<string, float>
     */
    public function openingPosition(): array
    {
        $cut = $this->periods->modelStart()->date();
        $cash = Money::round($this->settings->get('opening.cash'));
        $recv = $this->receivables->totalsAt($cut);
        [$prepaid, $payable] = $this->prepaidAndPayables($cut);
        $b = [
            'ending_cash' => $cash, 'cash_bank' => max(0.0, $cash),
            'accounts_receivable' => $recv['ar'],
            'advance_receivable' => Money::round($this->advances->sumBefore('amount', 'txn_date', $cut, ['type' => 'Disbursement'])
                - $this->advances->sumBefore('amount', 'txn_date', $cut, ['type' => 'Recovery'])),
            'net_fixed_assets' => $this->openingNetFixedAssets($cut),
            'other_assets' => Money::sum([$this->settings->get('opening.other_fixed_assets'), $this->settings->get('opening.other_assets'), $prepaid]),
            'prior_dues' => $this->liabilityBalance('LIA-DEF', $cut), 'ac_financing' => $this->liabilityBalance('LIA-AC', $cut),
            'siam_liability' => $this->liabilityBalance('LIA-SIAM', $cut), 'customer_advances' => $recv['advance'],
            'bank_overdraft' => max(0.0, -$cash), 'payables' => $payable, 'other_liabilities' => $this->otherLiabilities($cut),
            'paid_in_capital' => $this->capital->sumBefore('equity_impact', 'period', $cut),
            'opening_balance' => $this->openingHistoricalBalance(), 'cumulative_net_income' => 0.0,
        ];
        $b['total_assets'] = Money::sum([$b['cash_bank'], $b['accounts_receivable'], $b['advance_receivable'], $b['net_fixed_assets'], $b['other_assets']]);
        $b['total_liabilities'] = Money::sum([$b['prior_dues'], $b['ac_financing'], $b['siam_liability'], $b['customer_advances'], $b['bank_overdraft'], $b['payables'], $b['other_liabilities']]);
        $b['total_equity'] = Money::sum([$b['paid_in_capital'], $b['opening_balance'], $b['cumulative_net_income']]);
        $b['total_liabilities_equity'] = Money::round($b['total_liabilities'] + $b['total_equity']);
        $b['balance_check'] = Money::round($b['total_assets'] - $b['total_liabilities_equity']);

        return $b;
    }

    /** NBV of assets held before the model start (cost − opening accumulated depreciation, floored at residual). */
    private function openingNetFixedAssets(string $cut): float
    {
        $nbv = 0;
        foreach ($this->assets->query()->get() as $a) {
            $acq = $a->acquisition_date?->toDateString();
            if ($acq !== null && $acq < $cut) {
                $nbv += Money::paisa(max((float) $a->residual_value, (float) $a->cost - (float) $a->opening_accumulated_dep));
            }
        }

        return $nbv / 100;
    }

    /** Gross cost of fixed assets acquired before $cut (same inclusion rule as the NBV in the schedule). */
    public function fixedAssetCostAt(string $cut): float
    {
        return Money::round($this->assets->query()->whereNotNull('acquisition_date')->where('acquisition_date', '<', $cut)->sum('cost'));
    }

    /** Loans / liabilities recorded under any other code (each code netted, never below zero). */
    public function otherLiabilities(string $cut): float
    {
        return Money::sum($this->liabilities->query()->where('period', '<', $cut)->whereNotIn('liability_code', self::NAMED_LIABILITIES)
            ->selectRaw('liability_code, SUM(increase) - SUM(decrease) bal')->groupBy('liability_code')->pluck('bal')
            ->map(fn ($b) => max(0.0, Money::round((float) $b)))->all());
    }

    /** Opening_Balance!B21 — known opening assets − known liabilities − pre-model paid-in capital. */
    public function openingHistoricalBalance(): float
    {
        $opening = $this->settings->get('accounting.opening_date');
        $assets = Money::sum([
            $this->openingAr->query()->sum('opening_amount'),
            $this->assets->query()->whereNotNull('acquisition_date')->where('acquisition_date', '<=', $opening)
                ->sum(\DB::raw('cost - opening_accumulated_dep')),
            $this->settings->get('opening.other_fixed_assets'),
            $this->settings->get('opening.other_assets'),
            $this->settings->get('opening.cash'),
        ]);
        $liabs = Money::sum([
            $this->liabilities->query()->where('txn_date', '<=', $opening)->sum(\DB::raw('increase - decrease')),
            $this->settings->get('opening.other_liabilities'),
        ]);
        $capital = Money::round($this->capital->query()->where('txn_date', '<=', $opening)->sum('equity_impact'));

        return Money::round($assets - $liabs - $capital);
    }

    /** @return array{dep: float, nbv: float} */
    public function depreciationFor(Period $p): array
    {
        $periods = Period::range($this->periods->modelStart(), $p);
        $dep = 0;
        $nbv = 0;
        foreach ($this->assets->query()->get() as $asset) {
            $row = $this->depreciation->schedule($asset, $periods)[$p->key()];
            $dep += Money::paisa($row['dep']);
            $nbv += Money::paisa($row['nbv']);
        }

        return ['dep' => $dep / 100, 'nbv' => $nbv / 100];
    }
}
