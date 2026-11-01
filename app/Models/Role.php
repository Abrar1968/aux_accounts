<?php

namespace App\Models;

class Role extends BaseModel
{
    protected $table = 'roles';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
        ];
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
