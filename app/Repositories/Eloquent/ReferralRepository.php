<?php

namespace App\Repositories\Eloquent;

use App\Models\Referral;
use App\Repositories\Contracts\ReferralRepositoryInterface;

class ReferralRepository extends BaseRepository implements ReferralRepositoryInterface
{
    protected string $model = Referral::class;

    protected array $searchable = ['code', 'referrer'];

    protected function periodFilterColumns(): array
    {
        return [];
    }
}
