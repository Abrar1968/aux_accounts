<?php

namespace App\Repositories\Eloquent;

use App\Models\BankAccount;
use App\Repositories\Contracts\BankAccountRepositoryInterface;

class BankAccountRepository extends BaseRepository implements BankAccountRepositoryInterface
{
    protected string $model = BankAccount::class;

    protected array $searchable = ['name'];

    protected function periodFilterColumns(): array
    {
        return [];
    }
}
