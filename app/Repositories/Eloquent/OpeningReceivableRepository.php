<?php

namespace App\Repositories\Eloquent;

use App\Models\OpeningReceivable;
use App\Repositories\Contracts\OpeningReceivableRepositoryInterface;

class OpeningReceivableRepository extends BaseRepository implements OpeningReceivableRepositoryInterface
{
    protected string $model = OpeningReceivable::class;

    protected array $searchable = ['code','customer'];

    protected function periodFilterColumns(): array
    {
        return [];
    }
}
