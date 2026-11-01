<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnershipRevenue extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'partnership_revenues';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'earned_month' => \App\Casts\DateOnly::class,
            'share_percent' => 'decimal:4',
            'gross_sales' => 'decimal:2',
            'direct_amount' => 'decimal:2',
            'recognized_revenue' => 'decimal:2',
        ];
    }

    public function assignment() { return $this->belongsTo(PartnershipAssignment::class, 'assignment_id'); }
}
