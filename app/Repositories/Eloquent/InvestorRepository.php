<?php

namespace App\Repositories\Eloquent;

use App\Models\Investor;
use App\Repositories\Contracts\InvestorRepositoryInterface;

class InvestorRepository extends BaseRepository implements InvestorRepositoryInterface
{
    protected string $model = Investor::class;

    protected array $searchable = ['name'];

    protected function periodFilterColumns(): array
    {
        return [];
    }
}
