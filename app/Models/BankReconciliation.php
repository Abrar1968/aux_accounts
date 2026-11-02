<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\Auditable;

class BankReconciliation extends BaseModel
{
    use Auditable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['statement_date' => DateOnly::class, 'statement_balance' => 'decimal:2', 'book_balance' => 'decimal:2', 'difference' => 'decimal:2'];
    }

    public function account()
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }
}
