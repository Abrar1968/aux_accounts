# 06 — Frontend & Design System (Inertia + Vue 3 + Tailwind v4)

Design language **"Command glass"** (Nov 2026 redesign, inspired by the *Auxtech Command* concept):
midnight navy + amber, frosted-glass surfaces over soft ambient light, mono uppercase micro-labels
and mono figures. Dark is the primary theme (default for new browsers); light is a cool "cloud"
variant of the same tokens. Numbers are the hero; chrome stays quiet.

## 1. Stack
* Inertia v2 (`@inertiajs/vue3`): `Link`, `useForm`, `router`, partial reloads, deferred props,
  progress bar, persistent layout (`resources/js/app.js` sets `AppLayout` as default).
* Vue 3 `<script setup>`; state = page props + small composables (no Pinia needed with Inertia).
* Tailwind v4 via `@tailwindcss/vite`, tokens in `resources/css/app.css` (`@theme`), dark mode by
  `.dark` class (`@custom-variant dark`).
* Realtime: `laravel-echo` + `pusher-js` (`resources/js/lib/echo.js`).
* No UI/chart/icon/date libraries: icons are inline SVG (`Components/ui/Icon.vue`), charts are hand-built SVG
  components, dates via `Intl`.

## 2. Tokens (`resources/css/app.css`)
| Token | Light | Dark | Use |
|---|---|---|---|
| canvas | #edf0f7 + amber/blue washes | #0a1128 + amber/blue washes | page background (ambient orbs drift behind the glass) |
| surface / surface-2 / surface-3 | #fff / #f3f5fb / #e7ebf4 | #111d42 / #16244f / #1d2d5d | solid fills (inputs, sticky cells) |
| line / line-strong | navy 8.5 % / 16 % | cloud 8.5 % / 16 % | hairline borders |
| ink / ink-2 / ink-3 | #0a1128 / #3d4763 / #6b748c | #e8ecf7 / #b4bdd6 / #8b94ab | text hierarchy |
| brand-300…500 | #ffc978 / #ffb454 / #f59e2e | same | amber accent; `text-on-brand` = navy text on amber |
| positive / negative / warning / info | #0f9b68 / #e04848 / #c27c0e / #2f6fe0 | #4ade9e / #ff6b6b / #f7c85c / #5b9dff | statuses, money direction |

Settings → Branding can still switch the accent (amber house colour, ocean, indigo, violet, emerald,
teal, rose); each palette also sets `--color-on-brand`.

Glass utilities: `card` (frosted panel, 18 px radius, blur 18 px), `glass-pop` (opaque glass for
menus, drawers, modals, palette, toasts), `glass-chrome` (top bar, section panel). Hover utilities:
`lift` (rise 3 px + amber edge glow) and `spotlight` (amber radial glow that follows the pointer;
`AppLayout` feeds `--mx/--my`). Rows highlight with an amber inset bar; buttons are pills that rise
on hover; primary buttons carry an amber glow.

Typography: **Fustat** (UI, self-hosted variable woff2) and **JetBrains Mono** (figures via the `num`
utility, table money columns, `eyebrow` micro-labels at 10.5 px / .16em uppercase). 14 px base,
page titles 30 px bold −0.03em. Money: `৳1,23,456` (en-IN grouping), negatives in parentheses and
`text-negative`. Documents (invoice, payslip, statements, reports) render as light paper (`.paper`)
in both themes.

## 3. Layout & responsiveness
| Breakpoint | Behaviour |
|---|---|
| < 640 px (phones) | rail + section panel slide in together as one drawer; tables render as stacked cards (`DataTable` `mobile="cards"`); forms = full-screen sheet; sticky bottom action bar; toasts bottom-centre |
| 640–1023 px | drawer sidebar, 2-column KPI grid, tables scroll horizontally with sticky first column |
| ≥ 1024 px | 76 px module rail (Accounting · Lead CRM · System) + 248 px glass section panel, 4-column KPI grid, drawers 480 px from right |
| ≥ 1440 px | content max-width 1440 px, statement grid shows 8+ months |

Touch targets ≥ 40 px, focus rings on everything, `prefers-reduced-motion` respected.

## 4. Component library (`resources/js/Components`)
`ui/`: Icon, Button (primary/secondary/ghost/danger, loading), Input, MoneyInput (৳ prefix, 2 dp),
DateInput (native date), Select, Combobox (async — receivable/lead picker), Textarea, Checkbox, Switch,
Badge (status tones), Card, StatCard (value, delta, sparkline), DataTable (sorting, filters, pagination,
responsive cards, empty & loading skeletons), Drawer/Sheet, Modal, ConfirmDialog, Tabs, Dropdown,
Tooltip, EmptyState, Skeleton, ToastStack, NotificationBell, CommandPalette (Ctrl K), PageHeader
(title, breadcrumbs, actions), FilterBar, MonthPicker, FileDrop (attachments with preview).
`charts/`: LineChart, BarChart, Donut, Sparkline, Funnel — pure SVG, responsive via viewBox, accessible
`<title>`/table fallback.
`accounting/`: StatementTable, ReceivablePicker, PaymentDrawer (idempotent request_id), ControlRow.
`crm/`: LeadCard, PipelineColumn, CallDrawer, PhoneField (live normalisation + duplicate check), PriorityBadge.

## 5. Page inventory (Phase 3)
Dashboard (KPI cards, revenue vs expense chart, cash trend, top receivables, expense mix, controls summary) ·
Statements (month pack with tabs IS / Cash / BS, grid view, print) · Controls · Projects (list, 360° page) ·
Recognition · Payments · Receivables (month picker, aging) · Marketing · Partnerships · Expenses · Payroll
(month board with pay buttons) · Advances · Referrals · Assets (+schedule) · Liabilities (+Siam tracker) ·
Capital (cap table) · CRM Today · Leads · Lead 360° · Pipeline (Kanban) · Follow-ups · Meetings · CRM Reports ·
Analytics · Settings · Users/Roles · Automation · Audit · Notifications · Profile.

## 6. Interaction rules
* Every write gives feedback: optimistic disable → toast success/error (flash) → partial reload.
* Validation errors inline under the field (`form.errors`), first error focused.
* Destructive actions use ConfirmDialog with typed reason when accounting data is affected.
* Closed-period records render read-only with a lock badge.
* Lists remember filters in the URL (shareable, back-button safe).

## 7. Browser PDF (no server PDF library)
* `Components/print/PrintLayout.vue` + `@media print` CSS (A4, 12 mm margins, repeat table headers,
  page-break rules). "Download PDF" opens the print view in a hidden iframe and calls `print()` →
  "Save as PDF". Used for statements, payslips, CRM reports, receivable statements.
* `lib/pdf.js` (in-house, ~200 lines): minimal PDF 1.4 writer for tabular exports when a file must be
  generated without the print dialog (Helvetica, text + lines + rects). No third-party code.

## 8. Accessibility & quality bar
WCAG 2.1 AA contrast in both themes, `aria-live` toasts, labelled icon buttons, skip-to-content link,
keyboard Kanban alternative (move via menu). Lighthouse ≥ 90 performance/accessibility on Dashboard.

## 9. Search, keyboard shortcuts & address book (Nov 2026)
**Search everywhere** (`App\Support\Search`, `resources/js/lib/search.js`): every word must match some
column, in any order, case-insensitive. Phone numbers match with or without spaces, dashes, `+880`
or a leading 0 ("+880 1811-222333" finds `01811222333`). Lists search more columns (projects: client
email/phone, domain, referrer, manager; leads: service, source, owner, outcome; receipts: method,
account). Ctrl+K also returns marketing clients and users, ranks matches and highlights them.

**Email address book**: To / CC are chip pickers (`RecipientPicker.vue`) over every project
client email, the active team and anyone emailed before (`GET /system/email/contacts`). Picking a
project, receipt or receivable pre-fills that client's address. Type any address and press
Enter/comma; paste a list; Backspace removes the last chip.

**Keyboard shortcuts** (`resources/js/lib/shortcuts.js`, cheat-sheet: Ctrl+/ or the ⚡ rail button;
⌘ also works on Mac). Shortcuts the user lacks permission for are ignored.

| Keys | Action | Keys | Action |
|---|---|---|---|
| Ctrl+N / **Alt+N** | New project | Ctrl+D | Dashboard |
| Ctrl+P | Record payment (receipt) | Ctrl+O | Projects |
| Ctrl+E | New expense / bill | Ctrl+Shift+P | Customer payments |
| Ctrl+L | Add lead | Ctrl+Shift+E | Expenses |
| Ctrl+M | Compose email | Ctrl+B | Bank & cash |
| Ctrl+K | Search / jump | Ctrl+G | Financial statements |
| Ctrl+F | Search this list | Ctrl+H | CRM today |
| Ctrl+S / Ctrl+Enter | Save the open drawer | Ctrl+J / I / U | Leads / Pipeline / Follow-ups |
| Ctrl+/ or ? | Shortcut cheat-sheet | Ctrl+, | Settings |

Chrome and Edge never pass Ctrl+N to a web page (it opens a new window), so Alt+N is the
dependable "new project" key there.

**Roles & permissions**: each role is a set of tick boxes grouped by area
(`App\Support\PermissionCatalog`, 48 permissions). Wildcards such as `*` / `crm.*` are no longer stored —
migration `2026_11_11_000100` expanded existing roles. The owner always has everything, including
permissions added in future.
