<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Period extends Model
{
    use Auditable;

    protected $table = 'periods';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'month' => DateOnly::class,
            'closed_at' => 'datetime',
        ];
    }
}
