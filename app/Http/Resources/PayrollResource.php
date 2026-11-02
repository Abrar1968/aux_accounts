<?php

namespace App\Http\Resources;

use App\Services\Accounting\PayrollService;
use Illuminate\Http\Request;

class PayrollResource extends RecordResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), ['employee' => $this->resource->employee?->name], app(PayrollService::class)->summary($this->resource));
    }
}
