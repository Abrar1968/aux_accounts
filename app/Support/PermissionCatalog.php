<?php

namespace App\Support;

/**
 * Every permission the app checks (route middleware, services, UI), grouped for the
 * Roles & permissions screen. Roles store explicit lists of these keys — no wildcards.
 */
final class PermissionCatalog
{
    /** @var array<string, array<string, string>> group => [key => label] */
    public const GROUPS = [
        'General' => [
            'dashboard.view' => 'See the dashboard',
            'attachments.view' => 'View attachments',
            'attachments.create' => 'Upload attachments',
            'attachments.delete' => 'Delete attachments',
            'email.send' => 'Send emails to clients',
        ],
        'Projects & revenue' => [
            'accounting.projects.view' => 'View projects, recognition, marketing & partnerships',
            'accounting.projects.create' => 'Create projects / services',
            'accounting.projects.update' => 'Edit projects / services',
            'accounting.projects.delete' => 'Delete projects / services',
        ],
        'Customer payments' => [
            'accounting.payments.view' => 'View receipts & receivables',
            'accounting.payments.create' => 'Record receipts',
            'accounting.payments.update' => 'Edit receipts',
            'accounting.payments.delete' => 'Delete receipts',
        ],
        'Expenses' => [
            'accounting.expenses.view' => 'View bills & recurring bills',
            'accounting.expenses.create' => 'Create bills',
            'accounting.expenses.update' => 'Edit bills',
            'accounting.expenses.pay' => 'Pay / reverse bill payments',
            'accounting.expenses.delete' => 'Delete bills',
        ],
        'Payroll & people' => [
            'accounting.payroll.view' => 'View payroll, employees, advances, referrals',
            'accounting.payroll.create' => 'Create payroll / employees / advances',
            'accounting.payroll.update' => 'Edit payroll / employees',
            'accounting.payroll.pay' => 'Pay salaries & referrals',
            'accounting.payroll.delete' => 'Delete payroll records',
        ],
        'Balance sheet' => [
            'accounting.ledger.view' => 'View assets, liabilities, capital',
            'accounting.ledger.create' => 'Add ledger entries',
            'accounting.ledger.update' => 'Edit ledger entries',
            'accounting.ledger.delete' => 'Delete ledger entries',
            'accounting.banking.view' => 'View bank & cash',
            'accounting.banking.manage' => 'Manage bank accounts',
            'accounting.banking.reconcile' => 'Reconcile bank statements',
        ],
        'Reports & periods' => [
            'accounting.reports.view' => 'Statements, controls, forecast',
            'accounting.budgets.view' => 'View budgets',
            'accounting.budgets.manage' => 'Edit budgets',
            'accounting.periods.manage' => 'Close periods',
            'accounting.periods.reopen' => 'Reopen closed periods',
        ],
        'Lead CRM' => [
            'crm.leads.view' => 'View own leads, pipeline, follow-ups',
            'crm.leads.view_all' => 'View every caller’s leads',
            'crm.leads.create' => 'Add leads',
            'crm.leads.update' => 'Edit leads / move stage',
            'crm.leads.delete' => 'Delete leads',
            'crm.leads.import' => 'Import leads (CSV)',
            'crm.calls.create' => 'Log calls',
            'crm.reports.view' => 'CRM reports, analytics, leaderboard',
            'crm.targets.manage' => 'Set caller targets',
            'crm.settings.manage' => 'Manage CRM picklists',
        ],
        'System' => [
            'settings.manage' => 'Settings, branding, integrations, automation',
            'users.manage' => 'Users & roles',
            'audit.view' => 'Audit log',
        ],
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_merge(...array_map('array_keys', array_values(self::GROUPS)));
    }

    /**
     * Expand legacy wildcard grants ("*", "crm.*") into explicit keys.
     *
     * @param  list<string>  $granted
     * @return list<string>
     */
    public static function expand(array $granted): array
    {
        $out = [];
        foreach (self::keys() as $key) {
            foreach ($granted as $g) {
                if ($g === '*' || $g === $key || (str_ends_with($g, '.*') && str_starts_with($key, substr($g, 0, -1)))) {
                    $out[] = $key;
                    break;
                }
            }
        }

        return $out;
    }

    /** @return list<array{group:string, items:list<array{key:string,label:string}>}> */
    public static function forUi(): array
    {
        return array_map(fn ($g, $items) => ['group' => $g, 'items' => array_map(fn ($k, $l) => ['key' => $k, 'label' => $l], array_keys($items), $items)],
            array_keys(self::GROUPS), self::GROUPS);
    }
}
