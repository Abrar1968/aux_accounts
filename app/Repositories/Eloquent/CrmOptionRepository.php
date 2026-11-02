<?php

namespace App\Repositories\Eloquent;

use App\Models\CrmOption;
use App\Repositories\Contracts\CrmOptionRepositoryInterface;

class CrmOptionRepository extends BaseRepository implements CrmOptionRepositoryInterface
{
    protected string $model = CrmOption::class;

    protected array $searchable = ['value'];

    protected array $orderBy = ['sort', 'asc'];
}
