<?php

namespace App\Repositories\Eloquent;

use App\Models\EmployeeAdvance;
use App\Repositories\Contracts\EmployeeAdvanceRepositoryInterface;

class EmployeeAdvanceRepository extends BaseRepository implements EmployeeAdvanceRepositoryInterface
{
    protected string $model = EmployeeAdvance::class;

    protected array $searchable = ['code','notes'];

    protected function periodFilterColumns(): array
    {
        return ['txn_date'];
    }
}
