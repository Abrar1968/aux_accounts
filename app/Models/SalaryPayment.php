<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\Auditable;

class SalaryPayment extends BaseModel
{
    use Auditable;

    protected $table = 'salary_payments';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'paid_on' => DateOnly::class,
            'amount' => 'decimal:2',
            'posted_at' => 'datetime',
        ];
    }

    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }
}
