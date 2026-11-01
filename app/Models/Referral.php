<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Referral extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'referrals';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
            'memo_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
        ];
    }

    public function project() { return $this->belongsTo(Project::class); }
}
