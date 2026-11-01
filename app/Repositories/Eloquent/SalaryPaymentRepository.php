<?php

namespace App\Repositories\Eloquent;

use App\Models\SalaryPayment;
use App\Repositories\Contracts\SalaryPaymentRepositoryInterface;

class SalaryPaymentRepository extends BaseRepository implements SalaryPaymentRepositoryInterface
{
    protected string $model = SalaryPayment::class;

    protected array $searchable = ['code','notes'];

    protected function periodFilterColumns(): array
    {
        return ['paid_on'];
    }
}
