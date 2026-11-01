<?php

namespace App\Repositories\Eloquent;

use App\Models\CustomerPayment;
use App\Repositories\Contracts\CustomerPaymentRepositoryInterface;

class CustomerPaymentRepository extends BaseRepository implements CustomerPaymentRepositoryInterface
{
    protected string $model = CustomerPayment::class;

    protected array $filterable = ['type' => 'receivable_type'];

    protected array $searchable = ['code', 'source_name', 'canonical_name', 'revenue_code'];

    protected function periodFilterColumns(): array
    {
        return ['paid_at'];
    }
}
