<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;

/** docs/05 §4 roles; wildcard shorthands below are stored as explicit permission lists (App\Support\PermissionCatalog). */
class RoleSeeder extends Seeder
{
    public const ROLES = [
        'owner' => ['Owner', ['*']],
        'accountant' => ['Accountant', ['dashboard.view', 'accounting.projects.*', 'accounting.payments.*', 'accounting.expenses.*',
            'accounting.payroll.*', 'accounting.ledger.*', 'accounting.reports.*', 'accounting.banking.*', 'accounting.budgets.*', 'accounting.periods.manage', 'attachments.*', 'audit.view', 'email.send']],
        'sales_manager' => ['Sales Manager', ['dashboard.view', 'crm.*', 'accounting.reports.view', 'accounting.projects.view', 'accounting.projects.create', 'attachments.*', 'email.send']],
        'caller' => ['Caller', ['crm.leads.view', 'crm.leads.create', 'crm.leads.update', 'crm.calls.create', 'crm.reports.view']],
        'viewer' => ['Viewer / Investor', ['dashboard.view', 'accounting.reports.view', 'accounting.ledger.view']],
    ];

    public function run(): void
    {
        foreach (self::ROLES as $name => [$label, $permissions]) {
            Role::query()->updateOrCreate(['name' => $name], ['label' => $label, 'permissions' => PermissionCatalog::expand($permissions)]);
        }
    }
}
