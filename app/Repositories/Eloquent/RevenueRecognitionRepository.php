<?php

namespace App\Repositories\Eloquent;

use App\Models\RevenueRecognition;
use App\Repositories\Contracts\RevenueRecognitionRepositoryInterface;

class RevenueRecognitionRepository extends BaseRepository implements RevenueRecognitionRepositoryInterface
{
    protected string $model = RevenueRecognition::class;

    protected array $searchable = ['code', 'notes'];

    protected function periodFilterColumns(): array
    {
        return ['recognition_month'];
    }
}
