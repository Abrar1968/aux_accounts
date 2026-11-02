<?php

namespace App\Services\Accounting;

use App\Exceptions\Domain\DomainException;
use App\Models\Project;
use App\Models\Referral;
use App\Repositories\Contracts\ReferralRepositoryInterface;
use App\Services\Accounting\Engine\ReferralTierCalculator;
use App\Services\Shared\PeriodService;
use App\Services\Shared\SequenceService;
use App\Services\Shared\SettingsService;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Referral_Tracker: one row per project referrer (shared referrals split equally); expense only when paid (policy "When Paid").
 */
class ReferralService
{
    public function __construct(
        private readonly ReferralRepositoryInterface $repo,
        private readonly SettingsService $settings,
        private readonly SequenceService $sequences,
        private readonly PeriodService $periods,
    ) {}

    public function memoFor(float $totalValue): float
    {
        return (new ReferralTierCalculator($this->settings->get('referral.tiers')))->memo($totalValue);
    }

    /**
     * Referral_Tracker sync. One row per referrer; the tier memo for the project's total value is
     * split equally (to the paisa — any remainder goes to the first referrers so shares sum exactly).
     * A referrer who was already paid cannot be removed; unpaid rows of removed referrers are deleted.
     *
     * @return Collection<int, Referral>
     */
    public function syncForProject(Project $project): Collection
    {
        $names = $project->referrerNames();
        $rows = $this->repo->query()->where('project_id', $project->id)->orderBy('id')->get();
        if (! $names) {
            return $rows; // referrer cleared: keep paid history; memo becomes informational
        }
        $byName = $rows->keyBy(fn (Referral $r) => mb_strtolower($r->referrer));
        $removed = $rows->reject(fn (Referral $r) => in_array(mb_strtolower($r->referrer), array_map('mb_strtolower', $names), true));
        if ($paid = $removed->first(fn (Referral $r) => (float) $r->paid_amount > 0)) {
            throw new DomainException("{$paid->referrer} has already been paid on {$paid->code}; reverse that payment before removing them as a referrer.");
        }
        $removed->each(fn (Referral $r) => $r->delete());

        $shares = $this->split($this->memoFor($project->total_value), count($names));
        foreach ($names as $i => $name) {
            $attrs = ['referrer' => $name, 'memo_amount' => $shares[$i]];
            ($ref = $byName->get(mb_strtolower($name)))
                ? $this->repo->update($ref, $attrs)
                : $this->repo->create($attrs + [
                    'project_id' => $project->id,
                    'code' => 'REF-'.str_pad((string) $this->sequences->next('REF'), 3, '0', STR_PAD_LEFT),
                ]);
        }

        return $this->repo->query()->where('project_id', $project->id)->orderBy('id')->get();
    }

    /** Equal shares in paisa; remainder paisa to the first shares. @return list<float> */
    public function split(float $total, int $n): array
    {
        $paisa = Money::paisa($total);
        $base = intdiv($paisa, max(1, $n));
        $rem = $paisa - $base * $n;

        return array_map(fn ($i) => round(($base + ($i < $rem ? 1 : 0)) / 100, 2), range(0, $n - 1));
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
