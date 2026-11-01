<?php

namespace App\Repositories\Eloquent;

use App\Models\Payroll;
use App\Repositories\Contracts\PayrollRepositoryInterface;

class PayrollRepository extends BaseRepository implements PayrollRepositoryInterface
{
    protected string $model = Payroll::class;

    protected array $searchable = ['code'];

    protected function periodFilterColumns(): array
    {
        return ['period'];
    }
}
