<?php

namespace App\Models;

use App\Casts\DateOnly;

class InvestorOwnership extends BaseModel
{
    protected $table = 'investor_ownerships';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'effective_from' => DateOnly::class,
            'percent' => 'decimal:4',
        ];
    }

    public function investor()
    {
        return $this->belongsTo(Investor::class);
    }
}
