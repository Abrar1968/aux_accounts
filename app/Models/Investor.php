<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Investor extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'investors';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_equity' => 'boolean',
            'capital_commitment' => 'decimal:2',
        ];
    }

    public function ownerships() { return $this->hasMany(InvestorOwnership::class); }

    public function transactions() { return $this->hasMany(CapitalTransaction::class); }
}
