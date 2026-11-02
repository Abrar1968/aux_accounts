<?php

namespace App\Repositories\Eloquent;

use App\Models\BankReconciliation;
use App\Repositories\Contracts\BankReconciliationRepositoryInterface;

class BankReconciliationRepository extends BaseRepository implements BankReconciliationRepositoryInterface
{
    protected string $model = BankReconciliation::class;

    protected array $searchable = ['notes'];

    protected function periodFilterColumns(): array
    {
        return ['statement_date'];
    }
}
