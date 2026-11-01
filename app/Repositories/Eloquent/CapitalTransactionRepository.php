<?php

namespace App\Repositories\Eloquent;

use App\Models\CapitalTransaction;
use App\Repositories\Contracts\CapitalTransactionRepositoryInterface;

class CapitalTransactionRepository extends BaseRepository implements CapitalTransactionRepositoryInterface
{
    protected string $model = CapitalTransaction::class;

    protected array $searchable = ['type','notes'];

    protected function periodFilterColumns(): array
    {
        return ['period'];
    }
}
