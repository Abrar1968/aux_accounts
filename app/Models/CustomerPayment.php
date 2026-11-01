<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class CustomerPayment extends Model
{
    use Auditable;

    protected $table = 'customer_payments';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'period' => DateOnly::class,
            'cash_received' => 'decimal:2',
            'processing_fee' => 'decimal:2',
        ];
    }

    public function getGrossSettlementAttribute(): float
    {
        return round((float) $this->cash_received + (float) $this->processing_fee, 2);
    }
}
