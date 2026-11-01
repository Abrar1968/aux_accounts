<?php

namespace Tests\Feature\Services;

use App\Exceptions\Domain\DomainException;
use App\Exceptions\Domain\PeriodLockedException;
use App\Models\Collaborator;
use App\Models\Employee;
use App\Models\OpeningReceivable;
use App\Models\PartnershipAssignment;
use App\Models\PartnershipPackage;
use App\Models\PartnershipRevenue;
use App\Models\Project;
use App\Models\RevenueRecognition;
use App\Services\Accounting\AdvanceService;
use App\Services\Accounting\CustomerPaymentService;
use App\Services\Accounting\FinancialStatementService;
use App\Services\Accounting\PartnershipService;
use App\Services\Accounting\PayrollService;
use App\Services\Accounting\ProjectService;
use App\Services\Accounting\ReceivableService;
use App\Services\Accounting\RevenueRecognitionService;
use App\Services\Shared\PeriodService;
use App\Support\Period;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsWorkbook;
use Tests\TestCase;

class AccountingServicesTest extends TestCase
{
    use SeedsWorkbook;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-15 10:00:00');
    }

    public function test_customer_payment_ids_fees_and_idempotency(): void
    {
        $svc = app(CustomerPaymentService::class);
        $xman = Project::query()->where('code', 'PRJ-2610-018')->first();
        $req = (string) Str::uuid();
        $p = $svc->record(['paid_at' => '2026-11-10 12:00:00', 'receivable_type' => 'project', 'receivable_id' => $xman->id,
            'cash_received' => 14925, 'processing_fee' => 75, 'request_id' => $req]);
        $this->assertSame('PAY-261110-044', $p->code);
        $this->assertSame($p->id, $svc->record(['paid_at' => '2026-11-10 12:00:00', 'receivable_type' => 'project', 'receivable_id' => $xman->id,
            'cash_received' => 14925, 'processing_fee' => 75, 'request_id' => $req])->id);

        $nov = app(FinancialStatementService::class)->forPeriod(Period::of('2026-11'));
        $this->assertSame(75.0, $nov['bank_fees'], 'fee expensed once in payment month');
        $this->assertSame(14925.0, $nov['customer_receipts']);
        $this->assertSame(0.0, $nov['balance_check']);
        $this->assertSame(20000.0, app(ProjectService::class)->metrics($xman)['customer_advance'], 'not delivered: advance');
    }

    public function test_point_in_time_delivery_auto_recognition_is_idempotent(): void
    {
        $xman = Project::query()->where('code', 'PRJ-2610-018')->first();
        app(ProjectService::class)->update($xman, ['actual_delivery_date' => '2026-11-12']);
        $svc = app(RevenueRecognitionService::class);
        $this->assertSame(1, $svc->addProgressRows(Period::of('2026-11'))['point_in_time']);
        $this->assertSame(0, $svc->addProgressRows(Period::of('2026-11'))['point_in_time']);
        $row = RevenueRecognition::query()->where('project_id', $xman->id)->first();
        $this->assertSame(20000.0, (float) $row->recognized_amount);
        $this->assertSame('2026-11-01', $row->recognition_month->toDateString());
        $nov = app(FinancialStatementService::class)->forPeriod(Period::of('2026-11'));
        $this->assertSame(20000.0, $nov['project_revenue']);
        $this->assertSame(0.0, $nov['balance_check']);
        $this->assertSame(15000.0, app(ProjectService::class)->metrics($xman->fresh())['current_ar']);
    }

    public function test_over_time_drafts_and_progress_with_siam_share(): void
    {
        $p = app(ProjectService::class)->create(['name' => 'Intl Shop', 'kind' => 'international', 'start_date' => '2026-11-01',
            'revenue_method' => 'Over Time', 'base_value' => 200000]);
        $this->assertStringStartsWith('IPRJ-2611-', $p->code);
        $svc = app(RevenueRecognitionService::class);
        $this->assertSame(1, $svc->addProgressRows(Period::of('2026-11'))['over_time']);
        $this->assertSame(0, $svc->addProgressRows(Period::of('2026-11'))['over_time']);
        $draft = RevenueRecognition::query()->where('project_id', $p->id)->first();
        $this->assertSame('Ending progress required', $draft->entry_check);
        $this->assertSame(0.0, (float) $draft->recognized_amount, 'unconfirmed progress books nothing');

        $row = $svc->update($draft, ['ending_progress' => 0.4]);
        $this->assertSame(80000.0, (float) $row->recognized_amount);
        $this->assertSame(12000.0, (float) $row->siam_share);
        $nov = app(FinancialStatementService::class)->forPeriod(Period::of('2026-11'));
        $this->assertSame(12000.0, $nov['siam_share']);
        $this->assertSame(0.0, $nov['balance_check']);
    }

    public function test_advance_recovery_links_payroll_or_fails(): void
    {
        $abrar = Employee::query()->where('slug', 'abrar')->first();
        app(PayrollService::class)->create($abrar->id, '2026-11-01', 40000);
        $rec = app(AdvanceService::class)->create(['txn_date' => '2026-11-05', 'employee_id' => $abrar->id, 'type' => 'Recovery', 'amount' => 1000]);
        $this->assertSame('PAYROLL-202611-abrar', $rec->payroll->code);
        $this->assertSame('ADV-051', $rec->code);
        $this->expectException(DomainException::class);
        app(AdvanceService::class)->create(['txn_date' => '2026-11-05', 'employee_id' => Employee::query()->where('slug', 'jenson')->value('id'), 'type' => 'Recovery', 'amount' => 10]);
    }

    public function test_duplicate_payroll_rejected(): void
    {
        $this->expectException(DomainException::class);
        app(PayrollService::class)->create(Employee::query()->where('slug', 'opi')->value('id'), '2026-10-15', 1);
    }

    public function test_partnership_rows_snapshot_and_receivable(): void
    {
        $col = Collaborator::query()->where('code', 'COL-0001')->first();
        $col->update(['agreement_start' => '2026-10-01', 'status' => 'Active']);
        $a = PartnershipAssignment::query()->create(['code' => 'ASM-T', 'collaborator_id' => $col->id, 'package_id' => PartnershipPackage::query()->value('id'),
            'effective_from' => '2026-10-01', 'status' => 'Active', 'earning_method' => 'Share of Sales', 'share_percent' => 0.2]);
        $svc = app(PartnershipService::class);
        $svc->generateThrough(Period::of('2026-11'));
        $this->assertSame(0, $svc->generateThrough(Period::of('2026-11')), 'idempotent');
        $this->assertSame(['2026-10-01', '2026-11-01'], PartnershipRevenue::query()->where('assignment_id', $a->id)->orderBy('earned_month')
            ->get()->map(fn ($r) => $r->earned_month->toDateString())->all());
        $nov = PartnershipRevenue::query()->where('assignment_id', $a->id)->where('earned_month', '2026-11-01')->firstOrFail();
        $this->assertNull($nov->recognized_revenue, 'blank earned is not zero');
        $a->update(['share_percent' => 0.5]);
        $svc->setEarning($nov, 10000, null);
        $this->assertSame(2000.0, (float) $nov->fresh()->recognized_revenue, 'share % snapshotted at creation');
        $this->assertSame(2000.0, app(FinancialStatementService::class)->forPeriod(Period::of('2026-11'))['partnership_revenue']);
        $bal = app(ReceivableService::class)->balancesAt('2026-12-01')->firstWhere('code', $nov->code);
        $this->assertSame(2000.0, $bal['ar']);
    }

    public function test_closed_period_blocks_customer_payment(): void
    {
        $this->expectException(PeriodLockedException::class);
        app(PeriodService::class)->close(Period::of('2026-10'), null);
        app(CustomerPaymentService::class)->record(['paid_at' => '2026-10-20 10:00', 'receivable_type' => 'opening',
            'receivable_id' => OpeningReceivable::query()->value('id'), 'cash_received' => 100]);
    }
}
