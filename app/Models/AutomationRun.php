<?php

namespace App\Models;

class AutomationRun extends BaseModel
{
    protected $table = 'automation_runs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public $timestamps = false;
}
