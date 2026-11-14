// Global keyboard shortcuts (Ctrl on Windows/Linux, Ctrl or ⌘ on macOS).
// Browsers never hand Ctrl+N / Ctrl+T / Ctrl+W to web pages (Chrome/Edge open a new window/tab),
// so "New project" also listens on Alt+N.
export const shortcuts = [
    { group: 'Create', keys: ['Ctrl', 'N'], alt: ['Alt', 'N'], key: 'n', label: 'New project', href: '/accounting/projects?new=1', perm: 'accounting.projects.create', icon: 'briefcase' },
    { group: 'Create', keys: ['Ctrl', 'P'], key: 'p', label: 'Record payment (receipt)', href: '/accounting/payments?new=1', perm: 'accounting.payments.create', icon: 'inbox' },
    { group: 'Create', keys: ['Ctrl', 'E'], key: 'e', label: 'New expense / bill', href: '/accounting/expenses?new=1', perm: 'accounting.expenses.create', icon: 'receipt' },
    { group: 'Create', keys: ['Ctrl', 'L'], key: 'l', label: 'Add lead', href: '/crm/leads?new=1', perm: 'crm.leads.create', icon: 'phone' },
    { group: 'Create', keys: ['Ctrl', 'M'], key: 'm', label: 'Compose email', href: '/system/email', perm: 'email.send', icon: 'mail' },
    { group: 'Go to', keys: ['Ctrl', 'D'], key: 'd', label: 'Dashboard', href: '/', perm: 'dashboard.view', icon: 'home' },
    { group: 'Go to', keys: ['Ctrl', 'O'], key: 'o', label: 'Projects', href: '/accounting/projects', perm: 'accounting.projects.view', icon: 'briefcase' },
    { group: 'Go to', keys: ['Ctrl', 'Shift', 'P'], key: 'p', shift: true, label: 'Customer payments', href: '/accounting/payments', perm: 'accounting.payments.view', icon: 'inbox' },
    { group: 'Go to', keys: ['Ctrl', 'Shift', 'E'], key: 'e', shift: true, label: 'Expenses', href: '/accounting/expenses', perm: 'accounting.expenses.view', icon: 'receipt' },
    { group: 'Go to', keys: ['Ctrl', 'B'], key: 'b', label: 'Bank & cash', href: '/accounting/banking', perm: 'accounting.banking.view', icon: 'cash' },
    { group: 'Go to', keys: ['Ctrl', 'G'], key: 'g', label: 'Financial statements', href: '/accounting/statements', perm: 'accounting.reports.view', icon: 'doc' },
    { group: 'Go to', keys: ['Ctrl', 'H'], key: 'h', label: 'CRM today', href: '/crm', perm: 'crm.leads.view', icon: 'sun' },
    { group: 'Go to', keys: ['Ctrl', 'J'], key: 'j', label: 'Leads', href: '/crm/leads', perm: 'crm.leads.view', icon: 'phone' },
    { group: 'Go to', keys: ['Ctrl', 'I'], key: 'i', label: 'Pipeline', href: '/crm/pipeline', perm: 'crm.leads.view', icon: 'columns' },
    { group: 'Go to', keys: ['Ctrl', 'U'], key: 'u', label: 'Follow-ups', href: '/crm/follow-ups', perm: 'crm.leads.view', icon: 'bell' },
    { group: 'Go to', keys: ['Ctrl', 'Shift', 'M'], key: 'm', shift: true, label: 'Inbox (client replies)', href: '/system/inbox', perm: 'email.inbox', icon: 'inbox' },
    { group: 'Go to', keys: ['Ctrl', ','], key: ',', label: 'Settings', href: '/settings', perm: 'settings.manage', icon: 'cog' },
    { group: 'Anywhere', keys: ['Ctrl', 'K'], key: 'k', label: 'Search everything / jump to a page', action: 'palette', icon: 'search' },
    { group: 'Anywhere', keys: ['Ctrl', 'F'], key: 'f', label: 'Search this list', action: 'find', icon: 'filter' },
    { group: 'Anywhere', keys: ['Ctrl', 'S'], key: 's', label: 'Save the open form', action: 'save', icon: 'check' },
    { group: 'Anywhere', keys: ['Ctrl', 'Enter'], key: 'enter', label: 'Save the open form', action: 'save', icon: 'check', hidden: true },
    { group: 'Anywhere', keys: ['Ctrl', '/'], key: '/', label: 'Show these shortcuts', action: 'help', icon: 'info' },
    { group: 'Anywhere', keys: ['Esc'], label: 'Close drawer, dialog or search', icon: 'x', static: true },
];

export const isMac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent);
export const keyLabel = (k) => (isMac && k === 'Ctrl' ? '⌘' : k);

/** Shortcut for a URL (to show hints next to menu items). */
export const shortcutFor = (href) => shortcuts.find((s) => s.href === href && !s.hidden);

/** Find the shortcut matching a keydown event. */
export function match(e) {
    const k = e.key.toLowerCase();
    if (e.altKey && !e.ctrlKey && !e.metaKey && !e.shiftKey && e.code === 'KeyN') return shortcuts[0]; // Alt+N fallback
    if (!(e.ctrlKey || e.metaKey) || e.altKey) return null;
    const key = e.shiftKey && e.code?.startsWith('Key') ? e.code.slice(3).toLowerCase() : k;
    return shortcuts.find((s) => s.key && s.key === key && !!s.shift === e.shiftKey && !s.static) ?? null;
}

/** Ctrl+S / Ctrl+Enter: submit the topmost open dialog's form, else the focused field's form. */
export function submitOpenForm() {
    const dialogs = [...document.querySelectorAll('[role="dialog"]')].filter((d) => d.offsetParent !== null || d.getClientRects().length);
    const scope = dialogs.at(-1);
    const btn = scope?.querySelector('button[type="submit"]:not([disabled]), button[form]:not([disabled])');
    if (btn) { btn.click(); return true; }
    const form = document.activeElement?.closest?.('form');
    if (form) { form.requestSubmit(); return true; }
    return false;
}

/** Ctrl+F: focus the page's own search box when there is one (else the browser's find runs). */
export function focusPageSearch() {
    const el = [...document.querySelectorAll('[data-page-search]')].find((i) => i.offsetParent !== null);
    if (!el) return false;
    el.focus(); el.select?.();
    return true;
}
