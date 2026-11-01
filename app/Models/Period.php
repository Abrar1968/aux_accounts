<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Period extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'periods';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'month' => \App\Casts\DateOnly::class,
            'closed_at' => 'datetime',
        ];
    }

}
