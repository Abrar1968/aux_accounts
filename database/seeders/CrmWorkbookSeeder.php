<?php

namespace Database\Seeders;

use App\Models\AutomationRun;
use App\Models\CrmOption;
use App\Models\Lead;
use App\Models\LeadCall;
use App\Models\LeadOutcome;
use App\Models\User;
use App\Services\Crm\LeadComputer;
use App\Services\Crm\LeadService;
use Illuminate\Database\Seeder;

/**
 * Loads the Automated daily lead sheet (Lead CRM) workbook. Unused daily slots (rows without a
 * phone) are not imported; derived columns are recomputed by LeadComputer.
 */
class CrmWorkbookSeeder extends Seeder
{
    use LoadsWorkbookJson;

    public function run(): void
    {
        $settings = $this->json('crm/settings');
        foreach ($settings['outcomes'] as $i => $o) {
            LeadOutcome::query()->create($o + ['sort' => $i + 1]);
        }
        foreach (['services' => 'service', 'sources' => 'source', 'owners' => 'owner', 'business_types' => 'business_type', 'industries' => 'industry'] as $key => $type) {
            foreach ($settings[$key] as $i => $value) {
                CrmOption::query()->create(['type' => $type, 'value' => $value, 'sort' => $i + 1]);
            }
        }

        $users = User::query()->pluck('id', 'name');
        $outcomes = app(LeadService::class)->outcomes();
        $computer = app(LeadComputer::class);
        $ids = [];
        foreach ($this->json('crm/leads') as $r) {
            if (($r['Phone'] ?? '') === '' || empty($r['Lead ID'])) {
                continue;
            }
            $lead = new Lead([
                'code' => $r['Lead ID'], 'lead_date' => $r['Lead Date'], 'phone' => (string) $r['Phone'],
                'business_type' => $this->str($r['Business / Website Type'] ?? null),
                'industry' => $this->str($r['Industry / Product Category (Optional)'] ?? null),
                'service' => $this->str($r['Service'] ?? null), 'source' => $this->str($r['Source'] ?? null),
                'outcome' => $r['Outcome'] ?? 'New', 'owner' => $this->str($r['Owner'] ?? null),
                'owner_id' => isset($r['Owner']) ? ($users[$r['Owner']] ?? null) : null,
                'quoted_price' => isset($r['Quoted Price']) && is_numeric($r['Quoted Price']) ? $r['Quoted Price'] : null,
                'followup_override' => $r['Follow-up Override'] ?? null,
                'meeting_at' => $r['Meeting / Office Visit Date & Time'] ?? null,
                'meeting_details' => $this->str($r['Meeting Details / Link'] ?? null),
                'last_activity_at' => $r['Last Activity (Auto)'] ?? null,
                'closed_date' => $r['Closed Date (Auto)'] ?? null,
                'deal_value' => isset($r['Deal Value BDT (Manual)']) && is_numeric($r['Deal Value BDT (Manual)']) ? $r['Deal Value BDT (Manual)'] : null,
                'lost_reason' => $this->str($r['Lost Reason (Manual)'] ?? null),
                'calls_made' => (int) ($r['Calls Made (Manual)'] ?? 0),
                'notes' => $this->str($r['Notes'] ?? null),
            ]);
            $computer->apply($lead, $outcomes);
            $lead->save();
            $ids[$lead->code] = $lead->id;
        }

        foreach ($this->json('crm/call_logs') as $r) {
            if (! isset($ids[$r['Lead ID']])) {
                continue;
            }
            LeadCall::query()->create([
                'call_uuid' => $r['Call ID (Auto)'], 'lead_id' => $ids[$r['Lead ID']], 'recorded_at' => $r['Recorded At (Auto)'],
                'call_type' => $r['Call Type'], 'outcome' => $this->str($r['Outcome'] ?? null), 'caller' => $this->str($r['Caller / Owner'] ?? null),
                'notes' => $this->str($r['Call Notes'] ?? null), 'next_followup' => $r['Next Follow-up'] ?? null,
                'status' => $r['Save Status (Auto)'] ?? 'Saved', 'count_change' => (int) ($r['Count Change (Auto)'] ?? 1),
                'entry_source' => $r['Entry Source (Auto)'] ?? 'Call',
            ]);
        }

        foreach ($this->json('crm/automation_log') as $r) {
            AutomationRun::query()->create([
                'command' => 'crm:'.strtolower($r['Action']), 'status' => strtolower($r['Result'] ?? 'ok') === 'error' ? 'error' : 'ok',
                'summary' => ['details' => $r['Details'] ?? null, 'source' => 'Lead CRM Automation Log'],
                'error' => strtolower($r['Result'] ?? '') === 'error' ? ($r['Details'] ?? null) : null,
                'started_at' => $r['Time (Dhaka)'], 'finished_at' => $r['Time (Dhaka)'],
            ]);
        }
        app(\App\Services\Shared\SettingsService::class)->set('crm.cursor.daily', '2026-11-01', 'crm');
        app(\App\Services\Shared\SettingsService::class)->set('crm.cursor.monthly', '2026-11', 'crm');
    }
}
