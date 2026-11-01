<?php

namespace App\Models;

use App\Models\Concerns\Auditable;

class Setting extends BaseModel
{
    use Auditable;

    protected $table = 'settings';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'editable' => 'boolean',
        ];
    }
}
