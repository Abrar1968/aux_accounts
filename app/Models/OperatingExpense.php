<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OperatingExpense extends Model
{
    use Auditable;

    protected $table = 'operating_expenses';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'accrual_period' => DateOnly::class,
            'expense_date' => 'datetime',
            'accrual_month' => DateOnly::class,
            'amount' => 'decimal:2',
            'legacy' => 'array',
        ];
    }

    use SoftDeletes;

    public function payments()
    {
        return $this->hasMany(ExpensePayment::class);
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
