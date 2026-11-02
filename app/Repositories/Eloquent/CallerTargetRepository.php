<?php

namespace App\Repositories\Eloquent;

use App\Models\CallerTarget;
use App\Repositories\Contracts\CallerTargetRepositoryInterface;

class CallerTargetRepository extends BaseRepository implements CallerTargetRepositoryInterface
{
    protected string $model = CallerTarget::class;

    protected array $searchable = ['owner'];

    protected function periodFilterColumns(): array
    {
        return ['period'];
    }
}
