<?php

namespace App\Http\Resources;

use App\Services\Accounting\ProjectService;
use App\Services\System\BrandingService;
use Illuminate\Http\Request;

class ProjectResource extends RecordResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'type_label' => $this->resource->typeLabel(),
            // URLs instead of storage paths
            'client_signature' => app(BrandingService::class)->signatureUrls($this->resource),
        ], app(ProjectService::class)->metrics($this->resource));
    }
}
