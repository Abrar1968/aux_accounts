<?php

namespace App\Http\Resources;

use App\Services\Accounting\CustomerPaymentService;
use Illuminate\Http\Request;

class PaymentResource extends RecordResource
{
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + [
            'gross_settlement' => $this->resource->gross_settlement,
            'revenue_type' => app(CustomerPaymentService::class)->revenueType($this->resource),
        ];
    }
}
