<?php

namespace App\Services\Accounting;

use App\Models\CapitalTransaction;
use App\Models\FixedAsset;
use App\Models\Liability;
use App\Repositories\Contracts\LiabilityRepositoryInterface;
use App\Repositories\Contracts\ReferralRepositoryInterface;
use App\Repositories\Contracts\SalaryPaymentRepositoryInterface;
use App\Services\Shared\PeriodService;
use App\Services\Shared\SettingsService;
use App\Support\Money;
use App\Support\Period;

/**
 * Profit & Loss, Cash Flow Statement (IAS 7, direct method + indirect reconciliation) and Trial
 * Balance for any month range. Every figure is built from the monthly statements
 * (FinancialStatementService, golden-tested against the workbook). Each report carries `checks`
 * that must all be 0.00:
 * - P&L net = Σ monthly net income.
 * - Cash flow net change = Σ monthly net cash movement = closing − opening cash, and the indirect
 *   operating cash flow = the direct one.
 * - Trial balance debits = credits, and its P&L part = the P&L for the same range.
 */
class FinancialReportService
{
    /** P&L presentation: group => [label, statement line keys] */
    public const PL_GROUPS = [
        'revenue' => ['Revenue', ['project_revenue', 'marketing_revenue', 'partnership_revenue']],
        'staff' => ['Staff costs', ['salary_expense', 'bonuses']],
        'sales' => ['Sales & marketing', ['referral_paid', 'marketing_advertising']],
        'operations' => ['Office & operations', ['rent', 'software_hosting', 'office_admin', 'legal_license', 'bank_fees', 'repairs_equipment', 'other_operating']],
        'revenue_share' => ['Revenue share', ['siam_share']],
        'depreciation' => ['Depreciation', ['depreciation']],
    ];

    public const INCOME_KEYS = ['project_revenue', 'marketing_revenue', 'partnership_revenue', 'total_revenue', 'salary_expense', 'referral_paid',
        'rent', 'marketing_advertising', 'software_hosting', 'office_admin', 'legal_license', 'bank_fees', 'repairs_equipment', 'bonuses',
        'other_operating', 'depreciation', 'siam_share', 'total_expenses', 'net_income'];

    public const CASH_KEYS = ['customer_receipts', 'capital_in', 'liability_in', 'total_cash_in', 'salary_referral_paid', 'expense_cash_paid',
        'advances_disbursed', 'liability_paid', 'asset_cash_paid', 'capital_out', 'total_cash_out', 'net_cash_movement'];

    public function __construct(
        private readonly FinancialStatementService $statements,
        private readonly PeriodService $periods,
        private readonly SettingsService $settings,
        private readonly SalaryPaymentRepositoryInterface $salaryPayments,
        private readonly ReferralRepositoryInterface $referrals,
        private readonly LiabilityRepositoryInterface $liabilities,
    ) {}

    // ------------------------------------------------------------------ ranges

    /** @return array{0: Period, 1: Period} clamped to [model start, current month], from ≤ to */
    public function range(?string $from, ?string $to): array
    {
        $start = $this->periods->modelStart();
        $now = Period::current();
        $t = $to ? Period::of($to) : $now;
        $t = $t->lt($start) ? $start : ($now->lt($t) ? $now : $t);
        $f = $from ? Period::of($from) : $this->fiscalYearStart($t);
        $f = $f->lt($start) ? $start : ($t->lt($f) ? $t : $f);

        return [$f, $t];
    }

    public function fiscalStartMonth(): int
    {
        $m = (int) $this->settings->get('accounting.fiscal_year_start', 7);

        return $m >= 1 && $m <= 12 ? $m : 7;
    }

    /** First month of the financial year containing $p (never before the model start). */
    public function fiscalYearStart(Period $p): Period
    {
        $m = $this->fiscalStartMonth();
        $year = (int) $p->start->format('n') >= $m ? (int) $p->start->format('Y') : (int) $p->start->format('Y') - 1;
        $fy = Period::of(sprintf('%04d-%02d', $year, $m));
        $start = $this->periods->modelStart();

        return $fy->lt($start) ? $start : $fy;
    }

    public function fiscalLabel(Period $p): string
    {
        $m = $this->fiscalStartMonth();
        $y = (int) $p->start->format('n') >= $m ? (int) $p->start->format('Y') : (int) $p->start->format('Y') - 1;

        return $m === 1 ? "FY {$y}" : 'FY '.$y.'–'.substr((string) ($y + 1), 2);
    }

    /** @return list<array{key:string,label:string,from:string,to:string}> quick ranges for the pickers */
    public function presets(): array
    {
        $start = $this->periods->modelStart();
        $now = Period::current();
        $out = [];
        $add = function (string $key, string $label, Period $f, Period $t) use (&$out, $start) {
            if ($t->lt($start)) {
                return;
            }
            $out[] = ['key' => $key, 'label' => $label, 'from' => ($f->lt($start) ? $start : $f)->key(), 'to' => $t->key()];
        };
        $add('month', 'This month', $now, $now);
        $add('last_month', 'Last month', $now->previous(), $now->previous());
        $q = $now->addMonths(-(((int) $now->start->format('n') - 1) % 3));
        $add('quarter', 'This quarter', $q, $now);
        $fy = $this->fiscalYearStart($now);
        $add('fy', $this->fiscalLabel($now).' to date', $fy, $now);
        if ($start->lt($fy)) {
            $add('last_fy', $this->fiscalLabel($fy->previous()), $this->fiscalYearStart($fy->previous()), $fy->previous());
        }
        $add('all', 'Since '.$start->label(), $start, $now);

        return $out;
    }

    // ------------------------------------------------------------------ Profit & Loss

    public function profitAndLoss(Period $from, Period $to, string $compare = 'previous'): array
    {
        $months = Period::range($from, $to);
        $cur = $this->sumMonths($months, self::INCOME_KEYS);
        $cmpRange = $this->compareRange($from, $to, $compare);
        $cmp = $cmpRange ? $this->sumMonths(Period::range(...$cmpRange), self::INCOME_KEYS) : null;

        $groups = [];
        foreach (self::PL_GROUPS as $key => [$label, $keys]) {
            $lines = array_map(fn ($k) => ['key' => $k, 'label' => FinancialStatementService::LINES[$k][0], 'amount' => $cur[$k], 'compare' => $cmp[$k] ?? null], $keys);
            $groups[] = ['key' => $key, 'label' => $label, 'lines' => $lines,
                'total' => Money::sum(array_column($lines, 'amount')), 'compare' => $cmp ? Money::sum(array_map(fn ($k) => $cmp[$k], $keys)) : null];
        }
        $byKey = array_column($groups, null, 'key');
        $summary = fn (?array $v) => $v === null ? null : $this->plSummary($v);
        $s = $summary($cur);

        return [
            'from' => $from->key(), 'to' => $to->key(), 'label' => $this->rangeLabel($from, $to),
            'compare' => $cmpRange ? ['from' => $cmpRange[0]->key(), 'to' => $cmpRange[1]->key(), 'label' => $this->rangeLabel(...$cmpRange)] : null,
            'groups' => $groups, 'summary' => $s, 'compare_summary' => $summary($cmp),
            'months' => array_map(fn (Period $p) => ['month' => $p->key(), 'label' => $p->start->format('M y')]
                + array_intersect_key($this->statements->forPeriod($p), array_flip(self::INCOME_KEYS)), $months),
            'checks' => [
                'Net profit = Σ monthly net income' => Money::round($s['net_profit'] - $cur['net_income']),
                'Revenue groups = total revenue' => Money::round($byKey['revenue']['total'] - $cur['total_revenue']),
                'Expense groups = total expenses' => Money::round(Money::sum(array_map(fn ($g) => $g['key'] === 'revenue' ? 0 : $g['total'], $groups)) - $cur['total_expenses']),
            ],
        ];
    }

    private function plSummary(array $v): array
    {
        $keys = [];
        foreach (['staff', 'sales', 'operations', 'revenue_share'] as $g) {
            array_push($keys, ...self::PL_GROUPS[$g][1]);
        }
        $opex = Money::sum(array_map(fn ($k) => $v[$k], $keys));
        $ebitda = Money::round($v['total_revenue'] - $opex);
        $net = Money::round($ebitda - $v['depreciation']);

        return [
            'revenue' => $v['total_revenue'], 'operating_expenses' => $opex, 'ebitda' => $ebitda, 'depreciation' => $v['depreciation'],
            'total_expenses' => $v['total_expenses'], 'net_profit' => $net,
            'net_margin' => $v['total_revenue'] != 0.0 ? round($net / $v['total_revenue'], 4) : null,
        ];
    }

    /** @return array{0: Period, 1: Period}|null */
    private function compareRange(Period $from, Period $to, string $mode): ?array
    {
        $n = count(Period::range($from, $to));
        [$f, $t] = match ($mode) {
            'previous' => [$from->addMonths(-$n), $from->previous()],
            'year' => [$from->addMonths(-12), $to->addMonths(-12)],
            default => [null, null],
        };
        if (! $f || $f->lt($this->periods->modelStart())) {
            return null; // no figures before the model start
        }

        return [$f, $t];
    }

    // ------------------------------------------------------------------ Cash flow (IAS 7)

    public function cashFlow(Period $from, Period $to): array
    {
        $months = Period::range($from, $to);
        $c = $this->sumMonths($months, array_merge(self::CASH_KEYS, ['net_income', 'depreciation']));
        [$d0, $d1] = [$from->date(), $to->cut()];
        $salaries = $this->salaryPayments->sumBetween('amount', 'paid_on', $d0, $d1);
        $referrals = $this->referrals->sumBetween('paid_amount', 'paid_on', $d0, $d1);
        $siamShare = abs($this->liabilities->sumBetween('cash_impact', 'period', $d0, $d1, ['cash_impact' => ['<', 0], 'liability_code' => 'LIA-SIAM-SHARE']));
        $siamShareIn = $this->liabilities->sumBetween('cash_impact', 'period', $d0, $d1, ['cash_impact' => ['>', 0], 'liability_code' => 'LIA-SIAM-SHARE']);

        $operating = [
            ['Cash received from customers', $c['customer_receipts']],
            ['Salaries paid to employees', -$salaries],
            ['Referral commissions paid', -$referrals],
            ['Operating expenses paid', -$c['expense_cash_paid']],
            ['Advances given to employees', -$c['advances_disbursed']],
            ['Revenue share paid to Siam', -$siamShare + $siamShareIn],
        ];
        $investing = [['Purchase of fixed assets', -$c['asset_cash_paid']]];
        $financing = [
            ['Owner capital received', $c['capital_in']],
            ['Owner distributions / drawings', -$c['capital_out']],
            ['Loans & contract funding received', Money::round($c['liability_in'] - $siamShareIn)],
            ['Loan, dues & financing repayments', -Money::round($c['liability_paid'] - $siamShare)],
        ];
        $section = fn (string $key, string $label, array $rows) => ['key' => $key, 'label' => $label,
            'lines' => array_map(fn ($r) => ['label' => $r[0], 'amount' => Money::round($r[1] + 0.0)], $rows), 'total' => Money::sum(array_column($rows, 1))];
        $sections = [$section('operating', 'Operating activities', $operating), $section('investing', 'Investing activities', $investing), $section('financing', 'Financing activities', $financing)];
        $net = Money::sum(array_column($sections, 'total'));
        $opening = $this->statements->forPeriod($from)['beginning_cash'];
        $closing = $this->statements->forPeriod($to)['ending_cash'];

        // indirect method: net profit adjusted for non-cash items and working-capital movements
        $o = $this->positionBefore($from);
        $e = $this->statements->forPeriod($to);
        $delta = fn (string $k) => Money::round($e[$k] - $o[$k]);
        $indirect = [
            ['Net profit / (loss)', $c['net_income']],
            ['Depreciation (non-cash)', $c['depreciation']],
            ['(Increase) / decrease in accounts receivable', -$delta('accounts_receivable')],
            ['(Increase) / decrease in employee advances', -$delta('advance_receivable')],
            ['(Increase) / decrease in prepaid & other assets', -$delta('other_assets')],
            ['Increase / (decrease) in customer advances', $delta('customer_advances')],
            ['Increase / (decrease) in payables', $delta('payables')],
        ];
        $indirectTotal = Money::sum(array_column($indirect, 1));

        return [
            'from' => $from->key(), 'to' => $to->key(), 'label' => $this->rangeLabel($from, $to),
            'sections' => $sections, 'net_change' => $net, 'opening_cash' => $opening, 'closing_cash' => $closing,
            'indirect' => ['lines' => array_map(fn ($r) => ['label' => $r[0], 'amount' => Money::round($r[1] + 0.0)], $indirect), 'total' => $indirectTotal],
            'non_cash' => $this->nonCashItems($d0, $d1),
            'months' => array_map(function (Period $p) {
                $cf = $this->monthlyCashSections($p);

                return ['month' => $p->key(), 'label' => $p->start->format('M y')] + $cf;
            }, $months),
            'checks' => [
                'Net change = Σ monthly net cash movement' => Money::round($net - $c['net_cash_movement']),
                'Opening + net change = closing cash' => Money::round($opening + $net - $closing),
                'Indirect operating = direct operating' => Money::round($indirectTotal - $sections[0]['total']),
            ],
        ];
    }

    /** @return array{operating: float, investing: float, financing: float, net: float} */
    private function monthlyCashSections(Period $p): array
    {
        $s = $this->statements->forPeriod($p);
        [$d0, $d1] = [$p->date(), $p->cut()];
        $siamNet = $this->liabilities->sumBetween('cash_impact', 'period', $d0, $d1, ['liability_code' => 'LIA-SIAM-SHARE']);
        $operating = Money::round($s['customer_receipts'] - $s['salary_referral_paid'] - $s['expense_cash_paid'] - $s['advances_disbursed'] + $siamNet);
        $investing = Money::round(-$s['asset_cash_paid']);
        $financing = Money::round($s['capital_in'] - $s['capital_out'] + $s['liability_in'] - $s['liability_paid'] - $siamNet);

        return ['operating' => $operating, 'investing' => $investing, 'financing' => $financing, 'net' => Money::sum([$operating, $investing, $financing])];
    }

    /** Transactions with no (or partial) cash effect — disclosed, not in the cash flow (IAS 7 §43). */
    private function nonCashItems(string $from, string $to): array
    {
        $items = [];
        foreach (CapitalTransaction::query()->where('period', '>=', $from)->where('period', '<', $to)->get() as $t) {
            $diff = Money::round((float) $t->equity_impact - (float) $t->cash_impact);
            if ($diff != 0.0) {
                $items[] = ['date' => $t->txn_date?->toDateString(), 'label' => $t->type.($t->notes ? ' — '.mb_strimwidth((string) $t->notes, 0, 70, '…') : ''), 'amount' => $diff];
            }
        }
        foreach (Liability::query()->where('period', '>=', $from)->where('period', '<', $to)->get() as $l) {
            $diff = Money::round((float) $l->increase - (float) $l->decrease - (float) $l->cash_impact);
            if ($diff != 0.0 && $l->liability_code !== 'LIA-SIAM-SHARE') {
                $items[] = ['date' => $l->txn_date?->toDateString(), 'label' => $l->liability_code.' · '.$l->description, 'amount' => $diff];
            }
        }
        foreach (FixedAsset::query()->whereNotNull('acquisition_date')->where('acquisition_date', '>=', $from)->where('acquisition_date', '<', $to)->get() as $a) {
            $diff = Money::round((float) $a->cost - (float) $a->cash_paid);
            if ($diff != 0.0) {
                $items[] = ['date' => $a->acquisition_date->toDateString(), 'label' => $a->name.' — acquired without cash (financed / contributed)', 'amount' => $diff];
            }
        }

        return $items;
    }

    // ------------------------------------------------------------------ Trial balance

    public function trialBalance(Period $asAt, ?Period $from = null): array
    {
        $start = $this->periods->modelStart();
        $from ??= $this->fiscalYearStart($asAt);
        $from = $from->lt($start) ? $start : ($asAt->lt($from) ? $asAt : $from);
        $close = $this->statements->forPeriod($asAt);
        $pl = $this->sumMonths(Period::range($from, $asAt), self::INCOME_KEYS);
        $reBf = $from->equals($start) ? 0.0 : $this->statements->forPeriod($from->previous())['cumulative_net_income'];
        $cost = $this->statements->fixedAssetCostAt($asAt->cut());
        $accDep = Money::round($cost - $close['net_fixed_assets']);
        $L = FinancialStatementService::LINES;

        $rows = [];
        $add = function (string $code, string $name, string $type, float $amount, string $normal) use (&$rows) {
            $amount = Money::round($amount);
            $dr = $normal === 'debit' ? $amount : -$amount;
            $rows[] = ['code' => $code, 'name' => $name, 'type' => $type, 'debit' => $dr > 0 ? $dr : 0.0, 'credit' => $dr < 0 ? Money::round(-$dr) : 0.0];
        };
        $add('1000', 'Cash / Bank', 'Asset', $close['cash_bank'], 'debit');
        $add('1100', 'Accounts Receivable', 'Asset', $close['accounts_receivable'], 'debit');
        $add('1200', 'Employee Advance Receivable', 'Asset', $close['advance_receivable'], 'debit');
        $add('1300', 'Prepaid, Other Current & Opening Assets', 'Asset', $close['other_assets'], 'debit');
        $add('1500', 'Fixed Assets — at cost', 'Asset', $cost, 'debit');
        $add('1590', 'Accumulated Depreciation', 'Asset (contra)', $accDep, 'credit');
        $add('2000', 'Bank Overdraft', 'Liability', $close['bank_overdraft'], 'credit');
        $add('2100', 'Operating, Salary & Siam Share Payables', 'Liability', $close['payables'], 'credit');
        $add('2200', 'Customer Advances / Contract Liability', 'Liability', $close['customer_advances'], 'credit');
        $add('2300', 'Siam Contract Liability', 'Liability', $close['siam_liability'], 'credit');
        $add('2400', 'AC Financing Payable', 'Liability', $close['ac_financing'], 'credit');
        $add('2500', 'Prior Company Dues', 'Liability', $close['prior_dues'], 'credit');
        $add('2600', 'Other Liabilities / Loans', 'Liability', $close['other_liabilities'], 'credit');
        $add('3000', 'Paid-in Capital', 'Equity', $close['paid_in_capital'], 'credit');
        $add('3100', 'Provisional Opening Historical Balance', 'Equity', $close['opening_balance'], 'credit');
        $add('3200', 'Retained Earnings brought forward', 'Equity', $reBf, 'credit');
        foreach (['project_revenue' => '4000', 'marketing_revenue' => '4100', 'partnership_revenue' => '4200'] as $k => $code) {
            $add($code, $L[$k][0], 'Income', $pl[$k], 'credit');
        }
        $codes = ['salary_expense' => '5000', 'bonuses' => '5050', 'referral_paid' => '5100', 'marketing_advertising' => '5150', 'rent' => '5200',
            'software_hosting' => '5250', 'office_admin' => '5300', 'legal_license' => '5350', 'bank_fees' => '5400', 'repairs_equipment' => '5450',
            'other_operating' => '5500', 'siam_share' => '5600', 'depreciation' => '5700'];
        foreach ($codes as $k => $code) {
            $add($code, $L[$k][0], 'Expense', $pl[$k], 'debit');
        }
        $debit = Money::sum(array_column($rows, 'debit'));
        $credit = Money::sum(array_column($rows, 'credit'));
        $plNet = Money::round(Money::sum(array_map(fn ($r) => $r['type'] === 'Income' ? $r['credit'] - $r['debit'] : ($r['type'] === 'Expense' ? $r['credit'] - $r['debit'] : 0), $rows)));

        return [
            'as_at' => $asAt->key(), 'as_at_label' => $asAt->start->endOfMonth()->format('d M Y'), 'from' => $from->key(),
            'period_label' => $this->rangeLabel($from, $asAt), 'rows' => $rows, 'total_debit' => $debit, 'total_credit' => $credit,
            'checks' => [
                'Total debits = total credits' => Money::round($debit - $credit),
                'Income − expenses = P&L net profit' => Money::round($plNet - $pl['net_income']),
                'Retained earnings b/f + profit = cumulative net income' => Money::round($reBf + $pl['net_income'] - $close['cumulative_net_income']),
                'Cost − accumulated depreciation = net fixed assets' => Money::round($cost - $accDep - $close['net_fixed_assets']),
            ],
        ];
    }

    // ------------------------------------------------------------------ helpers

    /** Balance sheet at the end of the month before $p (or the opening position before the model start). */
    public function positionBefore(Period $p): array
    {
        return $p->equals($this->periods->modelStart()) || $p->lt($this->periods->modelStart())
            ? $this->statements->openingPosition()
            : $this->statements->forPeriod($p->previous());
    }

    /** Σ of statement lines over months, added in paisa (no float drift). @param list<Period> $months */
    private function sumMonths(array $months, array $keys): array
    {
        $paisa = array_fill_keys($keys, 0);
        foreach ($months as $p) {
            $s = $this->statements->forPeriod($p);
            foreach ($keys as $k) {
                $paisa[$k] += Money::paisa($s[$k]);
            }
        }

        return array_map(fn ($v) => $v / 100, $paisa);
    }

    public function rangeLabel(Period $from, Period $to): string
    {
        return $from->equals($to) ? $from->label() : $from->label().' – '.$to->label();
    }
}
