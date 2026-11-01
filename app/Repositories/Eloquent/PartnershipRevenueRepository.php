<?php

namespace App\Repositories\Eloquent;

use App\Models\PartnershipRevenue;
use App\Repositories\Contracts\PartnershipRevenueRepositoryInterface;

class PartnershipRevenueRepository extends BaseRepository implements PartnershipRevenueRepositoryInterface
{
    protected string $model = PartnershipRevenue::class;

    protected array $searchable = ['code'];

    protected function periodFilterColumns(): array
    {
        return ['earned_month'];
    }
}
