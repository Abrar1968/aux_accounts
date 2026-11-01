<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends BaseModel
{
    use Auditable;

    protected $table = 'leads';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'lead_date' => DateOnly::class,
            'followup_override' => 'datetime',
            'next_followup' => 'datetime',
            'meeting_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'closed_date' => DateOnly::class,
            'quoted_price' => 'decimal:2',
            'deal_value' => 'decimal:2',
        ];
    }

    use SoftDeletes;

    public function calls()
    {
        return $this->hasMany(LeadCall::class);
    }

    public function ownerUser()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
