<?php

namespace App\Services\Accounting;

use App\Models\FixedAsset;
use App\Repositories\Contracts\FixedAssetRepositoryInterface;
use App\Services\Accounting\Engine\DepreciationCalculator;
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

        return $this->repo->create($data + ['code' => 'AST-'.str_pad((string) $this->sequences->next('AST', 1), 3, '0', STR_PAD_LEFT)]);
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
}
