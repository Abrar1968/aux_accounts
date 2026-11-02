<?php

namespace App\Repositories\Eloquent;

use App\Models\RecurringExpense;
use App\Repositories\Contracts\RecurringExpenseRepositoryInterface;

class RecurringExpenseRepository extends BaseRepository implements RecurringExpenseRepositoryInterface
{
    protected string $model = RecurringExpense::class;

    protected array $searchable = ['name'];

    protected function periodFilterColumns(): array
    {
        return [];
    }
}
