<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Liability extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'liabilities';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'txn_date' => \App\Casts\DateOnly::class,
            'period' => \App\Casts\DateOnly::class,
            'increase' => 'decimal:2',
            'decrease' => 'decimal:2',
            'cash_impact' => 'decimal:2',
        ];
    }

}
