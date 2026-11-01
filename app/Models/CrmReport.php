<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmReport extends Model
{
    protected $table = 'crm_reports';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'payload' => 'array',
        ];
    }

}
