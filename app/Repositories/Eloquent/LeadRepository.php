<?php

namespace App\Repositories\Eloquent;

use App\Models\Lead;
use App\Repositories\Contracts\LeadRepositoryInterface;

class LeadRepository extends BaseRepository implements LeadRepositoryInterface
{
    protected string $model = Lead::class;

    protected array $searchable = ['code','phone','normalized_phone','notes','business_type'];

    protected function periodFilterColumns(): array
    {
        return ['lead_date'];
    }
}
