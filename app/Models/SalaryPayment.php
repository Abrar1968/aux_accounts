<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryPayment extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'salary_payments';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
            'amount' => 'decimal:2',
            'posted_at' => 'datetime',
        ];
    }

    public function payroll() { return $this->belongsTo(Payroll::class); }
}
