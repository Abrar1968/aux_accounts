<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use \App\Models\Concerns\Auditable;

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
