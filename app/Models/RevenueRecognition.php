<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevenueRecognition extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'revenue_recognitions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'period' => 'date',
            'recognition_month' => 'date',
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

    public function project() { return $this->belongsTo(Project::class); }
}
