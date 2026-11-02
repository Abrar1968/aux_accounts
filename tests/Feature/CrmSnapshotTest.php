<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Services\Crm\CrmDashboardService;
use App\Services\Crm\LeadComputer;
use App\Services\Crm\LeadService;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** CRM oracle: the seeded leads reproduce the Lead CRM workbook as of 2 Nov 2026. */
class CrmSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected $seeder = DatabaseSeeder::class;

    public function test_dashboard_totals_match_workbook(): void
    {
        $s = app(CrmDashboardService::class)->summary(CarbonImmutable::parse('2026-11-02', 'Asia/Dhaka'));
        $this->assertSame(142, $s['total_leads']);
        $this->assertSame(93, $s['active_leads']);
        $this->assertSame(0, $s['won']);
        $this->assertSame(81, $s['due_or_overdue']);
        $this->assertSame(7, $s['time_pending_visits']);
    }

    public function test_every_lead_derivation_matches_workbook(): void
    {
        $today = CarbonImmutable::parse('2026-11-02', 'Asia/Dhaka');
        $rows = collect(json_decode(file_get_contents(database_path('seeders/data/crm/leads.json')), true))
            ->filter(fn ($r) => ! empty($r['Phone']))->keyBy('Lead ID');
        $computer = app(LeadComputer::class);
        $leads = app(LeadService::class);
        foreach (Lead::all() as $l) {
            $r = $rows[$l->code];
            $this->assertSame($r['Stage'], $l->stage, $l->code.' stage');
            $this->assertSame((int) $r['Lead Score'], (int) $l->score, $l->code.' score');
            $this->assertSame(isset($r['Next Follow-up']) ? substr($r['Next Follow-up'], 0, 10) : null, $l->next_followup?->toDateString(), $l->code.' next follow-up');
            $this->assertSame($r['Priority'] ?? '', $computer->priority($l, $today), $l->code.' priority');
            $this->assertSame(isset($r['Normalized Phone (Auto)']) ? (string) $r['Normalized Phone (Auto)'] : null, $l->normalized_phone, $l->code.' phone');
            $this->assertSame((int) ($r['Total Calls (Auto)'] ?? 0), $leads->totalCalls($l), $l->code.' calls');
        }
    }
}
