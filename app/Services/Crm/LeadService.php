<?php

namespace App\Services\Crm;

use App\Events\LeadUpdated;
use App\Exceptions\Domain\DomainException;
use App\Models\Lead;
use App\Models\LeadCall;
use App\Repositories\Contracts\LeadCallRepositoryInterface;
use App\Repositories\Contracts\LeadOutcomeRepositoryInterface;
use App\Repositories\Contracts\LeadRepositoryInterface;
use App\Services\Accounting\ProjectService;
use App\Services\Shared\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Leads tab (LeadCRM.gs processRows_, syncCount_). All derived columns go through LeadComputer.
 */
class LeadService
{
    private ?array $outcomes = null;

    public function __construct(
        private readonly LeadRepositoryInterface $leads,
        private readonly LeadCallRepositoryInterface $calls,
        private readonly LeadOutcomeRepositoryInterface $outcomeRepo,
        private readonly LeadComputer $computer,
        private readonly PhoneNormalizer $phones,
        private readonly SettingsService $settings,
    ) {
    }

    /** @return array<string, array{stage:string, score:int, followup_days:int}> */
    public function outcomes(): array
    {
        return $this->outcomes ??= $this->outcomeRepo->all()
            ->mapWithKeys(fn ($o) => [$o->name => ['stage' => $o->stage, 'score' => (int) $o->score, 'followup_days' => (int) $o->followup_days]])
            ->all();
    }

    public function today(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.timezone'))->startOfDay();
    }

    public static function newCode(?CarbonImmutable $date = null): string
    {
        return ($date ?? CarbonImmutable::now(config('app.timezone')))->format('Ymd').'-'.strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 8));
    }

    public function create(array $data): Lead
    {
        if (! $this->phones->isValid($data['phone'] ?? null)) {
            throw new DomainException('Bangladesh: 01… / 10 digits starting 1 / 8801…; international: +countrycode and number.');
        }
        $now = CarbonImmutable::now(config('app.timezone'));
        $lead = new Lead(array_merge([
            'business_type' => $this->settings->get('crm.default_business_type'),
            'owner' => $this->settings->get('crm.default_owner'),
            'outcome' => 'New',
        ], $data, [
            'code' => self::newCode($now),
            'lead_date' => $data['lead_date'] ?? $now->toDateString(),
            'last_activity_at' => $now,
            'owner_id' => $data['owner_id'] ?? Auth::id(),
        ]));

        return $this->persist($lead, true);
    }

    public function update(Lead $lead, array $data): Lead
    {
        $outcomeChanged = array_key_exists('outcome', $data) && $data['outcome'] !== $lead->outcome;
        if (isset($data['phone']) && ! $this->phones->isValid($data['phone'])) {
            throw new DomainException('Invalid phone number.');
        }
        $lead->fill($data);
        if ($outcomeChanged) {
            $lead->last_activity_at = CarbonImmutable::now(config('app.timezone'));
        }

        return $this->persist($lead, $outcomeChanged);
    }

    /** Applies derived columns, closed-date transitions and Office Visit rules, then saves. */
    public function persist(Lead $lead, bool $outcomeChanged): Lead
    {
        if (! isset($this->outcomes()[$lead->outcome ?: 'New'])) {
            throw new DomainException('Unknown Outcome: '.$lead->outcome);
        }
        if ($outcomeChanged && $lead->outcome === 'Office Visit — Time Pending') {
            $lead->meeting_at = null;
        }
        $this->computer->apply($lead, $this->outcomes());
        $closed = in_array($lead->stage, LeadComputer::CLOSED_STAGES, true);
        if ($closed && ! $lead->closed_date) {
            $lead->closed_date = $this->today()->toDateString();
        }
        if (! $closed && $lead->closed_date) {
            $lead->closed_date = null;
        }
        $becameWon = $lead->isDirty('stage') && $lead->stage === 'Won';
        $reassigned = $lead->exists && $lead->isDirty('owner') && $lead->owner;
        $lead->save();
        event(new LeadUpdated($lead->id, $lead->code));
        $notifications = app(\App\Services\Notifications\NotificationService::class);
        if ($becameWon) {
            $notifications->send(new \App\Notifications\DealWon('Deal won 🎉', $lead->phone.' · '.$lead->business_type.($lead->deal_value ? ' · ৳'.number_format((float) $lead->deal_value, 2) : ''), 'success', '/crm/leads/'.$lead->id));
        }
        if ($reassigned && ($user = \App\Models\User::query()->where('name', $lead->owner)->first())) {
            $notifications->send(new \App\Notifications\LeadAssigned('Lead assigned to you', $lead->phone.' · '.$lead->outcome, 'info', '/crm/leads/'.$lead->id), $user);
        }

        return $lead;
    }

    public function archive(Lead $lead): void
    {
        $lead->delete(); // soft delete; calls and history are kept
        event(new LeadUpdated($lead->id, $lead->code));
    }

    /** Leads!I "Calls Made": posts a Count update / Count correction delta so history is kept. */
    public function setCallsMade(Lead $lead, mixed $wanted): Lead
    {
        if (! is_numeric($wanted) || (int) $wanted != $wanted || $wanted < 0 || $wanted > 10000) {
            throw new DomainException('Calls Made must be a whole number from 0 to 10000');
        }
        $wanted = (int) $wanted;
        $current = $this->totalCalls($lead);
        $delta = $wanted - $current;
        if ($delta === 0) {
            return $lead;
        }

        return DB::transaction(function () use ($lead, $wanted, $current, $delta) {
            $now = CarbonImmutable::now(config('app.timezone'));
            $this->calls->create([
                'call_uuid' => (string) Str::uuid(), 'lead_id' => $lead->id, 'recorded_at' => $now,
                'call_type' => $delta > 0 ? 'Count update' : 'Count correction', 'outcome' => $lead->outcome,
                'caller' => $lead->owner, 'status' => 'Saved', 'count_change' => $delta,
                'entry_source' => $delta > 0 ? 'Lead count update' : 'Count correction', 'user_id' => Auth::id(),
                'notes' => "Total changed from {$current} to {$wanted}. Recorded now; individual call dates/results were not supplied.",
            ]);
            $lead->calls_made = $wanted;
            if ($delta > 0) {
                if ($lead->followup_override && CarbonImmutable::instance($lead->followup_override)->lt($now)) {
                    $lead->followup_override = null;
                }
                $lead->last_activity_at = $now;
            }

            return $this->persist($lead, $delta > 0);
        });
    }

    public function totalCalls(Lead $lead): int
    {
        return (int) $this->calls->query()->where('lead_id', $lead->id)->where('status', 'Saved')->sum('count_change');
    }

    /** Read model for pages: persisted + time-relative columns. */
    public function present(Lead $lead, ?int $duplicates = null): array
    {
        $today = $this->today();
        $duplicates ??= $lead->normalized_phone
            ? $this->leads->query()->where('normalized_phone', $lead->normalized_phone)->count() : 0;
        $last = $lead->relationLoaded('calls') ? $lead->calls->where('status', 'Saved')->sortByDesc('recorded_at')->first() : null;

        return $lead->toArray() + [
            'priority' => $this->computer->priority($lead, $today),
            'days_open' => $this->computer->daysOpen($lead, $today),
            'day' => CarbonImmutable::instance($lead->lead_date)->format('l'),
            'whatsapp' => $this->computer->whatsapp($lead),
            'duplicate' => $duplicates > 1,
            'data_checks' => $this->computer->dataChecks($lead, $duplicates > 1),
            'last_call_at' => $last?->recorded_at?->toDateTimeString(),
            'latest_logged_outcome' => $last?->outcome,
        ];
    }

    /** CRM → accounting: create a Project from a Won lead (new integration). */
    public function convertToProject(Lead $lead, array $data): \App\Models\Project
    {
        if ($lead->stage !== 'Won') {
            throw new DomainException('Only Won leads can be converted to a project.');
        }
        if ($lead->project_id) {
            throw new DomainException('This lead is already linked to a project.');
        }
        $project = app(ProjectService::class)->create(array_merge([
            'name' => $data['name'] ?? $lead->business_type.' '.$lead->code,
            'base_value' => (float) ($lead->deal_value ?? 0),
            'start_date' => CarbonImmutable::now(config('app.timezone'))->toDateString(),
            'lead_id' => $lead->id,
        ], $data));
        $lead->update(['project_id' => $project->id]);

        return $project;
    }
}
