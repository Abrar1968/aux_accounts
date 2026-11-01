<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'employees';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2',
            'joined_on' => \App\Casts\DateOnly::class,
            'left_on' => \App\Casts\DateOnly::class,
            'is_active' => 'boolean',
        ];
    }

    public function payrolls() { return $this->hasMany(Payroll::class); }

    public function advances() { return $this->hasMany(EmployeeAdvance::class); }
}
