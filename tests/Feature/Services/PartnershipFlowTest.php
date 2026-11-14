<?php

namespace Tests\Feature\Services;

use App\Models\Collaborator;
use App\Models\PartnershipAssignment;
use App\Models\PartnershipPackage;
use App\Models\PartnershipRevenue;
use App\Services\Accounting\CustomerPaymentService;
use App\Services\Accounting\FinancialStatementService;
use App\Services\Accounting\ReceivableService;
use App\Support\Period;
use Tests\Concerns\SeedsWorkbook;
use Tests\TestCase;

/** Partnership assignment → monthly rows → statements → receivable → receipt, end to end. */
class PartnershipFlowTest extends TestCase
{
    use SeedsWorkbook;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-14 10:00:00');
    }

    private function months(PartnershipAssignment $a): array
    {
        return PartnershipRevenue::query()->where('assignment_id', $a->id)->orderBy('earned_month')->get()
            ->map(fn ($r) => $r->earned_month->format('Y-m'))->all();
    }

    public function test_active_assignment_gets_rows_immediately_and_flows_into_the_books(): void
    {
        $col = Collaborator::query()->where('code', 'COL-0001')->firstOrFail(); // Paperless Education: Active, no agreement date
        $this->assertNull($col->agreement_start);
        $pkg = PartnershipPackage::query()->where('name', 'Maintenance Package')->firstOrFail();
        $before = app(FinancialStatementService::class)->forPeriod(Period::of('2026-10'));

        $this->actingAs($this->owner())->post('/accounting/partnerships/assignments', [
            'collaborator_id' => $col->id, 'package_id' => $pkg->id, 'status' => 'Active', 'earning_method' => 'Direct Amount',
            'effective_from' => '2026-10-01', 'effective_through' => '2027-12-11', 'notes' => 'Paperless education package',
        ])->assertSessionHas('toast.message', fn ($m) => str_contains($m, '2 monthly revenue rows ready'));
        $a = PartnershipAssignment::query()->latest('id')->firstOrFail();
        $this->assertSame(['2026-10', '2026-11'], $this->months($a), 'Oct + Nov now; later months appear as they start');

        // earned amount → statements, receivable, receipt
        $oct = PartnershipRevenue::query()->where('assignment_id', $a->id)->where('earned_month', '2026-10-01')->firstOrFail();
        $this->put("/accounting/partnerships/revenues/{$oct->id}", ['direct_amount' => 20000])->assertSessionHasNoErrors();
        $after = app(FinancialStatementService::class)->forPeriod(Period::of('2026-10'));
        $this->assertSame(round($before['partnership_revenue'] + 20000, 2), $after['partnership_revenue']);
        $this->assertSame(round($before['total_revenue'] + 20000, 2), $after['total_revenue']);
        $this->assertSame(0.0, (float) $after['balance_check'], 'books still balance');

        $ar = fn () => app(ReceivableService::class)->balancesAt(null)->firstWhere('code', $oct->code)['ar'];
        $this->assertSame(20000.0, $ar());
        app(CustomerPaymentService::class)->record(['paid_at' => '2026-11-10 12:00', 'receivable_type' => 'partnership', 'receivable_id' => $oct->id, 'cash_received' => 5000, 'method' => 'Bank Transfer']);
        $this->assertSame(15000.0, $ar());
        $this->assertSame(0.0, (float) app(FinancialStatementService::class)->forPeriod(Period::of('2026-11'))['balance_check']);

        // shortening the agreement drops the empty Nov row, keeps the earned Oct row
        $this->put("/accounting/partnerships/assignments/{$a->id}", $a->only('collaborator_id', 'package_id', 'earning_method', 'notes') + ['status' => 'Active', 'effective_from' => '2026-10-01', 'effective_through' => '2026-10-31'])->assertSessionHasNoErrors();
        $this->assertSame(['2026-10'], $this->months($a));
        // inactive: no new rows, earned history stays
        $this->put("/accounting/partnerships/assignments/{$a->id}", $a->only('collaborator_id', 'package_id', 'earning_method', 'notes') + ['status' => 'Inactive', 'effective_from' => '2026-10-01', 'effective_through' => '2027-12-11'])->assertSessionHasNoErrors();
        $this->assertSame(['2026-10'], $this->months($a));
    }

    public function test_share_of_sales_and_reasons_for_missing_rows(): void
    {
        $col = Collaborator::query()->where('code', 'COL-0001')->firstOrFail();
        $this->actingAs($this->owner())->post('/accounting/partnerships/assignments', [
            'collaborator_id' => $col->id, 'package_id' => PartnershipPackage::query()->value('id'), 'status' => 'Active',
            'earning_method' => 'Share of Sales', 'share_percent' => 0.2, 'effective_from' => '2026-11-01',
        ]);
        $nov = PartnershipRevenue::query()->latest('id')->firstOrFail();
        $this->put("/accounting/partnerships/revenues/{$nov->id}", ['gross_sales' => 50000])->assertSessionHasNoErrors();
        $this->assertSame(10000.0, (float) $nov->fresh()->recognized_revenue, '20% of 50,000');

        $this->get('/accounting/partnerships')->assertOk()->assertInertia(fn ($p) => $p
            ->where('assignments', fn ($list) => collect($list)->contains(fn ($a) => $a['status'] === 'Draft' && str_contains((string) $a['no_rows_reason'], 'Draft'))));
    }
}
