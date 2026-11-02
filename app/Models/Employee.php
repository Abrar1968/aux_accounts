<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\Auditable;

class Employee extends BaseModel
{
    use Auditable;

    protected $table = 'employees';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2',
            'joined_on' => DateOnly::class,
            'left_on' => DateOnly::class,
            'is_active' => 'boolean',
        ];
    }

    public function payrolls()
    {
        return $this->hasMany(Payroll::class);
    }

    public function advances()
    {
        return $this->hasMany(EmployeeAdvance::class);
    }
}
