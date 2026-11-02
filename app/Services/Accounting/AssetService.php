<?php

namespace App\Services\Accounting;

use App\Models\FixedAsset;
use App\Repositories\Contracts\FixedAssetRepositoryInterface;
use App\Services\Accounting\Engine\DepreciationCalculator;
use App\Services\Shared\AuditService;
use App\Services\Shared\PeriodService;
use App\Services\Shared\SequenceService;
use App\Support\Period;

/** Assets register + Asset_Schedule. */
class AssetService
{
    public function __construct(
        private readonly FixedAssetRepositoryInterface $repo,
        private readonly DepreciationCalculator $calculator,
        private readonly PeriodService $periods,
        private readonly SequenceService $sequences,
    ) {}

    public function create(array $data): FixedAsset
    {
        $this->periods->assertOpen($data['acquisition_date'] ?? null, $data['cash_payment_date'] ?? null);
        foreach (['residual_value', 'opening_accumulated_dep', 'cash_paid'] as $k) {
            $data[$k] = (float) ($data[$k] ?? 0); // blank optional amounts are zero, never NULL
        }

        return $this->repo->create($data + ['code' => 'AST-'.str_pad((string) $this->sequences->next('AST', 1), 3, '0', STR_PAD_LEFT)]);
    }

    /**
     * Workbook 2.10: an asset only enters the balance sheet funded by direct cash (Assets!cash paid)
     * or a financing liability (e.g. LIA-AC). Returns the carrying amount not covered by cash so the
     * UI can remind the user to post the matching liability; otherwise the balance check drifts.
     */
    public function unfundedAmount(FixedAsset $a): float
    {
        return max(0.0, round((float) $a->cost - (float) $a->opening_accumulated_dep - (float) $a->cash_paid, 2));
    }

    public function update(FixedAsset $a, array $data): FixedAsset
    {
        $this->periods->assertOpen($data['disposal_date'] ?? null, $data['cash_payment_date'] ?? null);

        return $this->repo->update($a, $data);
    }

    /** @return list<array<string,mixed>> */
    public function schedule(Period $through): array
    {
        $periods = Period::range($this->periods->modelStart(), $through);

        return $this->repo->all()->map(fn (FixedAsset $a) => [
            'asset' => $a->toArray() + ['monthly_depreciation' => round($a->monthlyDepreciation(), 2),
                'status' => $a->disposal_date ? 'Disposed' : 'Active'],
            'months' => $this->calculator->schedule($a, $periods),
        ])->values()->all();
    }

    public function delete(FixedAsset $a, string $reason): void
    {
        $this->periods->assertOpen($a->acquisition_date, $a->cash_payment_date);
        app(AuditService::class)->record('deleted', $a, $a->toArray(), null, $reason);
        $a->delete();
    }
}
