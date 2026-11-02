<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $role = fn (string $n) => Role::query()->where('name', $n)->value('id');
        $password = env('ADMIN_PASSWORD', 'ChangeMe!2026');

        User::query()->updateOrCreate(['email' => env('ADMIN_EMAIL', 'admin@auxtech.local')], [
            'name' => env('ADMIN_NAME', 'Abrar'), 'password' => $password, 'role_id' => $role('owner'), 'is_active' => true,
            'email_verified_at' => now(),
        ]);
        // CRM owners from the Lead CRM "Owner Options" list.
        foreach (['Abrar' => 'abrar@auxtech.local', 'Rizwan' => 'rizwan@auxtech.local'] as $name => $email) {
            User::query()->updateOrCreate(['email' => $email], [
                'name' => $name, 'password' => $password, 'role_id' => $role('caller'), 'is_active' => true, 'email_verified_at' => now(),
            ]);
        }
    }
}
