<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\Auditable;

class Referral extends BaseModel
{
    use Auditable;

    protected $table = 'referrals';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'paid_on' => DateOnly::class,
            'memo_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
