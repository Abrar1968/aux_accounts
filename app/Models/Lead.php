<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'leads';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'lead_date' => 'date',
            'followup_override' => 'datetime',
            'next_followup' => 'datetime',
            'meeting_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'closed_date' => 'date',
            'quoted_price' => 'decimal:2',
            'deal_value' => 'decimal:2',
        ];
    }

    use \Illuminate\Database\Eloquent\SoftDeletes;

    public function calls() { return $this->hasMany(LeadCall::class); }

    public function ownerUser() { return $this->belongsTo(User::class, 'owner_id'); }

    public function project() { return $this->belongsTo(Project::class); }
}
