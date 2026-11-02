<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\Auditable;

class RecurringExpense extends BaseModel
{
    use Auditable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'start_month' => DateOnly::class, 'end_month' => DateOnly::class, 'is_active' => 'boolean'];
    }

    public function bills()
    {
        return $this->hasMany(OperatingExpense::class);
    }
}
