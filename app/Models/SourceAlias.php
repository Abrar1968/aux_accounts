<?php

namespace App\Models;

class SourceAlias extends BaseModel
{
    protected $table = 'source_aliases';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'confirmed' => 'boolean',
        ];
    }
}
