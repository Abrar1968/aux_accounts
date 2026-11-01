<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Liability extends Model
{
    use Auditable;

    protected $table = 'liabilities';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'txn_date' => DateOnly::class,
            'period' => DateOnly::class,
            'increase' => 'decimal:2',
            'decrease' => 'decimal:2',
            'cash_impact' => 'decimal:2',
        ];
    }
}
