<?php

namespace App\Repositories\Eloquent;

use App\Models\Budget;
use App\Repositories\Contracts\BudgetRepositoryInterface;

class BudgetRepository extends BaseRepository implements BudgetRepositoryInterface
{
    protected string $model = Budget::class;

    protected array $searchable = ['line_key'];

    protected function periodFilterColumns(): array
    {
        return ['period'];
    }
}
