<?php

namespace App\Services\Integrations;

use App\Exceptions\Domain\DomainException;
use App\Models\AutomationRun;
use App\Models\CrmOption;
use App\Models\Lead;
use App\Models\LeadCall;
use App\Models\User;
use App\Services\Crm\LeadService;
use App\Services\Crm\PhoneNormalizer;
use App\Services\Shared\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Google Sheets → Lead CRM live sync (push model). An Apps Script bound to the "Automated daily lead
 * sheet" posts edited rows (installable onEdit trigger) and a full snapshot every 5 minutes to a
 * signed webhook. The sheet is the source of truth for the columns it owns: rows are upserted by
 * Lead ID (then normalised phone), derived columns (stage, score, priority, follow-up, WhatsApp) are
 * recomputed by LeadComputer, unchanged rows are skipped by content hash and Call Log rows are
 * inserted once by Call ID. Leads archived in the app are never resurrected; rows are never deleted.
 */
class GoogleSheetSyncService
{
    public const SECRET_KEY = 'integrations.sheets_secret';

    public const STATUS_KEY = 'integrations.sheets_status';

    /** Seconds a signed request stays valid (replay protection). */
    public const TOLERANCE = 600;

    /** Columns the sheet owns (manual inputs); everything else is recomputed by the app. */
    private const LEAD_COLUMNS = [
        'Lead Date' => 'lead_date', 'Lead ID' => 'code', 'Phone' => 'phone', 'Business / Website Type' => 'business_type',
        'Industry / Product Category (Optional)' => 'industry', 'Service' => 'service', 'Source' => 'source', 'Outcome' => 'outcome',
        'Calls Made (Manual)' => 'calls_made', 'Owner' => 'owner', 'Quoted Price' => 'quoted_price', 'Follow-up Override' => 'followup_override',
        'Notes' => 'notes', 'Meeting / Office Visit Date & Time' => 'meeting_at', 'Meeting Details / Link' => 'meeting_details',
        'Deal Value BDT (Manual)' => 'deal_value', 'Lost Reason (Manual)' => 'lost_reason', 'Last Activity (Auto)' => 'last_activity_at',
    ];

    public function __construct(
        private readonly LeadService $leads,
        private readonly PhoneNormalizer $phones,
        private readonly SettingsService $settings,
    ) {}

    // ------------------------------------------------------------------ secret & signature

    public function secret(): ?string
    {
        $enc = $this->settings->get(self::SECRET_KEY);
        if (! $enc) {
            return null;
        }
        try {
            return Crypt::decryptString((string) $enc);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Create (or rotate) the shared secret; the Apps Script must be re-downloaded afterwards. */
    public function rotateSecret(): string
    {
        $secret = Str::random(48);
        $this->settings->set(self::SECRET_KEY, Crypt::encryptString($secret), 'integrations');
        $this->settings->flush();

        return $secret;
    }

    /** HMAC-SHA256 over "<timestamp>.<raw body>" with the shared secret; rejects stale or forged requests. */
    public function verify(string $body, ?string $timestamp, ?string $signature, ?int $now = null): void
    {
        $secret = $this->secret();
        if (! $secret) {
            throw new DomainException('Google Sheets sync is not enabled.');
        }
        $now ??= time();
        if (! ctype_digit((string) $timestamp) || abs($now - (int) $timestamp) > self::TOLERANCE) {
            throw new DomainException('Stale or missing timestamp.');
        }
        $expected = hash_hmac('sha256', $timestamp.'.'.$body, $secret);
        if (! is_string($signature) || ! hash_equals($expected, strtolower(trim($signature)))) {
            throw new DomainException('Invalid signature.');
        }
    }

    // ------------------------------------------------------------------ ingest

    /**
     * @param  array{mode?:string, leads?:list<array{row?:int, values:array<string,mixed>}>, calls?:list<array{row?:int, values:array<string,mixed>}>}  $payload
     * @return array<string, mixed> summary
     */
    public function ingest(array $payload): array
    {
        $sum = ['mode' => $payload['mode'] ?? 'edit', 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'archived' => 0,
            'calls_added' => 0, 'calls_existing' => 0, 'errors' => []];
        $users = User::query()->pluck('id', 'name')->all();

        foreach ($payload['leads'] ?? [] as $r) {
            try {
                $sum[$this->upsertLead((array) ($r['values'] ?? []), isset($r['row']) ? (int) $r['row'] : null, $users)]++;
            } catch (\Throwable $e) {
                $sum['errors'][] = 'Leads row '.($r['row'] ?? '?').': '.Str::limit($e->getMessage(), 160);
            }
        }
        foreach ($payload['calls'] ?? [] as $r) {
            try {
                $sum[$this->insertCall((array) ($r['values'] ?? []))]++;
            } catch (\Throwable $e) {
                $sum['errors'][] = 'Call Log row '.($r['row'] ?? '?').': '.Str::limit($e->getMessage(), 160);
            }
        }
        $this->record($sum, count($payload['leads'] ?? []), count($payload['calls'] ?? []));

        return $sum;
    }

    /** @return 'created'|'updated'|'unchanged'|'skipped'|'archived' */
    private function upsertLead(array $v, ?int $row, array $users): string
    {
        $phone = ltrim(trim((string) ($v['Phone'] ?? '')), "='"); // "=+973 …" typed as a formula, "'01…" text prefix
        if ($phone === '') {
            return 'skipped'; // unused daily slot
        }
        $data = [];
        foreach (self::LEAD_COLUMNS as $col => $field) {
            if (array_key_exists($col, $v)) {
                $data[$field] = $this->clean($field, $v[$col]);
            }
        }
        $hash = sha1(json_encode($data, JSON_UNESCAPED_UNICODE));
        $code = $data['code'] ?? null;
        unset($data['code']);
        $normalized = $this->phones->normalize($phone);

        $lead = $code ? Lead::withTrashed()->where('code', $code)->first() : null;
        $lead ??= $normalized ? Lead::withTrashed()->where('normalized_phone', $normalized)->orderBy('id')->first() : null;
        if ($lead?->trashed()) {
            return 'archived';
        }
        if ($lead && $lead->sheet_hash === $hash) {
            return 'unchanged';
        }
        $this->rememberOptions($data);
        if (! empty($data['owner'])) {
            $data['owner_id'] = $users[$data['owner']] ?? null;
        }
        $data['outcome'] = $data['outcome'] ?: 'New';
        $sync = ['sheet_row' => $row, 'sheet_hash' => $hash, 'sheet_synced_at' => now()];

        if ($lead) {
            if ($code && $lead->code !== $code && ! Lead::withTrashed()->where('code', $code)->exists()) {
                $lead->code = $code; // the sheet's automation assigned its own ID after the row was first synced
            }
            $outcomeChanged = $data['outcome'] !== $lead->outcome;
            $lead->fill($data + $sync);
            $this->leads->persist($lead, $outcomeChanged);

            return 'updated';
        }

        if (! $this->phones->isValid($phone)) {
            throw new DomainException("Invalid phone \"{$phone}\" — fix it in the sheet.");
        }
        $lead = new Lead($data + $sync + [
            'code' => $code ?: LeadService::newCode(),
            'lead_date' => $data['lead_date'] ?? now(config('app.timezone'))->toDateString(),
            'last_activity_at' => $data['last_activity_at'] ?? now(),
            'business_type' => $data['business_type'] ?? $this->settings->get('crm.default_business_type'),
        ]);
        $this->leads->persist($lead, true);

        return 'created';
    }

    /** @return 'calls_added'|'calls_existing'|'skipped' */
    private function insertCall(array $v): string
    {
        $uuid = trim((string) ($v['Call ID (Auto)'] ?? ''));
        $status = trim((string) ($v['Save Status (Auto)'] ?? 'Saved'));
        if ($uuid === '' || ($status !== '' && stripos($status, 'saved') === false)) {
            return 'skipped'; // draft row not saved in the sheet yet
        }
        if (LeadCall::query()->where('call_uuid', $uuid)->exists()) {
            return 'calls_existing';
        }
        $code = trim((string) ($v['Lead ID'] ?? ''));
        $lead = $code !== '' ? Lead::query()->where('code', $code)->first() : null;
        $lead ??= ($n = $this->phones->normalize($v['Phone (Auto)'] ?? null)) ? Lead::query()->where('normalized_phone', $n)->first() : null;
        if (! $lead) {
            throw new DomainException("Lead {$code} not found in the CRM yet (it syncs on the next run).");
        }
        LeadCall::query()->create([
            'call_uuid' => $uuid, 'lead_id' => $lead->id,
            'recorded_at' => $this->date($v['Recorded At (Auto)'] ?? null) ?? now(),
            'call_type' => $this->text($v['Call Type'] ?? null) ?? 'Follow-up',
            'outcome' => $this->text($v['Outcome'] ?? null), 'caller' => $this->text($v['Caller / Owner'] ?? null),
            'notes' => $this->text($v['Call Notes'] ?? null), 'next_followup' => $this->date($v['Next Follow-up'] ?? null),
            'status' => 'Saved', 'count_change' => (int) ($v['Count Change (Auto)'] ?? 1) ?: 1,
            'entry_source' => $this->text($v['Entry Source (Auto)'] ?? null) ?? 'Google Sheet',
        ]);

        return 'calls_added';
    }

    /** New service / source / owner / type values typed in the sheet join the CRM picklists. */
    private function rememberOptions(array $data): void
    {
        foreach (['service' => 'service', 'source' => 'source', 'business_type' => 'business_type', 'industry' => 'industry', 'owner' => 'owner'] as $field => $type) {
            if (! empty($data[$field])) {
                CrmOption::query()->firstOrCreate(['type' => $type, 'value' => $data[$field]], ['sort' => 999]);
            }
        }
    }

    private function clean(string $field, mixed $value): mixed
    {
        return match ($field) {
            'lead_date' => ($d = $this->date($value)) ? $d->toDateString() : null,
            'followup_override', 'meeting_at', 'last_activity_at' => $this->date($value)?->toDateTimeString(),
            'calls_made' => is_numeric($value) ? max(0, (int) $value) : 0,
            'quoted_price', 'deal_value' => is_numeric(str_replace(',', '', (string) $value)) ? round((float) str_replace(',', '', (string) $value), 2) : null,
            'phone' => ltrim(trim((string) $value), "='"),
            default => $this->text($value),
        };
    }

    private function text(mixed $v): ?string
    {
        $s = trim((string) ($v ?? ''));

        return $s === '' || $s === 'false' ? null : $s;
    }

    /** Accepts what Apps Script sends (Y-m-d H:i:s in Dhaka time) and the sheet's display formats. */
    private function date(mixed $v): ?CarbonImmutable
    {
        $s = trim((string) ($v ?? ''));
        if ($s === '') {
            return null;
        }
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d', 'd-M-Y H:i', 'd-M-Y', 'd/m/Y H:i', 'd/m/Y'] as $f) {
            try {
                $d = CarbonImmutable::createFromFormat($f, $s, config('app.timezone'));
            } catch (\Throwable) {
                continue;
            }
            if ($d && $d->format($f) === $s) {
                return str_contains($f, 'H') ? $d : $d->startOfDay();
            }
        }
        try {
            return CarbonImmutable::parse($s)->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    // ------------------------------------------------------------------ status

    /** Heartbeat for the Settings page; runs with changes or errors also go to the automation log. */
    private function record(array $sum, int $leadRows, int $callRows): void
    {
        $changed = $sum['created'] + $sum['updated'] + $sum['calls_added'];
        $this->settings->set(self::STATUS_KEY, [
            'last_seen' => now()->toDateTimeString(), 'mode' => $sum['mode'], 'lead_rows' => $leadRows, 'call_rows' => $callRows,
            'last_change' => $changed ? now()->toDateTimeString() : ($this->status()['last_change'] ?? null),
            'summary' => array_diff_key($sum, ['errors' => 1]), 'errors' => array_slice($sum['errors'], 0, 10),
        ], 'integrations');
        $this->settings->flush();
        if ($changed || $sum['errors']) {
            AutomationRun::query()->create([
                'command' => 'crm:sheet-sync', 'status' => $sum['errors'] ? 'error' : 'ok',
                'summary' => array_diff_key($sum, ['errors' => 1]) + ['errors' => count($sum['errors'])],
                'error' => $sum['errors'] ? implode("\n", array_slice($sum['errors'], 0, 5)) : null,
                'started_at' => now(), 'finished_at' => now(),
            ]);
        }
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        $s = $this->settings->get(self::STATUS_KEY);

        return is_array($s) ? $s : [];
    }

    /** Apps Script with this site's URL and the secret filled in (Settings → Integrations → Download). */
    public function appsScript(): string
    {
        $secret = $this->secret() ?? throw new DomainException('Enable the sync first to create a secret.');

        return strtr((string) file_get_contents(resource_path('integrations/AuxtechLeadSync.gs')), [
            '{{ENDPOINT}}' => route('integrations.sheets.leads'),
            '{{SECRET}}' => $secret,
            '{{APP}}' => (string) config('app.name'),
        ]);
    }
}
