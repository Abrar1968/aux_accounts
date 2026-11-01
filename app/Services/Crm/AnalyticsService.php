<?php

namespace App\Services\Crm;

use App\Repositories\Contracts\LeadCallRepositoryInterface;
use App\Repositories\Contracts\LeadRepositoryInterface;
use App\Support\Money;

/**
 * Analytics + report datasets. Semantics follow the workbook: wins counted by Closed Date,
 * cohort conversion by Lead Date, calls by recorded time (count changes included).
 */
class AnalyticsService
{
    public function __construct(
        private readonly LeadRepositoryInterface $leads,
        private readonly LeadCallRepositoryInterface $calls,
    ) {
    }

    /** @return array<string,mixed> */
    public function forPeriod(string $start, string $end): array
    {
        $endCut = date('Y-m-d', strtotime($end.' +1 day'));
        $cohort = $this->leads->query()->where('lead_date', '>=', $start)->where('lead_date', '<=', $end)->get();
        $won = $this->leads->query()->where('stage', 'Won')->whereNotNull('closed_date')
            ->where('closed_date', '>=', $start)->where('closed_date', '<=', $end)->get();
        $lost = $this->leads->query()->where('stage', 'Lost')->whereNotNull('closed_date')
            ->where('closed_date', '>=', $start)->where('closed_date', '<=', $end)->count();
        $calls = $this->calls->query()->where('status', 'Saved')->where('recorded_at', '>=', $start)->where('recorded_at', '<', $endCut);
        $group = fn (string $col) => $cohort->groupBy(fn ($l) => $l->{$col} ?: 'Not set')
            ->map(fn ($g, $k) => ['label' => $k, 'leads' => $g->count(), 'won' => $g->where('stage', 'Won')->count()])
            ->sortByDesc('leads')->values()->all();

        $stages = ['New', 'Contacted', 'Interested', 'Meeting', 'Proposal', 'Negotiation', 'Won', 'Lost'];

        return [
            'period' => ['start' => $start, 'end' => $end],
            'new_leads' => $cohort->count(),
            'calls' => (int) (clone $calls)->sum('count_change'),
            'detailed_calls' => (clone $calls)->whereIn('call_type', ['Follow-up', 'Initial'])->count(),
            'wins' => $won->count(),
            'losses' => $lost,
            'deal_value' => Money::sum($won->pluck('deal_value')),
            'cohort_conversion' => $cohort->count() ? round($cohort->where('stage', 'Won')->count() / $cohort->count(), 4) : 0,
            'funnel' => collect($stages)->map(fn ($s) => ['stage' => $s, 'count' => $cohort->where('stage', $s)->count()])->all(),
            'by_owner' => $group('owner'),
            'by_source' => $group('source'),
            'by_service' => $group('service'),
            'by_business_type' => $group('business_type'),
            'by_industry' => $group('industry'),
            'by_outcome' => $group('outcome'),
        ];
    }
}
