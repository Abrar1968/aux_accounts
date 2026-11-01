<?php

namespace App\Models;

use App\Models\Concerns\Auditable;

class Investor extends BaseModel
{
    use Auditable;

    protected $table = 'investors';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_equity' => 'boolean',
            'capital_commitment' => 'decimal:2',
        ];
    }

    public function ownerships()
    {
        return $this->hasMany(InvestorOwnership::class);
    }

    public function transactions()
    {
        return $this->hasMany(CapitalTransaction::class);
    }
}
