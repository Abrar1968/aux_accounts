<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FixedAsset extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'fixed_assets';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'acquisition_date' => \App\Casts\DateOnly::class,
            'in_service_date' => \App\Casts\DateOnly::class,
            'disposal_date' => \App\Casts\DateOnly::class,
            'cash_payment_date' => \App\Casts\DateOnly::class,
            'cost' => 'decimal:2',
            'residual_value' => 'decimal:2',
            'opening_accumulated_dep' => 'decimal:2',
            'cash_paid' => 'decimal:2',
        ];
    }

    public function monthlyDepreciation(): float { return $this->useful_life_months > 0 ? ((float) $this->cost - (float) $this->residual_value) / $this->useful_life_months : 0.0; }
}
