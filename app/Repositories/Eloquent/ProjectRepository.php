<?php

namespace App\Repositories\Eloquent;

use App\Models\Project;
use App\Repositories\Contracts\ProjectRepositoryInterface;

class ProjectRepository extends BaseRepository implements ProjectRepositoryInterface
{
    protected string $model = Project::class;

    protected array $filterable = ['kind' => 'kind'];

    protected array $searchable = ['code', 'name', 'customer', 'client_email', 'client_phone', 'domain', 'referrer', 'project_manager', 'notes'];

    protected function periodFilterColumns(): array
    {
        return ['source_month'];
    }
}
