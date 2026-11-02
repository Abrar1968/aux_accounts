<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\Auditable;

class Budget extends BaseModel
{
    use Auditable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['period' => DateOnly::class, 'amount' => 'decimal:2'];
    }
}
