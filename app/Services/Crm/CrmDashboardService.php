<?php

namespace App\Services\Crm;

use App\Repositories\Contracts\LeadRepositoryInterface;
use App\Services\Shared\SettingsService;
use Carbon\CarbonImmutable;

/**
 * Dashboard / Today / Follow-Ups / Meetings tabs.
 */
class CrmDashboardService
{
    public function __construct(
        private readonly LeadRepositoryInterface $leads,
        private readonly LeadService $leadService,
        private readonly SettingsService $settings,
    ) {
    }

    public function summary(?CarbonImmutable $today = null): array
    {
        $today ??= $this->leadService->today();
        $all = $this->leads->query()->get();
        $active = $all->whereNotIn('stage', LeadComputer::CLOSED_STAGES);
        $won = $all->where('stage', 'Won')->count();
        $due = $active->filter(fn ($l) => $l->next_followup && $l->next_followup->toDateString() <= $today->toDateString());

        return [
            'today' => $today->toDateString(),
            'system_start' => $this->settings->get('crm.start'),
            'total_leads' => $all->count(),
            'active_leads' => $active->count(),
            'won' => $won,
            'lost' => $all->where('stage', 'Lost')->count(),
            'conversion_rate' => $all->count() ? round($won / $all->count(), 4) : 0,
            'new_today' => $all->filter(fn ($l) => $l->lead_date->toDateString() === $today->toDateString())->count(),
            'due_or_overdue' => $due->count(),
            'meetings_today' => $active->filter(fn ($l) => $l->meeting_at && $l->meeting_at->toDateString() === $today->toDateString())->count(),
            'upcoming_meetings' => $active->filter(fn ($l) => $l->meeting_at && $l->meeting_at->toDateString() >= $today->toDateString())->count(),
            'time_pending_visits' => $active->where('outcome', 'Office Visit — Time Pending')->count(),
            'daily_target' => (int) $this->settings->get('crm.daily_slots'),
        ];
    }

    /** Follow-up queue: active leads ordered by due date (overdue first). */
    public function followUps(?string $owner = null)
    {
        return $this->leads->query()->whereNotIn('stage', LeadComputer::CLOSED_STAGES)->whereNotNull('next_followup')
            ->when($owner, fn ($q) => $q->where('owner', $owner))->orderBy('next_followup')->get()
            ->map(fn ($l) => $this->leadService->present($l));
    }

    public function meetings(): array
    {
        $active = $this->leads->query()->whereNotIn('stage', LeadComputer::CLOSED_STAGES);

        return [
            'scheduled' => (clone $active)->whereIn('outcome', LeadComputer::MEETING_OUTCOMES)->orderBy('meeting_at')->get()
                ->map(fn ($l) => $this->leadService->present($l))->values(),
            'time_pending' => (clone $active)->where('outcome', 'Office Visit — Time Pending')->orderBy('next_followup')->get()
                ->map(fn ($l) => $this->leadService->present($l))->values(),
        ];
    }
}
