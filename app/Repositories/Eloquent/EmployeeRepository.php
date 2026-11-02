<?php

namespace App\Repositories\Eloquent;

use App\Models\Employee;
use App\Repositories\Contracts\EmployeeRepositoryInterface;

class EmployeeRepository extends BaseRepository implements EmployeeRepositoryInterface
{
    protected string $model = Employee::class;

    protected array $searchable = ['name', 'designation'];

    protected function periodFilterColumns(): array
    {
        return [];
    }
}
