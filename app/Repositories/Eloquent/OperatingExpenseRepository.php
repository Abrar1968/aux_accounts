<?php

namespace App\Repositories\Eloquent;

use App\Models\OperatingExpense;
use App\Repositories\Contracts\OperatingExpenseRepositoryInterface;

class OperatingExpenseRepository extends BaseRepository implements OperatingExpenseRepositoryInterface
{
    protected string $model = OperatingExpense::class;

    protected array $filterable = ['category' => 'category'];

    protected array $searchable = ['code','category','subcategory','description'];

    protected function periodFilterColumns(): array
    {
        return ['accrual_month'];
    }
}
