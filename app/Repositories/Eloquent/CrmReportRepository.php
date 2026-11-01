<?php

namespace App\Repositories\Eloquent;

use App\Models\CrmReport;
use App\Repositories\Contracts\CrmReportRepositoryInterface;

class CrmReportRepository extends BaseRepository implements CrmReportRepositoryInterface
{
    protected string $model = CrmReport::class;

    protected array $searchable = ['type'];

    protected function periodFilterColumns(): array
    {
        return ['period_start'];
    }
}
