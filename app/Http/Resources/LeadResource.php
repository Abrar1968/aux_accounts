<?php

namespace App\Http\Resources;

use App\Services\Crm\LeadService;
use Illuminate\Http\Request;

class LeadResource extends RecordResource
{
    public function toArray(Request $request): array
    {
        return app(LeadService::class)->present($this->resource);
    }
}
