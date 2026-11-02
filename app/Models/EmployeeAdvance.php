<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\Auditable;

class EmployeeAdvance extends BaseModel
{
    use Auditable;

    protected $table = 'employee_advances';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'txn_date' => DateOnly::class,
            'related_salary_period' => DateOnly::class,
            'amount' => 'decimal:2',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }
}
