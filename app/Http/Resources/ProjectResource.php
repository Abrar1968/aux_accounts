<?php

namespace App\Http\Resources;

use App\Services\Accounting\ProjectService;
use Illuminate\Http\Request;

class ProjectResource extends RecordResource
{
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + ['type_label' => $this->resource->typeLabel()] + app(ProjectService::class)->metrics($this->resource);
    }
}
