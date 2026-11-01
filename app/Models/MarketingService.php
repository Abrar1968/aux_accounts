<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class MarketingService extends Model
{
    use Auditable;

    protected $table = 'marketing_services';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'service_month' => DateOnly::class,
            'revenue' => 'decimal:2',
        ];
    }

    public function payments()
    {
        return $this->hasMany(CustomerPayment::class, 'receivable_id')->where('receivable_type', 'marketing');
    }
}
