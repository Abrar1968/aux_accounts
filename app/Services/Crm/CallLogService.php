<?php

namespace App\Services\Crm;

use App\Exceptions\Domain\DomainException;
use App\Models\Lead;
use App\Models\LeadCall;
use App\Repositories\Contracts\LeadCallRepositoryInterface;
use App\Services\Shared\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Call Log (LeadCRM.gs saveCall_): idempotent by call_uuid; updates the lead outcome, follow-up
 * override and last activity, and increments the call total by exactly one.
 */
class CallLogService
{
    public function __construct(
        private readonly LeadCallRepositoryInterface $calls,
        private readonly LeadService $leads,
        private readonly SettingsService $settings,
    ) {}

    /**
     * @param  array{call_uuid:string, call_type:string, outcome:string, caller?:?string, notes?:?string, next_followup?:?string}  $data
     * @return array{action:'saved'|'already_saved', call: LeadCall}
     */
    public function save(Lead $lead, array $data): array
    {
        if (! in_array($data['call_type'], ['Follow-up', 'Initial'], true)) {
            throw new DomainException('Choose Initial or Follow-up');
        }
        if (! isset($this->leads->outcomes()[$data['outcome']])) {
            throw new DomainException('Choose a valid Outcome');
        }
        $next = isset($data['next_followup']) && $data['next_followup'] !== '' ? CarbonImmutable::parse($data['next_followup'], config('app.timezone')) : null;
        if ($next && $next->toDateString() < $this->settings->get('crm.start')) {
            throw new DomainException('Next Follow-up must be a real date from the CRM start date.');
        }

        $existing = $this->calls->query()->where('call_uuid', $data['call_uuid'])->first();
        if ($existing) {
            if ((int) $existing->lead_id !== $lead->id) {
                throw new DomainException('Duplicate Call ID; do not copy saved rows');
            }

            return ['action' => 'already_saved', 'call' => $existing];
        }

        return DB::transaction(function () use ($lead, $data, $next) {
            $stamp = CarbonImmutable::now(config('app.timezone'));
            $call = $this->calls->create([
                'call_uuid' => $data['call_uuid'], 'lead_id' => $lead->id, 'recorded_at' => $stamp,
                'call_type' => $data['call_type'], 'outcome' => $data['outcome'],
                'caller' => $data['caller'] ?? $lead->owner ?? $this->settings->get('crm.default_owner'),
                'notes' => $data['notes'] ?? null, 'next_followup' => $next, 'status' => 'Saved',
                'count_change' => 1, 'entry_source' => 'Call', 'user_id' => Auth::id(),
            ]);
            $lead->outcome = $data['outcome'];
            if ($next) {
                $lead->followup_override = $next;
            } elseif ($lead->followup_override && CarbonImmutable::instance($lead->followup_override)->lt($stamp)) {
                $lead->followup_override = null;
            }
            $lead->last_activity_at = $stamp;
            $lead->calls_made = $this->leads->totalCalls($lead);
            $this->leads->persist($lead, true);

            return ['action' => 'saved', 'call' => $call];
        });
    }
}
