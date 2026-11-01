<?php

namespace App\Repositories\Eloquent;

use App\Models\LeadCall;
use App\Repositories\Contracts\LeadCallRepositoryInterface;

class LeadCallRepository extends BaseRepository implements LeadCallRepositoryInterface
{
    protected string $model = LeadCall::class;

    protected array $searchable = ['notes', 'caller'];

    protected function periodFilterColumns(): array
    {
        return ['recorded_at'];
    }
}
