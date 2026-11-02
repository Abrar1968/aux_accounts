<?php

namespace App\Models;

class LeadCall extends BaseModel
{
    protected $table = 'lead_calls';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'next_followup' => 'datetime',
        ];
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }
}
