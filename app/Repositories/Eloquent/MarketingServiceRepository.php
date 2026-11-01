<?php

namespace App\Repositories\Eloquent;

use App\Models\MarketingService;
use App\Repositories\Contracts\MarketingServiceRepositoryInterface;

class MarketingServiceRepository extends BaseRepository implements MarketingServiceRepositoryInterface
{
    protected string $model = MarketingService::class;

    protected array $searchable = ['code','client'];

    protected function periodFilterColumns(): array
    {
        return ['service_month'];
    }
}
