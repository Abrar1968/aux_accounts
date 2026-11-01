<?php

namespace App\Repositories\Eloquent;

use App\Models\FixedAsset;
use App\Repositories\Contracts\FixedAssetRepositoryInterface;

class FixedAssetRepository extends BaseRepository implements FixedAssetRepositoryInterface
{
    protected string $model = FixedAsset::class;

    protected array $searchable = ['code','name','category'];

    protected function periodFilterColumns(): array
    {
        return [];
    }
}
