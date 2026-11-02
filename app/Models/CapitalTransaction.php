<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\Auditable;

class CapitalTransaction extends BaseModel
{
    use Auditable;

    protected $table = 'capital_transactions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'txn_date' => DateOnly::class,
            'period' => DateOnly::class,
            'cash_impact' => 'decimal:2',
            'equity_impact' => 'decimal:2',
        ];
    }

    public function investor()
    {
        return $this->belongsTo(Investor::class);
    }
}
