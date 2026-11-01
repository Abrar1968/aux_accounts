// Sidebar information architecture. `perm` hides items the user cannot access.
export const navigation = [
    { section: 'Overview', items: [
        { label: 'Dashboard', href: '/', icon: 'home', perm: 'dashboard.view' },
        { label: 'Controls', href: '/accounting/controls', icon: 'shield', perm: 'accounting.reports.view' },
    ]},
    { section: 'Revenue', items: [
        { label: 'Projects', href: '/accounting/projects', icon: 'briefcase', perm: 'accounting.projects.view' },
        { label: 'Recognition', href: '/accounting/recognition', icon: 'check', perm: 'accounting.projects.view' },
        { label: 'Customer payments', href: '/accounting/payments', icon: 'inbox', perm: 'accounting.payments.view' },
        { label: 'Receivables', href: '/accounting/receivables', icon: 'clock', perm: 'accounting.payments.view' },
        { label: 'Marketing services', href: '/accounting/marketing', icon: 'megaphone', perm: 'accounting.projects.view' },
        { label: 'Partnerships', href: '/accounting/partnerships', icon: 'link', perm: 'accounting.projects.view' },
    ]},
    { section: 'Spending', items: [
        { label: 'Expenses', href: '/accounting/expenses', icon: 'receipt', perm: 'accounting.expenses.view' },
        { label: 'Payroll', href: '/accounting/payroll', icon: 'users', perm: 'accounting.payroll.view' },
        { label: 'Advances', href: '/accounting/advances', icon: 'wallet', perm: 'accounting.payroll.view' },
        { label: 'Referrals', href: '/accounting/referrals', icon: 'gift', perm: 'accounting.payroll.view' },
    ]},
    { section: 'Balance sheet', items: [
        { label: 'Assets', href: '/accounting/assets', icon: 'box', perm: 'accounting.ledger.view' },
        { label: 'Liabilities', href: '/accounting/liabilities', icon: 'scale', perm: 'accounting.ledger.view' },
        { label: 'Capital', href: '/accounting/capital', icon: 'pie', perm: 'accounting.ledger.view' },
        { label: 'Statements', href: '/accounting/statements', icon: 'doc', perm: 'accounting.reports.view' },
    ]},
    { section: 'Lead CRM', items: [
        { label: 'Today', href: '/crm', icon: 'sun', perm: 'crm.leads.view' },
        { label: 'Leads', href: '/crm/leads', icon: 'phone', perm: 'crm.leads.view' },
        { label: 'Pipeline', href: '/crm/pipeline', icon: 'columns', perm: 'crm.leads.view' },
        { label: 'Follow-ups', href: '/crm/follow-ups', icon: 'bell', perm: 'crm.leads.view' },
        { label: 'Meetings', href: '/crm/meetings', icon: 'calendar', perm: 'crm.leads.view' },
        { label: 'Reports', href: '/crm/reports', icon: 'chart', perm: 'crm.reports.view' },
    ]},
    { section: 'System', items: [
        { label: 'Settings', href: '/settings', icon: 'cog', perm: 'settings.manage' },
        { label: 'Automation', href: '/system/automation', icon: 'bolt', perm: 'settings.manage' },
    ]},
];

export function can(user, perm) {
    const granted = user?.permissions ?? [];
    return granted.some((g) => g === '*' || g === perm || (g.endsWith('.*') && perm.startsWith(g.slice(0, -1))));
}
