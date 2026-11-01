<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;

class CrmReport extends Model
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
