<?php

namespace App\Services\Crm;

use App\Models\Lead;
use Carbon\CarbonImmutable;

/**
 * Pure port of the Leads formulas (stage, score, next follow-up, priority, days open, data checks).
 * `$outcomes` = [name => ['stage'=>, 'score'=>, 'followup_days'=>]].
 */
class LeadComputer
{
    public const CLOSED_STAGES = ['Won', 'Lost'];

    public const HIGH_STAGES = ['Interested', 'Meeting', 'Proposal', 'Negotiation'];

    public const MEETING_OUTCOMES = ['Meeting Set', 'Office Visit — Scheduled'];

    public function __construct(private readonly PhoneNormalizer $phones)
    {
    }

    /** @return array{stage:string, score:int, followup_days:int} */
    public function outcome(string $outcome, array $outcomes): array
    {
        $o = $outcomes[$outcome] ?? null;

        return ['stage' => $o['stage'] ?? 'New', 'score' => (int) ($o['score'] ?? 0), 'followup_days' => (int) ($o['followup_days'] ?? 1)];
    }

    /** Leads!M (next follow-up) — returns a date-time or null. */
    public function nextFollowup(Lead $lead, array $outcomes): ?CarbonImmutable
    {
        $o = $this->outcome($lead->outcome ?: 'New', $outcomes);
        if (in_array($o['stage'], self::CLOSED_STAGES, true)) {
            return null;
        }
        if ($lead->followup_override) {
            return CarbonImmutable::instance($lead->followup_override);
        }
        $base = $lead->last_activity_at
            ? CarbonImmutable::instance($lead->last_activity_at)->startOfDay()
            : CarbonImmutable::instance($lead->lead_date)->startOfDay();
        if (in_array($lead->outcome, self::MEETING_OUTCOMES, true)) {
            return $lead->meeting_at ? CarbonImmutable::instance($lead->meeting_at) : $base->addDay();
        }

        return $base->addDays($o['followup_days']);
    }

    /** Leads!O priority, relative to $today (Dhaka date). */
    public function priority(Lead $lead, CarbonImmutable $today): string
    {
        if (in_array($lead->stage, self::CLOSED_STAGES, true)) {
            return 'CLOSED';
        }
        if (! $lead->next_followup) {
            return '';
        }
        $due = CarbonImmutable::instance($lead->next_followup)->startOfDay();

        return match (true) {
            $due->lt($today->startOfDay()) => 'OVERDUE',
            $due->equalTo($today->startOfDay()) => 'TODAY',
            in_array($lead->stage, self::HIGH_STAGES, true) => 'HIGH',
            default => 'NORMAL',
        };
    }

    public function daysOpen(Lead $lead, CarbonImmutable $today): int
    {
        $end = $lead->closed_date ? CarbonImmutable::instance($lead->closed_date) : $today;

        return max(0, (int) CarbonImmutable::instance($lead->lead_date)->startOfDay()->diffInDays($end->startOfDay(), false));
    }

    /** Leads!AC data-check flags. */
    public function dataChecks(Lead $lead, bool $duplicate): array
    {
        $flags = [];
        if (str_contains((string) $lead->notes, 'PHONE REVIEW:')) {
            $flags[] = 'CHECK IMPORTED PHONE';
        }
        if (! $lead->normalized_phone) {
            $flags[] = 'CHECK PHONE';
        }
        if ($duplicate) {
            $flags[] = 'DUPLICATE PHONE';
        }
        if (in_array($lead->stage, self::CLOSED_STAGES, true) && ! $lead->closed_date) {
            $flags[] = 'MISSING CLOSED DATE';
        }
        if ($lead->stage === 'Won' && $lead->deal_value === null) {
            $flags[] = 'ADD DEAL VALUE';
        }
        if ($lead->stage === 'Lost' && ! $lead->lost_reason) {
            $flags[] = 'ADD LOST REASON';
        }
        if ($lead->outcome === 'Office Visit — Scheduled' && ! $lead->meeting_at) {
            $flags[] = 'ADD OFFICE VISIT DATE/TIME';
        }

        return $flags;
    }

    /** Applies the derived columns that are persisted (stage, score, phone, next follow-up). */
    public function apply(Lead $lead, array $outcomes): Lead
    {
        $o = $this->outcome($lead->outcome ?: 'New', $outcomes);
        $lead->stage = $o['stage'];
        $lead->score = $o['score'];
        $lead->normalized_phone = $this->phones->normalize($lead->phone);
        $lead->next_followup = $this->nextFollowup($lead, $outcomes);

        return $lead;
    }

    public function whatsapp(Lead $lead): ?string
    {
        return str_contains((string) $lead->notes, 'PHONE REVIEW:') ? null : $this->phones->whatsappUrl($lead->normalized_phone);
    }
}
