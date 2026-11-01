<?php

namespace Database\Seeders;

use App\Services\Shared\AuditService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** Imported history is not a user edit: auditing is muted while seeding. */
    public function run(): void
    {
        app(AuditService::class)->mute(function () {
            $this->call([
                RoleSeeder::class,
                UserSeeder::class,
                SettingsSeeder::class,
                AccountingWorkbookSeeder::class,
                CrmWorkbookSeeder::class,
            ]);
        });
    }
}
