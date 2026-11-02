<?php

namespace App\Http\Resources;

use App\Services\Accounting\ExpenseService;
use Illuminate\Http\Request;

class ExpenseResource extends RecordResource
{
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + app(ExpenseService::class)->status($this->resource);
    }
}
