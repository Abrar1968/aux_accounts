<?php

namespace App\Models;

class NotificationPreference extends BaseModel
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['in_app' => 'boolean', 'push' => 'boolean', 'email' => 'boolean'];
    }
}
