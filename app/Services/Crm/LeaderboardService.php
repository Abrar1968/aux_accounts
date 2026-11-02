<?php

namespace App\Services\Crm;

use App\Models\CallerTarget;
use App\Repositories\Contracts\CallerTargetRepositoryInterface;
use App\Repositories\Contracts\CrmOptionRepositoryInterface;
use App\Repositories\Contracts\LeadCallRepositoryInterface;
use App\Repositories\Contracts\LeadRepositoryInterface;
use App\Support\Period;

/**
 * Caller targets & leaderboard (FR-CRM-14, new). Actuals per owner for a month:
 * calls = Σ saved call count changes by caller; leads = leads created (lead date) by owner;
 * wins = Won leads closed in the month by owner. Score = mean achievement of the set targets.
 */
class LeaderboardService
{
    public function __construct(
        private readonly CallerTargetRepositoryInterface $targets,
        private readonly LeadRepositoryInterface $leads,
        private readonly LeadCallRepositoryInterface $calls,
        private readonly CrmOptionRepositoryInterface $options,
    ) {}

    public function setTarget(string $owner, Period $p, array $t): CallerTarget
    {
        return $this->targets->query()->updateOrCreate(['owner' => $owner, 'period' => $p->date()], [
            'calls_target' => (int) ($t['calls_target'] ?? 0), 'leads_target' => (int) ($t['leads_target'] ?? 0), 'wins_target' => (int) ($t['wins_target'] ?? 0),
        ]);
    }

    public function board(Period $p): array
    {
        [$from, $cut] = [$p->date(), $p->cut()];
        $calls = $this->calls->query()->where('status', 'Saved')->where('recorded_at', '>=', $from)->where('recorded_at', '<', $cut)
            ->selectRaw('caller, SUM(count_change) n')->groupBy('caller')->pluck('n', 'caller');
        $leads = $this->leads->query()->where('lead_date', '>=', $from)->where('lead_date', '<', $cut)
            ->selectRaw('owner, COUNT(*) n')->groupBy('owner')->pluck('n', 'owner');
        $wins = $this->leads->query()->where('stage', 'Won')->where('closed_date', '>=', $from)->where('closed_date', '<', $cut)
            ->selectRaw('owner, COUNT(*) n, SUM(deal_value) v')->groupBy('owner')->get()->keyBy('owner');
        $targets = $this->targets->query()->where('period', $from)->get()->keyBy('owner');
        $owners = collect($this->options->query()->where('type', 'owner')->where('is_active', true)->pluck('value'))
            ->merge($calls->keys())->merge($leads->keys())->merge($targets->keys())
            ->filter(fn ($o) => $o && $o !== 'Unassigned')->unique()->values();

        $rows = $owners->map(function ($o) use ($calls, $leads, $wins, $targets) {
            $t = $targets[$o] ?? null;
            $actual = ['calls' => (int) ($calls[$o] ?? 0), 'leads' => (int) ($leads[$o] ?? 0), 'wins' => (int) ($wins[$o]->n ?? 0)];
            $ach = [];
            foreach (['calls', 'leads', 'wins'] as $k) {
                $target = (int) ($t?->{$k.'_target'} ?? 0);
                $ach[$k] = $target > 0 ? round($actual[$k] / $target, 4) : null;
            }
            $set = array_filter($ach, fn ($v) => $v !== null);

            return ['owner' => $o, 'actual' => $actual, 'deal_value' => (float) ($wins[$o]->v ?? 0),
                'targets' => ['calls' => (int) ($t?->calls_target ?? 0), 'leads' => (int) ($t?->leads_target ?? 0), 'wins' => (int) ($t?->wins_target ?? 0)],
                'achievement' => $ach, 'score' => $set ? round(array_sum(array_map(fn ($v) => min($v, 1.5), $set)) / count($set), 4) : null];
        })->sortByDesc(fn ($r) => [$r['score'] ?? -1, $r['actual']['calls']])->values();

        return ['period' => $p->key(), 'rows' => $rows->all()];
    }
}
