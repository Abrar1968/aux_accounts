<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvestorOwnership extends Model
{
    protected $table = 'investor_ownerships';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'percent' => 'decimal:4',
        ];
    }

    public function investor() { return $this->belongsTo(Investor::class); }
}
