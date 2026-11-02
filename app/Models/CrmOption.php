<?php

namespace App\Models;

class CrmOption extends BaseModel
{
    protected $table = 'crm_options';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
