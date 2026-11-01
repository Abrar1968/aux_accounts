<?php

namespace App\Http\Resources;

use App\Casts\DateOnly;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Default shaper for simple ledger rows: model attributes with casts applied,
 * money as floats, dates as Y-m-d. Hidden attributes stay hidden.
 */
class RecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $out = [];
        foreach ($this->resource->attributesToArray() as $k => $v) {
            $cast = $this->resource->getCasts()[$k] ?? null;
            $out[$k] = match (true) {
                $v === null => null,
                is_string($cast) && str_starts_with($cast, 'decimal') => (float) $v,
                $cast === DateOnly::class => substr((string) $v, 0, 10),
                default => $v,
            };
        }

        return $out;
    }
}
