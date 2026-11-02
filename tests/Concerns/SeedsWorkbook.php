<?php

namespace Tests\Concerns;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/** Full real-data seed (accounting + CRM workbooks) and role-based test users. */
trait SeedsWorkbook
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = DatabaseSeeder::class;

    protected function userWithRole(string $role, ?string $name = null): User
    {
        return User::factory()->create([
            'name' => $name ?? ucfirst($role).' User',
            'role_id' => Role::query()->where('name', $role)->value('id'),
            'is_active' => true,
        ]);
    }

    protected function owner(): User
    {
        return $this->userWithRole('owner');
    }
}
