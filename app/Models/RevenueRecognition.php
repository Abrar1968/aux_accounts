<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class RevenueRecognition extends Model
{
    use Auditable;

    protected $table = 'revenue_recognitions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'period' => DateOnly::class,
            'recognition_month' => DateOnly::class,
            'opening_progress' => 'decimal:4',
            'ending_progress' => 'decimal:4',
            'contract_value' => 'decimal:2',
            'auto_revenue' => 'decimal:2',
            'override_amount' => 'decimal:2',
            'recognized_amount' => 'decimal:2',
            'siam_eligible' => 'decimal:2',
            'siam_share' => 'decimal:2',
            'is_historical' => 'boolean',
            'auto_generated' => 'boolean',
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
