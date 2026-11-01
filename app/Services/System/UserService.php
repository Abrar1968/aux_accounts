<?php

namespace App\Services\System;

use App\Exceptions\Domain\DomainException;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function list()
    {
        return User::query()->with('role')->orderBy('name')->get();
    }

    public function create(array $data): User
    {
        return User::query()->create([
            'name' => $data['name'], 'email' => mb_strtolower($data['email']), 'password' => Hash::make($data['password']),
            'role_id' => $data['role_id'], 'is_active' => $data['is_active'] ?? true, 'email_verified_at' => now(),
        ]);
    }

    public function update(User $user, array $data, User $actor): User
    {
        if ($user->id === $actor->id && isset($data['is_active']) && ! $data['is_active']) {
            throw new DomainException('You cannot deactivate your own account.');
        }
        $owner = Role::query()->where('name', 'owner')->value('id');
        if ($user->role_id === $owner && isset($data['role_id']) && (int) $data['role_id'] !== $owner
            && User::query()->where('role_id', $owner)->where('is_active', true)->count() <= 1) {
            throw new DomainException('At least one active owner is required.');
        }
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        $user->fill($data)->save();

        return $user;
    }

    public function roles()
    {
        return Role::query()->orderBy('id')->get();
    }

    public function updateRole(Role $role, array $permissions): Role
    {
        if ($role->name === 'owner') {
            throw new DomainException('The owner role always has every permission.');
        }
        $role->update(['permissions' => array_values(array_unique($permissions))]);

        return $role;
    }

    public function recordLogin(User $user): void
    {
        $user->forceFill(['last_login_at' => now()])->save();
    }
}
