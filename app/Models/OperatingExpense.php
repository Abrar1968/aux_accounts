<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperatingExpense extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'operating_expenses';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'accrual_period' => 'date',
            'expense_date' => 'datetime',
            'accrual_month' => 'date',
            'amount' => 'decimal:2',
            'legacy' => 'array',
        ];
    }

    use \Illuminate\Database\Eloquent\SoftDeletes;

    public function payments() { return $this->hasMany(ExpensePayment::class); }

    public function attachments() { return $this->morphMany(Attachment::class, 'attachable'); }
}
