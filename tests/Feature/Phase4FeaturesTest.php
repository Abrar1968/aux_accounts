<?php

namespace Tests\Feature;

use App\Exceptions\Domain\DomainException;
use App\Models\BankAccount;
use App\Models\Lead;
use App\Models\OperatingExpense;
use App\Models\RecurringExpense;
use App\Models\User;
use App\Services\Accounting\BudgetService;
use App\Services\Accounting\CashForecastService;
use App\Services\Accounting\FinancialStatementService;
use App\Services\Accounting\ReceivableService;
use App\Services\Accounting\RecurringExpenseService;
use App\Services\Banking\BankingService;
use App\Services\Crm\LeaderboardService;
use App\Services\Crm\LeadImportService;
use App\Services\Shared\PeriodService;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\SeedsWorkbook;
use Tests\TestCase;

/** docs/02 "new" requirements: banking, budgets, forecast, recurring bills, statements, leaderboard, import. */
class Phase4FeaturesTest extends TestCase
{
    use SeedsWorkbook;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-02 10:00:00');
    }

    public function test_bank_accounts_plus_unassigned_equal_statement_cash_every_month(): void
    {
        $banking = app(BankingService::class);
        $statements = app(FinancialStatementService::class);
        foreach (Period::range(Period::of('2026-04'), Period::of('2026-10')) as $p) {
            $b = $banking->balances($p->end());
            $this->assertEqualsWithDelta($statements->forPeriod($p)['ending_cash'], $b['total'], 0.001, $p->label());
        }
        $oct = collect($banking->balances('2026-10-31')['accounts'])->keyBy('name');
        $this->assertSame(30000.0, $oct['EBL']['balance']);
        $this->assertSame(50000.0, $oct['bKash']['balance']);
        $this->assertSame(12000.0, $oct['Cash']['balance'], 'liability notes must not be matched as the Cash account');
        $this->assertGreaterThan(0, $oct['DBBL']['movements']);
    }

    public function test_reconciliation_matched_and_difference(): void
    {
        $ebl = BankAccount::query()->where('name', 'EBL')->first();
        $svc = app(BankingService::class);
        $this->assertSame('matched', $svc->reconcile($ebl, '2026-10-31', 30000)->status);
        $r = $svc->reconcile($ebl, '2026-10-31', 29500, 'bank charge?');
        $this->assertSame('difference', $r->status);
        $this->assertSame(-500.0, (float) $r->difference);
        $this->assertSame(1, $ebl->reconciliations()->count(), 'same statement date updates in place');
        $ledger = $svc->ledger($ebl, '2026-07-01', '2026-10-31');
        $this->assertSame(30000.0, $ledger['closing']);
        $this->expectException(DomainException::class);
        $svc->reconcile($ebl, '2026-12-01', 1);
    }

    public function test_budget_vs_actual(): void
    {
        $svc = app(BudgetService::class);
        $oct = Period::of('2026-10');
        $svc->save($oct, ['salary_expense' => 200000, 'total_revenue' => 50000, 'rent' => '']);
        $rows = collect($svc->compare($oct))->keyBy('key');
        $this->assertSame(3000.0, $rows['salary_expense']['variance']);
        $this->assertFalse($rows['salary_expense']['favourable'], 'cost over budget');
        $this->assertSame(-50000.0, $rows['total_revenue']['variance']);
        $this->assertFalse($rows['total_revenue']['favourable'], 'revenue under budget');
        $this->assertNull($rows['rent']['budget']);
        $this->assertSame(2, $svc->copyPrevious(Period::of('2026-11')));
        $this->expectException(DomainException::class);
        $svc->save($oct, ['balance_check' => 1]);
    }

    public function test_recurring_templates_generate_once_and_respect_locks(): void
    {
        $svc = app(RecurringExpenseService::class);
        $rent = $svc->create(['name' => 'Office rent', 'category' => 'Rent', 'amount' => 15000, 'day_of_month' => 31, 'start_month' => '2026-11-01']);
        $svc->create(['name' => 'Old hosting', 'category' => 'Software & Hosting', 'amount' => 500, 'day_of_month' => 5, 'start_month' => '2026-01-01', 'end_month' => '2026-10-01']);
        $this->assertSame(1, $svc->generate(Period::of('2026-11')));
        $this->assertSame(0, $svc->generate(Period::of('2026-11')), 'idempotent');
        $bill = OperatingExpense::query()->where('recurring_expense_id', $rent->id)->first();
        $this->assertSame('EXP-110', $bill->code);
        $this->assertSame('2026-11-30', $bill->expense_date->toDateString(), 'day clamped to month end');
        $this->assertSame(15000.0, app(FinancialStatementService::class)->forPeriod(Period::of('2026-11'))['rent']);
        app(PeriodService::class)->close(Period::of('2026-12'), null);
        $this->assertSame(0, $svc->generate(Period::of('2026-12')));
        $this->expectException(DomainException::class);
        $svc->create(['name' => 'x', 'category' => 'Rent', 'amount' => 1, 'day_of_month' => 1, 'start_month' => '2026-12-01', 'end_month' => '2026-11-01']);
    }

    public function test_cash_forecast_is_consistent(): void
    {
        RecurringExpense::query()->create(['name' => 'Wifi', 'category' => 'Office & Admin', 'amount' => 2000, 'day_of_month' => 10, 'start_month' => '2026-11-01', 'is_active' => true]);
        $f = app(CashForecastService::class)->forecast(CarbonImmutable::parse('2026-11-02', 'Asia/Dhaka'));
        $this->assertCount(13, $f['weeks']);
        $this->assertSame(-62160.0, $f['opening']);
        $items = collect($f['items']);
        $this->assertTrue($items->contains(fn ($i) => $i['label'] === 'Wifi' && $i['date'] === '2026-11-10'));
        $this->assertTrue($items->contains(fn ($i) => $i['label'] === 'Payroll Nov-2026' && $i['date'] === '2026-11-30'));
        $this->assertEqualsWithDelta($f['opening'] + collect($f['weeks'])->sum('inflow') - collect($f['weeks'])->sum('outflow'), $f['closing'], 0.01);
        $this->assertEqualsWithDelta(150800 * 0.6, $items->where('kind', 'inflow')->sum('amount'), 0.05);
    }

    public function test_customer_statements_match_receivable_balances(): void
    {
        $svc = app(ReceivableService::class);
        $this->assertSame(56200.0, $svc->statement('opening', 1)['closing']);
        foreach ($svc->balancesAt(null)->take(25) as $r) {
            $this->assertEqualsWithDelta($r['balance'], $svc->statement($r['type'], $r['id'])['closing'], 0.001, $r['code']);
        }
        $this->actingAs($this->owner())->get('/accounting/receivables/opening/1/statement')
            ->assertInertia(fn (Assert $p) => $p->component('Accounting/CustomerStatement')->where('statement.closing', 56200));
    }

    public function test_leaderboard_actuals_and_targets(): void
    {
        $svc = app(LeaderboardService::class);
        $svc->setTarget('Abrar', Period::of('2026-10'), ['calls_target' => 100, 'leads_target' => 200, 'wins_target' => 2]);
        $row = collect($svc->board(Period::of('2026-10'))['rows'])->firstWhere('owner', 'Abrar');
        $this->assertSame(['calls' => 103, 'leads' => 142, 'wins' => 0], $row['actual']);
        $this->assertSame(1.03, $row['achievement']['calls']);
        $this->assertEqualsWithDelta((1.03 + 0.71 + 0) / 3, $row['score'], 0.0001);
    }

    public function test_csv_import_preview_and_commit(): void
    {
        $owner = $this->owner();
        $existing = Lead::query()->first();
        $csv = "Phone,Business Type,Outcome,Owner,Notes\n01799000001,E-commerce / Online Store,Interested,Abrar,first\n"
            ."{$existing->phone},Other,New,Abrar,dup crm\n01799000001,Other,New,Abrar,dup file\n12345,Other,New,Abrar,bad\n+44 7700 900999,Other,Bogus outcome,Rizwan,intl\n";
        $file = UploadedFile::fake()->createWithContent('leads.csv', "\xEF\xBB\xBF".$csv);
        $p = app(LeadImportService::class)->preview($file, $owner->id);
        $this->assertSame(['total' => 5, 'new' => 2, 'duplicate_crm' => 1, 'duplicate_file' => 1, 'invalid' => 1], $p['counts']);
        $this->assertContains('Unknown outcome → New', $p['rows'][4]['issues']);

        $before = Lead::query()->count();
        $created = app(LeadImportService::class)->commit($p['token'], $owner->id, array_column($p['rows'], 'line'));
        $this->assertCount(2, $created, 'only new rows are imported even if others are selected');
        $this->assertSame($before + 2, Lead::query()->count());
        $this->assertSame('Interested', Lead::query()->where('normalized_phone', '8801799000001')->value('outcome'));
        $this->expectException(DomainException::class);
        app(LeadImportService::class)->commit($p['token'], $owner->id, [2]);
    }

    public function test_new_pages_render_and_permissions(): void
    {
        $owner = $this->owner();
        foreach (['/accounting/banking' => 'Accounting/Banking/Index', '/accounting/banking/1' => 'Accounting/Banking/Show', '/accounting/banking/unassigned' => 'Accounting/Banking/Show',
            '/accounting/budgets?month=2026-10' => 'Accounting/Budgets', '/accounting/forecast' => 'Accounting/Forecast', '/accounting/recurring' => 'Accounting/Recurring',
            '/crm/leaderboard?month=2026-10' => 'Crm/Leaderboard', '/crm/import' => 'Crm/Import'] as $url => $component) {
            $this->actingAs($owner)->get($url)->assertOk()->assertInertia(fn (Assert $p) => $p->component($component));
        }
        $this->actingAs($owner)->get('/crm/import/template')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->actingAs($owner)->post('/crm/import/preview', ['file' => UploadedFile::fake()->createWithContent('l.csv', "phone\n01799000777\n")])
            ->assertInertia(fn (Assert $p) => $p->where('preview.counts.new', 1));

        $caller = User::query()->where('email', 'abrar@auxtech.local')->first();
        $this->actingAs($caller)->get('/crm/import')->assertForbidden();
        $this->actingAs($caller)->get('/accounting/banking')->assertForbidden();
        $acc = $this->userWithRole('accountant');
        $this->actingAs($acc)->get('/accounting/banking')->assertOk();
        $this->actingAs($acc)->post('/accounting/banking/2/reconcile', ['statement_date' => '2026-10-31', 'statement_balance' => 30000])->assertSessionHas('toast.type', 'success');
    }
}
