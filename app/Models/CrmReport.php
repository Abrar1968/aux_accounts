<?php

namespace App\Models;

use App\Casts\DateOnly;

class CrmReport extends BaseModel
{
    protected $table = 'crm_reports';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'period_start' => DateOnly::class,
            'period_end' => DateOnly::class,
            'payload' => 'array',
        ];
    }
}
