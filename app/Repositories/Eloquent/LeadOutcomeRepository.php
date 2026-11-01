<?php

namespace App\Repositories\Eloquent;

use App\Models\LeadOutcome;
use App\Repositories\Contracts\LeadOutcomeRepositoryInterface;

class LeadOutcomeRepository extends BaseRepository implements LeadOutcomeRepositoryInterface
{
    protected string $model = LeadOutcome::class;

    protected array $searchable = ['name'];

    protected array $orderBy = ['sort', 'asc'];
}
