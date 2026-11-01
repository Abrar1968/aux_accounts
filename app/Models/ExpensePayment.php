<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpensePayment extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'expense_payments';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'paid_on' => \App\Casts\DateOnly::class,
            'amount' => 'decimal:2',
            'posted_at' => 'datetime',
        ];
    }

    public function expense() { return $this->belongsTo(OperatingExpense::class, 'operating_expense_id'); }

    public function cashOut(): float { return $this->origin === 'Withheld fee' ? 0.0 : (float) $this->amount; }
}
