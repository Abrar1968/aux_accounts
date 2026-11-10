// Information architecture: three modules (left rail) → sections (glass panel) → pages.
// `perm` hides items the user cannot access (null = everyone signed in).
export const modules = [
    { key: 'accounting', label: 'Accounting', icon: 'layers', blurb: 'Books, cash & balance sheet' },
    { key: 'crm', label: 'Lead CRM', icon: 'phone', blurb: 'Leads, calls & pipeline' },
    { key: 'system', label: 'System', icon: 'cog', blurb: 'People, settings & automation' },
];

export const navigation = [
    { section: 'Overview', module: 'accounting', items: [
        { label: 'Dashboard', href: '/', icon: 'home', perm: 'dashboard.view' },
        { label: 'Controls', href: '/accounting/controls', icon: 'shield', perm: 'accounting.reports.view' },
        { label: 'Cash forecast', href: '/accounting/forecast', icon: 'trend', perm: 'accounting.reports.view' },
        { label: 'Budgets', href: '/accounting/budgets', icon: 'chart', perm: 'accounting.budgets.view' },
    ]},
    { section: 'Revenue', module: 'accounting', items: [
        { label: 'Projects', href: '/accounting/projects', icon: 'briefcase', perm: 'accounting.projects.view' },
        { label: 'Recognition', href: '/accounting/recognition', icon: 'check', perm: 'accounting.projects.view' },
        { label: 'Customer payments', href: '/accounting/payments', icon: 'inbox', perm: 'accounting.payments.view' },
        { label: 'Receivables', href: '/accounting/receivables', icon: 'clock', perm: 'accounting.payments.view' },
        { label: 'Marketing services', href: '/accounting/marketing', icon: 'megaphone', perm: 'accounting.projects.view' },
        { label: 'Partnerships', href: '/accounting/partnerships', icon: 'link', perm: 'accounting.projects.view' },
    ]},
    { section: 'Spending', module: 'accounting', items: [
        { label: 'Expenses', href: '/accounting/expenses', icon: 'receipt', perm: 'accounting.expenses.view' },
        { label: 'Recurring bills', href: '/accounting/recurring', icon: 'reverse', perm: 'accounting.expenses.view' },
        { label: 'Payroll', href: '/accounting/payroll', icon: 'users', perm: 'accounting.payroll.view' },
        { label: 'Employees', href: '/accounting/employees', icon: 'user', perm: 'accounting.payroll.view' },
        { label: 'Advances', href: '/accounting/advances', icon: 'wallet', perm: 'accounting.payroll.view' },
        { label: 'Referrals', href: '/accounting/referrals', icon: 'gift', perm: 'accounting.payroll.view' },
    ]},
    { section: 'Balance sheet', module: 'accounting', items: [
        { label: 'Bank & cash', href: '/accounting/banking', icon: 'cash', perm: 'accounting.banking.view' },
        { label: 'Assets', href: '/accounting/assets', icon: 'box', perm: 'accounting.ledger.view' },
        { label: 'Liabilities', href: '/accounting/liabilities', icon: 'scale', perm: 'accounting.ledger.view' },
        { label: 'Capital', href: '/accounting/capital', icon: 'pie', perm: 'accounting.ledger.view' },
        { label: 'Statements', href: '/accounting/statements', icon: 'doc', perm: 'accounting.reports.view' },
        { label: 'Periods', href: '/accounting/periods', icon: 'lock', perm: 'accounting.reports.view' },
    ]},
    { section: 'Lead CRM', module: 'crm', items: [
        { label: 'Today', href: '/crm', icon: 'sun', perm: 'crm.leads.view' },
        { label: 'Leads', href: '/crm/leads', icon: 'phone', perm: 'crm.leads.view' },
        { label: 'Pipeline', href: '/crm/pipeline', icon: 'columns', perm: 'crm.leads.view' },
        { label: 'Follow-ups', href: '/crm/follow-ups', icon: 'bell', perm: 'crm.leads.view' },
        { label: 'Meetings', href: '/crm/meetings', icon: 'calendar', perm: 'crm.leads.view' },
    ]},
    { section: 'Insights', module: 'crm', items: [
        { label: 'Reports', href: '/crm/reports', icon: 'chart', perm: 'crm.reports.view' },
        { label: 'Analytics', href: '/crm/analytics', icon: 'trend', perm: 'crm.reports.view' },
        { label: 'Leaderboard', href: '/crm/leaderboard', icon: 'target', perm: 'crm.reports.view' },
        { label: 'Import leads', href: '/crm/import', icon: 'upload', perm: 'crm.leads.import' },
    ]},
    { section: 'System', module: 'system', items: [
        { label: 'Send email', href: '/system/email', icon: 'mail', perm: 'email.send' },
        { label: 'Settings', href: '/settings', icon: 'cog', perm: 'settings.manage' },
        { label: 'Users & roles', href: '/system/users', icon: 'users', perm: 'users.manage' },
        { label: 'Automation', href: '/system/automation', icon: 'bolt', perm: 'settings.manage' },
        { label: 'Audit log', href: '/system/audit', icon: 'shield', perm: 'audit.view' },
    ]},
    { section: 'Account', module: 'system', items: [
        { label: 'Notifications', href: '/notifications', icon: 'bell', perm: null },
        { label: 'Profile', href: '/profile', icon: 'user', perm: null },
    ]},
];

export function can(user, perm) {
    if (!perm) return !!user;
    const granted = user?.permissions ?? [];
    return granted.some((g) => g === '*' || g === perm || (g.endsWith('.*') && perm.startsWith(g.slice(0, -1))));
}

/** Section + item for a URL (longest matching href) — used for page eyebrows and breadcrumbs. */
export function findNav(url) {
    const path = String(url ?? '').split('?')[0];
    let best = null;
    for (const s of navigation) {
        for (const i of s.items) {
            const hit = i.href === '/' ? path === '/' : path === i.href || path.startsWith(`${i.href}/`);
            if (hit && (!best || i.href.length > best.href.length)) best = { ...i, section: s.section, module: s.module };
        }
    }
    return best;
}

/** "+ Create" quick actions in the top bar (each page opens its drawer when ?new=1). */
export const quickCreate = [
    { label: 'New project', href: '/accounting/projects?new=1', icon: 'briefcase', perm: 'accounting.projects.create' },
    { label: 'Record receipt', href: '/accounting/payments?new=1', icon: 'inbox', perm: 'accounting.payments.create' },
    { label: 'New bill', href: '/accounting/expenses?new=1', icon: 'receipt', perm: 'accounting.expenses.create' },
    { label: 'Add lead', href: '/crm/leads?new=1', icon: 'phone', perm: 'crm.leads.create' },
    { label: 'Send email', href: '/system/email', icon: 'mail', perm: 'email.send' },
];
