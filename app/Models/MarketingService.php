<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingService extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'marketing_services';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'service_month' => \App\Casts\DateOnly::class,
            'revenue' => 'decimal:2',
        ];
    }

    public function payments() { return $this->hasMany(CustomerPayment::class, 'receivable_id')->where('receivable_type', 'marketing'); }
}
