<?php

namespace Tests\Feature\Services;

use App\Models\CapitalTransaction;
use App\Models\Investor;
use App\Services\Accounting\AssetService;
use App\Services\Accounting\CapitalService;
use App\Services\Accounting\FinancialReportService;
use App\Services\Accounting\FinancialStatementService;
use App\Services\Accounting\LiabilityService;
use App\Services\Accounting\ReceivableService;
use App\Support\Period;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsWorkbook;
use Tests\TestCase;

/**
 * Trial balance, P&L and cash flow statement: every range reconciles to 0.00, the totals agree
 * with the workbook's golden monthly statements and with raw ledger sums computed here
 * independently, and the books stay balanced for distributions, new loans and credit purchases.
 */
class FinancialReportsTest extends TestCase
{
    use SeedsWorkbook;

    private array $golden;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-20 10:00:00');
        $this->golden = json_decode(file_get_contents(base_path('tests/Fixtures/golden_monthly_statements.json')), true);
    }

    private function reports(): FinancialReportService
    {
        return app(FinancialReportService::class); // fresh instance = fresh statement memo
    }

    private function assertChecksZero(array $report, string $context): void
    {
        foreach ($report['checks'] as $label => $diff) {
            $this->assertSame(0.0, (float) $diff, "{$context}: {$label} is off by {$diff}");
        }
    }

    public function test_every_range_and_every_month_reconciles_to_zero(): void
    {
        $r = $this->reports();
        $months = Period::range(Period::of('2026-04'), Period::of('2026-11'));
        $pairs = 0;
        foreach ($months as $i => $from) {
            foreach (array_slice($months, $i) as $to) {
                $this->assertChecksZero($r->profitAndLoss($from, $to, 'previous'), "P&L {$from->key()}–{$to->key()}");
                $this->assertChecksZero($r->cashFlow($from, $to), "Cash flow {$from->key()}–{$to->key()}");
                $this->assertChecksZero($r->trialBalance($to, $from), "Trial balance {$from->key()}–{$to->key()}");
                $pairs++;
            }
        }
        $this->assertSame(36, $pairs);
        $this->assertSame(0.0, app(FinancialStatementService::class)->openingPosition()['balance_check'], 'opening balance sheet (31 Mar 2026) balances');
    }

    public function test_totals_agree_with_the_golden_workbook_statements(): void
    {
        $r = $this->reports();
        $sum = fn (string $label, array $months) => round(array_sum(array_map(fn ($m) => $this->golden[$m][$label], $months)), 2);
        $all = array_keys($this->golden); // 2026-04 … 2026-11
        [$from, $to] = [Period::of('2026-04'), Period::of('2026-11')];

        $pl = $r->profitAndLoss($from, $to, 'none');
        $this->assertEqualsWithDelta($sum('Total Revenue', $all), $pl['summary']['revenue'], 0.001);
        $this->assertEqualsWithDelta($sum('Total Expenses', $all), $pl['summary']['total_expenses'], 0.001);
        $this->assertEqualsWithDelta($sum('Net Income / (Loss)', $all), $pl['summary']['net_profit'], 0.001);
        $this->assertEqualsWithDelta($sum('Depreciation', $all), $pl['summary']['depreciation'], 0.001);

        $cf = $r->cashFlow($from, $to);
        $this->assertEqualsWithDelta($sum('Net Cash Movement', $all), $cf['net_change'], 0.001);
        $this->assertEqualsWithDelta($this->golden['2026-04']['Beginning Cash / (Overdraft)'], $cf['opening_cash'], 0.001);
        $this->assertEqualsWithDelta($this->golden['2026-11']['Ending Cash / (Overdraft)'], $cf['closing_cash'], 0.001);

        // trial balance at 31 Oct 2026: balance-sheet accounts equal the workbook balance sheet
        $tb = $r->trialBalance(Period::of('2026-10'), $from);
        $acct = collect($tb['rows'])->keyBy('code');
        $g = $this->golden['2026-10'];
        $this->assertEqualsWithDelta($g['Accounts Receivable'], $acct['1100']['debit'], 0.001);
        $this->assertEqualsWithDelta($g['Bank Overdraft'], $acct['2000']['credit'], 0.001);
        $this->assertEqualsWithDelta($g['Net Fixed Assets'], $acct['1500']['debit'] - $acct['1590']['credit'], 0.001);
        $this->assertEqualsWithDelta($g['Siam Contract Liability'], $acct['2300']['credit'], 0.001);
        $this->assertEqualsWithDelta($g['Operating, Salary & Siam Share Payables'], $acct['2100']['credit'], 0.001);
        $this->assertEqualsWithDelta($g['Paid-in Capital'], $acct['3000']['credit'], 0.001);
        $this->assertEqualsWithDelta(-$g['Provisional Opening Historical Balance'], $acct['3100']['debit'], 0.001, 'negative equity shows as a debit');
        $this->assertEqualsWithDelta($sum('Net Income / (Loss)', ['2026-04', '2026-05', '2026-06', '2026-07', '2026-08', '2026-09', '2026-10']),
            $g['Cumulative Net Income / (Loss)'], 0.001);
        $this->assertSame($tb['total_debit'], $tb['total_credit']);
    }

    public function test_reports_agree_with_raw_ledger_sums(): void
    {
        $r = $this->reports();
        [$from, $to] = [Period::of('2026-04'), Period::of('2026-11')];
        [$a, $b] = ['2026-04-01', '2026-12-01'];
        $raw = fn (string $sql) => round((float) DB::scalar($sql, [$a, $b]), 2);

        $pl = $r->profitAndLoss($from, $to, 'none');
        $revenue = $raw('select coalesce(sum(recognized_amount),0) from revenue_recognitions where recognition_month >= ? and recognition_month < ?')
            + $raw('select coalesce(sum(revenue),0) from marketing_services where service_month >= ? and service_month < ?')
            + $raw('select coalesce(sum(recognized_revenue),0) from partnership_revenues where earned_month >= ? and earned_month < ?');
        $this->assertEqualsWithDelta($revenue, $pl['summary']['revenue'], 0.001, 'revenue = recognitions + marketing + partnership');
        $lines = collect($pl['groups'])->flatMap(fn ($g) => $g['lines'])->keyBy('key');
        $this->assertEqualsWithDelta($raw('select coalesce(sum(gross_salary),0) from payrolls where period >= ? and period < ?'), $lines['salary_expense']['amount'], 0.001);
        $this->assertEqualsWithDelta($raw('select coalesce(sum(paid_amount),0) from referrals where paid_on >= ? and paid_on < ?'), $lines['referral_paid']['amount'], 0.001);
        $this->assertEqualsWithDelta(2200 * 8, $lines['depreciation']['amount'], 0.001, 'AC: 132,000 / 60 months = 2,200 a month');

        $cf = collect($r->cashFlow($from, $to)['sections'])->flatMap(fn ($s) => $s['lines'])->keyBy('label');
        $this->assertEqualsWithDelta($raw('select coalesce(sum(cash_received),0) from customer_payments where paid_at >= ? and paid_at < ?'), $cf['Cash received from customers']['amount'], 0.001);
        $this->assertEqualsWithDelta(-$raw('select coalesce(sum(amount),0) from salary_payments where paid_on >= ? and paid_on < ?'), $cf['Salaries paid to employees']['amount'], 0.001);
        $this->assertEqualsWithDelta(-$raw("select coalesce(sum(amount),0) from expense_payments where paid_on >= ? and paid_on < ? and origin <> 'Withheld fee'"), $cf['Operating expenses paid']['amount'], 0.001);
        $this->assertEqualsWithDelta(-$raw("select coalesce(sum(amount),0) from employee_advances where txn_date >= ? and txn_date < ? and type = 'Disbursement'"), $cf['Advances given to employees']['amount'], 0.001);
        $this->assertEqualsWithDelta($raw('select coalesce(sum(cash_impact),0) from capital_transactions where period >= ? and period < ? and cash_impact > 0'), $cf['Owner capital received']['amount'], 0.001);
        $this->assertEqualsWithDelta(300000, $cf['Loans & contract funding received']['amount'], 0.001, 'three Siam installments');
        $this->assertEqualsWithDelta(-(31500 + 120000 + 11000 * 3), $cf['Loan, dues & financing repayments']['amount'], 0.001, 'prior dues + AC installments');

        $tb = collect($r->trialBalance($to, $from)['rows'])->keyBy('code');
        $this->assertEqualsWithDelta(app(ReceivableService::class)->totalsAt('2026-12-01')['ar'], $tb['1100']['debit'], 0.001);
        $this->assertEqualsWithDelta(132000, $tb['1500']['debit'], 0.001);
        $this->assertEqualsWithDelta(19800 + 2200 * 8, $tb['1590']['credit'], 0.001, 'opening 19,800 + 8 months');
    }

    public function test_books_stay_balanced_for_distributions_new_loans_and_credit_purchases(): void
    {
        $nov = Period::of('2026-11');
        $before = $this->reports()->cashFlow($nov, $nov);
        $beforePl = $this->reports()->profitAndLoss($nov, $nov, 'none');
        $investor = Investor::query()->value('id');

        app(CapitalService::class)->create(['txn_date' => '2026-11-05', 'investor_id' => $investor, 'type' => 'Distribution', 'cash_impact' => -50000, 'equity_impact' => -50000]);
        app(LiabilityService::class)->create(['txn_date' => '2026-11-06', 'liability_code' => 'LIA-BANK-LOAN', 'description' => 'Bank loan', 'increase' => 200000, 'cash_impact' => 200000]);
        app(LiabilityService::class)->create(['txn_date' => '2026-11-10', 'liability_code' => 'LIA-BANK-LOAN', 'description' => 'Instalment', 'decrease' => 20000, 'cash_impact' => -20000]);
        app(AssetService::class)->create(['name' => 'Laptop on credit', 'category' => 'Equipment', 'acquisition_date' => '2026-11-08', 'in_service_date' => '2026-11-08', 'cost' => 60000, 'useful_life_months' => 60, 'cash_paid' => 0]);
        app(LiabilityService::class)->create(['txn_date' => '2026-11-08', 'liability_code' => 'LIA-AC', 'description' => 'Laptop financing', 'increase' => 60000, 'cash_impact' => 0]);

        $s = app(FinancialStatementService::class)->forPeriod($nov);
        $this->assertSame(0.0, $s['balance_check'], 'balance sheet still balances');
        $this->assertEqualsWithDelta(50000, $s['capital_out'], 0.001, 'distribution leaves the bank');
        $this->assertEqualsWithDelta(180000, $s['other_liabilities'], 0.001, 'new loan code is on the balance sheet');

        $r = $this->reports();
        $cf = $r->cashFlow($nov, $nov);
        $this->assertChecksZero($cf, 'Nov with new transactions');
        $this->assertEqualsWithDelta($before['sections'][2]['total'] - 50000 + 200000 - 20000, $cf['sections'][2]['total'], 0.001, 'financing');
        $this->assertEqualsWithDelta($before['sections'][1]['total'], $cf['sections'][1]['total'], 0.001, 'credit purchase uses no cash');
        $this->assertEqualsWithDelta($before['sections'][0]['total'], $cf['sections'][0]['total'], 0.001, 'operating unchanged');
        $nonCash = collect($cf['non_cash'])->pluck('amount', 'label');
        $this->assertTrue($nonCash->contains(60000.0), 'laptop disclosed as non-cash acquisition');
        $this->assertTrue($nonCash->has('LIA-AC · Laptop financing'), 'its financing disclosed too');

        $pl = $r->profitAndLoss($nov, $nov, 'none');
        $this->assertEqualsWithDelta($beforePl['summary']['depreciation'] + 1000, $pl['summary']['depreciation'], 0.001, '60,000 / 60 = 1,000 in Nov');
        $this->assertChecksZero($r->trialBalance($nov), 'TB Nov');
        $this->assertChecksZero($r->trialBalance($nov, Period::of('2026-04')), 'TB since start');
        $this->assertTrue(CapitalTransaction::query()->where('type', 'Distribution')->exists());
    }

    public function test_pages_presets_and_permissions(): void
    {
        $this->actingAs($this->owner());
        $this->get('/accounting/reports/profit-loss?from=2026-09&to=2026-11&compare=previous')->assertOk()
            ->assertInertia(fn ($p) => $p->component('Accounting/Reports/ProfitLoss')->where('report.from', '2026-09')->where('report.compare.from', '2026-06')->where('report.compare.to', '2026-08')
                ->where('report.checks', fn ($c) => collect($c)->every(fn ($v) => (float) $v === 0.0)));
        $this->get('/accounting/reports/cash-flow?from=2026-04&to=2026-11')->assertOk()->assertInertia(fn ($p) => $p->component('Accounting/Reports/CashFlow'));
        $this->get('/accounting/reports/trial-balance?month=2026-10')->assertOk()
            ->assertInertia(fn ($p) => $p->component('Accounting/Reports/TrialBalance')->where('report.from', '2026-07')); // FY starts in July

        $presets = collect($this->reports()->presets())->keyBy('key');
        $this->assertSame(['2026-07', '2026-11'], [$presets['fy']['from'], $presets['fy']['to']]);
        $this->assertSame('FY 2026–27 to date', $presets['fy']['label']);
        $this->assertSame(['2026-04', '2026-06'], [$presets['last_fy']['from'], $presets['last_fy']['to']], 'previous FY clipped to the model start');

        $this->put('/settings', ['settings' => ['accounting.fiscal_year_start' => 13]])->assertSessionHasErrors();
        $this->actingAs($this->userWithRole('viewer'))->get('/accounting/reports/trial-balance')->assertOk();
        $this->actingAs($this->userWithRole('caller'))->get('/accounting/reports/profit-loss')->assertForbidden();
    }
}
