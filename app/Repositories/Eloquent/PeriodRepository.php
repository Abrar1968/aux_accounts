<?php

namespace App\Repositories\Eloquent;

use App\Models\Period;
use App\Repositories\Contracts\PeriodRepositoryInterface;

class PeriodRepository extends BaseRepository implements PeriodRepositoryInterface
{
    protected string $model = Period::class;

    protected array $searchable = ['month'];

    protected function periodFilterColumns(): array
    {
        return [];
    }
}
