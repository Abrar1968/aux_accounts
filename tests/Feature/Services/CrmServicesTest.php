<?php

namespace Tests\Feature\Services;

use App\Exceptions\Domain\DomainException;
use App\Models\CrmReport;
use App\Models\Lead;
use App\Models\LeadCall;
use App\Services\Crm\CallLogService;
use App\Services\Crm\CrmReportService;
use App\Services\Crm\LeadService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsWorkbook;
use Tests\TestCase;

/** qaLeadCRM / qaCallFeatures / qaEvolution equivalents. */
class CrmServicesTest extends TestCase
{
    use SeedsWorkbook;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-02 11:00:00');
    }

    public function test_create_lead_defaults_and_phone_validation(): void
    {
        $lead = app(LeadService::class)->create(['phone' => '+1 (202) 555-0123']);
        $this->assertMatchesRegularExpression('/^20261102-[0-9A-F]{8}$/', $lead->code);
        $this->assertSame('E-commerce / Online Store', $lead->business_type);
        $this->assertSame('12025550123', $lead->normalized_phone);
        $this->assertSame('New', $lead->stage);
        $this->expectException(DomainException::class);
        app(LeadService::class)->create(['phone' => '12345']);
    }

    public function test_call_save_is_idempotent_and_updates_lead(): void
    {
        $lead = app(LeadService::class)->create(['phone' => '01700000001']);
        $uuid = (string) Str::uuid();
        $data = ['call_uuid' => $uuid, 'call_type' => 'Follow-up', 'outcome' => 'Develop Later', 'notes' => 'QA call'];
        $this->assertSame('saved', app(CallLogService::class)->save($lead, $data)['action']);
        $this->assertSame('already_saved', app(CallLogService::class)->save($lead->fresh(), $data)['action']);
        $lead->refresh();
        $this->assertSame(1, $lead->calls_made);
        $this->assertSame('Contacted', $lead->stage);
        $this->assertSame('2026-12-02', $lead->next_followup->toDateString(), 'Develop Later = 30 days');

        app(CallLogService::class)->save($lead, ['call_uuid' => (string) Str::uuid(), 'call_type' => 'Initial', 'outcome' => 'Interested', 'next_followup' => '2026-11-05']);
        $lead->refresh();
        $this->assertSame(2, $lead->calls_made);
        $this->assertSame('2026-11-05', $lead->next_followup->toDateString(), 'call next follow-up becomes override');
    }

    public function test_calls_made_deltas_keep_history_and_reject_fractions(): void
    {
        $lead = app(LeadService::class)->create(['phone' => '01700000002']);
        $svc = app(LeadService::class);
        $svc->setCallsMade($lead, 5);
        $svc->setCallsMade($lead->fresh(), 5);
        $svc->setCallsMade($lead->fresh(), 3);
        $this->assertSame(3, $svc->totalCalls($lead));
        $this->assertSame(['Count update', 'Count correction'], LeadCall::query()->where('lead_id', $lead->id)->orderBy('id')->pluck('call_type')->all());
        $this->expectException(DomainException::class);
        $svc->setCallsMade($lead->fresh(), 2.5);
    }

    public function test_closure_reopen_and_office_visit_rules(): void
    {
        $svc = app(LeadService::class);
        $lead = $svc->create(['phone' => '01700000003']);
        $svc->update($lead, ['outcome' => 'Won', 'deal_value' => 1500]);
        $this->assertSame('2026-11-02', $lead->closed_date->toDateString());
        $this->assertNull($lead->next_followup);
        $svc->update($lead, ['outcome' => 'Interested']);
        $this->assertNull($lead->closed_date, 'reopening clears closure');
        $code = $lead->code;
        $svc->update($lead, ['meeting_at' => '2026-11-04 15:00']);
        $svc->update($lead, ['outcome' => 'Office Visit — Time Pending']);
        $this->assertNull($lead->meeting_at);
        $this->assertSame('Interested', $lead->stage);
        $this->assertSame($code, $lead->code, 'stable id');
    }

    public function test_won_lead_converts_to_project(): void
    {
        $svc = app(LeadService::class);
        $lead = $svc->update($svc->create(['phone' => '01700000004']), ['outcome' => 'Won', 'deal_value' => 25000]);
        $project = $svc->convertToProject($lead, ['name' => 'New shop']);
        $this->assertSame(25000.0, $project->total_value);
        $this->assertSame($lead->id, $project->lead_id);
        $this->expectException(DomainException::class);
        $svc->convertToProject($lead->fresh(), ['name' => 'again']);
    }

    public function test_reports_are_clipped_deduplicated_and_cursor_driven(): void
    {
        $svc = app(CrmReportService::class);
        $p = $svc->period('Monthly', '2026-10-15');
        $this->assertSame(['start' => '2026-10-07', 'end' => '2026-10-31', 'status' => 'CLOSED'], $p);
        $a = $svc->snapshot('Monthly', '2026-10-31');
        $b = $svc->snapshot('Monthly', '2026-10-31');
        $this->assertSame($a->id, $b->id, 'unchanged snapshot reused');
        $this->assertSame(64, strlen($a->hash));
        $this->assertSame($a->payload['new_leads'], collect($a->payload['funnel'])->sum('count'), 'funnel covers the cohort');
        $this->assertSame(Lead::query()->whereBetween('lead_date', ['2026-10-07', '2026-10-31'])->count(), $a->payload['new_leads']);

        $made = $svc->runCursors(CarbonImmutable::parse('2026-11-02 00:10', 'Asia/Dhaka'));
        $this->assertSame(7, $made['daily'], 'max 7 days per run');
        $this->assertSame(1, $made['monthly'], 'October closed on 1 Nov');
        $this->assertSame(0, $made['yearly']);
        $again = $svc->runCursors(CarbonImmutable::parse('2026-11-02 00:15', 'Asia/Dhaka'));
        $this->assertSame(7, $again['daily']);
        $this->assertSame(0, $again['monthly']);
        $this->assertGreaterThanOrEqual(15, CrmReport::query()->count());
        $this->expectException(DomainException::class);
        $svc->period('Daily', '2026-10-01');
    }
}
