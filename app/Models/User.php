<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role_id', 'avatar_path', 'is_active', 'last_login_at'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /** Wildcards supported: "*" (owner), "accounting.*", "crm.leads.*". */
    public function hasPermission(string $permission): bool
    {
        foreach ($this->role?->permissions ?? [] as $granted) {
            if ($granted === '*' || $granted === $permission
                || (str_ends_with($granted, '.*') && str_starts_with($permission, substr($granted, 0, -1)))) {
                return true;
            }
        }

        return false;
    }
}
