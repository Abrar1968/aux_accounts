<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'payrolls';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'period' => 'date',
            'gross_salary' => 'decimal:2',
            'source_net_paid' => 'decimal:2',
            'legacy_referral_snapshot' => 'decimal:2',
            'legacy_unpaid_snapshot' => 'decimal:2',
        ];
    }

    public function employee() { return $this->belongsTo(Employee::class); }

    public function payments() { return $this->hasMany(SalaryPayment::class); }

    public function recoveries() { return $this->hasMany(EmployeeAdvance::class)->where('type', 'Recovery'); }
}
