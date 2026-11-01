<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CapitalTransaction extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'capital_transactions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'txn_date' => 'date',
            'period' => 'date',
            'cash_impact' => 'decimal:2',
            'equity_impact' => 'decimal:2',
        ];
    }

    public function investor() { return $this->belongsTo(Investor::class); }
}
