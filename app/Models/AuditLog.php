<?php

namespace App\Models;

class AuditLog extends BaseModel
{
    protected $table = 'audit_logs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public $timestamps = false;

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
