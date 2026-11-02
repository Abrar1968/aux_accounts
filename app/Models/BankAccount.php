<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\Auditable;

class BankAccount extends BaseModel
{
    use Auditable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['aliases' => 'array', 'opening_balance' => 'decimal:2', 'opening_date' => DateOnly::class, 'is_active' => 'boolean'];
    }

    public function reconciliations()
    {
        return $this->hasMany(BankReconciliation::class);
    }
}
