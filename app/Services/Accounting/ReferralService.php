<?php

namespace App\Services\Accounting;

use App\Models\Project;
use App\Models\Referral;
use App\Repositories\Contracts\ReferralRepositoryInterface;
use App\Services\Accounting\Engine\ReferralTierCalculator;
use App\Services\Shared\PeriodService;
use App\Services\Shared\SequenceService;
use App\Services\Shared\SettingsService;

/**
 * Referral_Tracker: one row per project with a referrer; expense only when paid (policy "When Paid").
 */
class ReferralService
{
    public function __construct(
        private readonly ReferralRepositoryInterface $repo,
        private readonly SettingsService $settings,
        private readonly SequenceService $sequences,
        private readonly PeriodService $periods,
    ) {
    }

    public function memoFor(float $totalValue): float
    {
        return (new ReferralTierCalculator($this->settings->get('referral.tiers')))->memo($totalValue);
    }

    public function syncForProject(Project $project): ?Referral
    {
        $ref = $this->repo->query()->where('project_id', $project->id)->first();
        if (! $project->referrer) {
            return $ref; // keep paid history; memo becomes informational
        }
        $attrs = ['referrer' => $project->referrer, 'memo_amount' => $this->memoFor($project->total_value)];
        if ($ref) {
            return $this->repo->update($ref, $attrs);
        }

        return $this->repo->create($attrs + [
            'project_id' => $project->id,
            'code' => 'REF-'.str_pad((string) $this->sequences->next('REF'), 3, '0', STR_PAD_LEFT),
        ]);
    }

    public function recordPayment(Referral $ref, string $paidOn, float $amount, ?string $notes = null): Referral
    {
        $this->periods->assertOpen($paidOn, $ref->paid_on);

        return $this->repo->update($ref, ['paid_on' => $paidOn, 'paid_amount' => $amount, 'notes' => $notes ?? $ref->notes]);
    }

    public function status(Referral $r): string
    {
        $paid = (float) $r->paid_amount;
        $memo = (float) $r->memo_amount;

        return match (true) {
            $paid <= 0 => 'Unpaid',
            $r->paid_on === null => 'Payment date required',
            $paid + 0.01 < $memo => 'Partially Paid',
            $paid > $memo + 0.01 => 'Overpaid',
            default => 'Paid',
        };
    }
}
