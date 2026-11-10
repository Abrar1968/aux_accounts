<?php

namespace Tests\Feature\Integrations;

use App\Models\AutomationRun;
use App\Models\CrmOption;
use App\Models\Lead;
use App\Models\LeadCall;
use App\Models\User;
use App\Services\Integrations\GoogleSheetSyncService;
use Tests\Concerns\SeedsWorkbook;
use Tests\TestCase;

/** Google Sheets → Lead CRM webhook (Apps Script push). */
class GoogleSheetSyncTest extends TestCase
{
    use SeedsWorkbook;

    private const URL = '/api/integrations/google-sheets/leads';

    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-10 12:00:00');
        $this->secret = app(GoogleSheetSyncService::class)->rotateSecret();
    }

    /** POST like the Apps Script does: signature over "<ts>.<body>". */
    private function push(array $payload, ?string $secret = null, ?int $ts = null)
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $ts ??= time();
        $sig = hash_hmac('sha256', $ts.'.'.$body, $secret ?? $this->secret);

        return $this->call('POST', self::URL, [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_AUXTECH_TIMESTAMP' => (string) $ts, 'HTTP_X_AUXTECH_SIGNATURE' => $sig,
        ], $body);
    }

    private function leadRow(array $over = [], int $row = 300): array
    {
        return ['row' => $row, 'values' => array_merge([
            'Lead Date' => '2026-11-10 00:00:00', 'Lead ID' => '20261110-SHEET001', 'Phone' => '01711000111',
            'Business / Website Type' => 'E-commerce / Online Store', 'Service' => 'Website', 'Source' => 'Facebook',
            'Outcome' => 'Interested', 'Calls Made (Manual)' => 1, 'Owner' => 'Rizwan', 'Quoted Price' => '25,000',
            'Notes' => 'Wants a Shopify-style store — রিপ্লাই দিবে', 'Stage' => 'IGNORED-derived', 'Lead Score' => 999,
        ], $over)];
    }

    public function test_requests_must_be_signed_and_fresh(): void
    {
        $this->postJson(self::URL, ['leads' => []])->assertStatus(401);
        $this->push(['leads' => []], 'wrong-secret')->assertStatus(401)->assertJson(['error' => 'Invalid signature.']);
        $this->push(['leads' => []], null, time() - 3600)->assertStatus(401)->assertJson(['error' => 'Stale or missing timestamp.']);
        $this->push(['leads' => []])->assertOk()->assertJson(['ok' => true]);
    }

    public function test_new_rows_create_leads_and_unchanged_rows_are_skipped(): void
    {
        $r = $this->push(['mode' => 'full', 'leads' => [$this->leadRow(), ['row' => 301, 'values' => ['Lead ID' => '', 'Phone' => '']]]])->assertOk();
        $r->assertJson(['created' => 1, 'skipped' => 1, 'errors' => []]);

        $lead = Lead::query()->where('code', '20261110-SHEET001')->firstOrFail();
        $this->assertSame(['8801711000111', 'Interested', 'Rizwan', '25000.00', 300], [$lead->normalized_phone, $lead->outcome, $lead->owner, $lead->quoted_price, $lead->sheet_row]);
        $this->assertNotSame('IGNORED-derived', $lead->stage, 'derived columns are recomputed, not copied');
        $this->assertSame(User::query()->where('name', 'Rizwan')->value('id'), $lead->owner_id);
        $this->assertStringContainsString('রিপ্লাই', $lead->notes, 'UTF-8 notes survive');

        $this->push(['mode' => 'full', 'leads' => [$this->leadRow()]])->assertJson(['unchanged' => 1, 'updated' => 0]);
    }

    public function test_sheet_edits_update_existing_leads_by_id_or_phone(): void
    {
        $seeded = Lead::query()->where('code', '20261007-IMP-3624')->firstOrFail();
        $this->push(['leads' => [$this->leadRow(['Lead ID' => '20261007-IMP-3624', 'Phone' => '01768093784', 'Outcome' => 'Meeting Set', 'Meeting / Office Visit Date & Time' => '2026-11-12 15:30:00'])]])
            ->assertJson(['updated' => 1]);
        $seeded->refresh();
        $this->assertSame(['Meeting Set', '2026-11-12 15:30'], [$seeded->outcome, $seeded->meeting_at->format('Y-m-d H:i')]);

        // a row first sent without its Lead ID is matched by phone, then adopts the sheet's ID
        $this->push(['leads' => [$this->leadRow(['Lead ID' => '', 'Phone' => '01822333444'])]])->assertJson(['created' => 1]);
        $this->push(['leads' => [$this->leadRow(['Lead ID' => '20261110-LATEID01', 'Phone' => '01822333444'])]])->assertJson(['updated' => 1]);
        $this->assertSame(1, Lead::query()->where('normalized_phone', '8801822333444')->count());
        $this->assertTrue(Lead::query()->where('code', '20261110-LATEID01')->exists());
    }

    public function test_archived_leads_stay_archived_and_new_options_join_picklists(): void
    {
        $lead = Lead::query()->where('code', '20261007-IMP-3624')->firstOrFail();
        $lead->delete();
        $this->push(['leads' => [$this->leadRow(['Lead ID' => '20261007-IMP-3624', 'Phone' => '01768093784', 'Outcome' => 'Won'])]])->assertJson(['archived' => 1]);
        $this->assertTrue(Lead::withTrashed()->find($lead->id)->trashed());

        $this->push(['leads' => [$this->leadRow(['Service' => 'SEO Audit', 'Source' => 'TikTok'])]])->assertJson(['created' => 1]);
        $this->assertTrue(CrmOption::query()->where(['type' => 'service', 'value' => 'SEO Audit'])->exists());
        $this->assertTrue(CrmOption::query()->where(['type' => 'source', 'value' => 'TikTok'])->exists());
    }

    public function test_bad_rows_are_reported_without_blocking_the_rest(): void
    {
        $r = $this->push(['leads' => [
            $this->leadRow(['Lead ID' => '20261110-BADOUT01', 'Phone' => '01933444555', 'Outcome' => 'Maybe later?'], 310),
            $this->leadRow(['Lead ID' => '20261110-BADPH001', 'Phone' => '12345'], 311),
            $this->leadRow(['Lead ID' => '20261110-GOOD0001', 'Phone' => '01944555666'], 312),
        ]])->assertOk();
        $r->assertJson(['created' => 1]);
        $this->assertCount(2, $r->json('errors'));
        $this->assertStringContainsString('Leads row 310', $r->json('errors.0'));
        $this->assertSame('error', AutomationRun::query()->where('command', 'crm:sheet-sync')->latest('id')->value('status'));
        $this->assertCount(2, app(GoogleSheetSyncService::class)->status()['errors']);
    }

    public function test_call_log_rows_are_added_once(): void
    {
        $call = ['row' => 80, 'values' => [
            'Recorded At (Auto)' => '2026-11-10 11:05:00', 'Lead ID' => '20261007-IMP-3624', 'Phone (Auto)' => '01768093784',
            'Call Type' => 'Follow-up', 'Outcome' => 'Interested', 'Caller / Owner' => 'Abrar', 'Call Notes' => 'Asked for pricing',
            'Call ID (Auto)' => 'sheet-call-0001', 'Save Status (Auto)' => 'Saved', 'Count Change (Auto)' => 1, 'Entry Source (Auto)' => 'Call',
        ]];
        $draft = ['row' => 81, 'values' => ['Lead ID' => '20261007-IMP-3624', 'Call ID (Auto)' => 'sheet-call-0002', 'Save Status (Auto)' => '']];
        $draft['values']['Save Status (Auto)'] = 'Draft';
        $this->push(['calls' => [$call, $draft]])->assertJson(['calls_added' => 1, 'skipped' => 1]);
        $this->push(['calls' => [$call]])->assertJson(['calls_existing' => 1, 'calls_added' => 0]);

        $c = LeadCall::query()->where('call_uuid', 'sheet-call-0001')->firstOrFail();
        $this->assertSame(['Interested', 'Abrar', '2026-11-10 11:05'], [$c->outcome, $c->caller, $c->recorded_at->format('Y-m-d H:i')]);

        $orphan = $call;
        $orphan['values']['Call ID (Auto)'] = 'sheet-call-0003';
        $orphan['values']['Lead ID'] = 'NOT-SYNCED-YET';
        $orphan['values']['Phone (Auto)'] = '01999000999';
        $this->assertCount(1, $this->push(['calls' => [$orphan]])->json('errors'));
    }

    public function test_a_batch_sends_one_realtime_refresh_not_one_per_lead(): void
    {
        \Illuminate\Support\Facades\Event::fake([\App\Events\LeadUpdated::class]);
        $this->push(['leads' => [
            $this->leadRow(['Lead ID' => '20261110-BATCH001', 'Phone' => '01755000001']),
            $this->leadRow(['Lead ID' => '20261110-BATCH002', 'Phone' => '01755000002']),
            $this->leadRow(['Lead ID' => '20261110-BATCH003', 'Phone' => '01755000003']),
        ]])->assertJson(['created' => 3]);
        \Illuminate\Support\Facades\Event::assertDispatchedTimes(\App\Events\LeadUpdated::class, 1);
    }

    public function test_settings_enable_rotate_and_download_script(): void
    {
        $owner = User::query()->where('email', 'admin@auxtech.local')->firstOrFail();
        $this->actingAs($owner)->get('/settings')->assertOk()
            ->assertInertia(fn ($p) => $p->where('sheets.enabled', true)->missing('settings.'.GoogleSheetSyncService::SECRET_KEY));

        $script = $this->get('/settings/integrations/google-sheets/script')->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="AuxtechLeadSync.gs"')->getContent();
        $this->assertStringContainsString($this->secret, $script);
        $this->assertStringContainsString(route('integrations.sheets.leads'), $script);
        $this->assertStringNotContainsString('{{', $script);

        $this->post('/settings/integrations/google-sheets/secret')->assertSessionHas('toast.type', 'success');
        $this->push(['leads' => []])->assertStatus(401); // old secret stops working after rotation

        $this->actingAs($this->userWithRole('caller'))->get('/settings/integrations/google-sheets/script')->assertForbidden();
    }
}
