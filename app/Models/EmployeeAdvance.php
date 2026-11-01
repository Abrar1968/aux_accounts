<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeAdvance extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'employee_advances';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'txn_date' => \App\Casts\DateOnly::class,
            'related_salary_period' => \App\Casts\DateOnly::class,
            'amount' => 'decimal:2',
        ];
    }

    public function employee() { return $this->belongsTo(Employee::class); }

    public function payroll() { return $this->belongsTo(Payroll::class); }
}
