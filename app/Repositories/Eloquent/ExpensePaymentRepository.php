<?php

namespace App\Repositories\Eloquent;

use App\Models\ExpensePayment;
use App\Repositories\Contracts\ExpensePaymentRepositoryInterface;

class ExpensePaymentRepository extends BaseRepository implements ExpensePaymentRepositoryInterface
{
    protected string $model = ExpensePayment::class;

    protected array $searchable = ['code','notes','method','account'];

    protected function periodFilterColumns(): array
    {
        return ['paid_on'];
    }
}
