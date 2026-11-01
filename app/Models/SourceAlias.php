<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SourceAlias extends Model
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
