<?php

namespace App\Repositories\Eloquent;

use App\Models\Liability;
use App\Repositories\Contracts\LiabilityRepositoryInterface;

class LiabilityRepository extends BaseRepository implements LiabilityRepositoryInterface
{
    protected string $model = Liability::class;

    protected array $filterable = ['code' => 'liability_code'];

    protected array $searchable = ['liability_code', 'description', 'counterparty'];

    protected function periodFilterColumns(): array
    {
        return ['period'];
    }
}
