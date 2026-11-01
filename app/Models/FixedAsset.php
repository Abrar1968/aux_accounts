<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\Auditable;

class FixedAsset extends BaseModel
{
    use Auditable;

    protected $table = 'fixed_assets';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'acquisition_date' => DateOnly::class,
            'in_service_date' => DateOnly::class,
            'disposal_date' => DateOnly::class,
            'cash_payment_date' => DateOnly::class,
            'cost' => 'decimal:2',
            'residual_value' => 'decimal:2',
            'opening_accumulated_dep' => 'decimal:2',
            'cash_paid' => 'decimal:2',
        ];
    }

    public function monthlyDepreciation(): float
    {
        return $this->useful_life_months > 0 ? ((float) $this->cost - (float) $this->residual_value) / $this->useful_life_months : 0.0;
    }
}
