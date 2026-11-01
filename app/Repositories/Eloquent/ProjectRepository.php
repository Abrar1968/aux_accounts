<?php

namespace App\Repositories\Eloquent;

use App\Models\Project;
use App\Repositories\Contracts\ProjectRepositoryInterface;

class ProjectRepository extends BaseRepository implements ProjectRepositoryInterface
{
    protected string $model = Project::class;

    protected array $searchable = ['code','name','customer'];

    protected function periodFilterColumns(): array
    {
        return ['source_month'];
    }
}
