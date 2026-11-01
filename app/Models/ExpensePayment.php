<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class ExpensePayment extends Model
{
    use Auditable;

    protected $table = 'expense_payments';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'paid_on' => DateOnly::class,
            'amount' => 'decimal:2',
            'posted_at' => 'datetime',
        ];
    }

    public function expense()
    {
        return $this->belongsTo(OperatingExpense::class, 'operating_expense_id');
    }

    public function cashOut(): float
    {
        return $this->origin === 'Withheld fee' ? 0.0 : (float) $this->amount;
    }
}
