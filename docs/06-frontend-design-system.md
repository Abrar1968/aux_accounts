# 06 — Frontend & Design System (Inertia + Vue 3 + Tailwind v4)

Goal: a calm, dense, professional finance tool — closer to Linear / Stripe Dashboard than to an
admin template. Numbers are the hero; chrome is quiet.

## 1. Stack
* Inertia v2 (`@inertiajs/vue3`): `Link`, `useForm`, `router`, partial reloads, deferred props,
  progress bar, persistent layout (`resources/js/app.js` sets `AppLayout` as default).
* Vue 3 `<script setup>`; state = page props + small composables (no Pinia needed with Inertia).
* Tailwind v4 via `@tailwindcss/vite`, tokens in `resources/css/app.css` (`@theme`), dark mode by
  `.dark` class (`@custom-variant dark`).
* Realtime: `laravel-echo` + `pusher-js` (`resources/js/lib/echo.js`).
* No UI/chart/icon/date libraries: icons are inline SVG (`Components/ui/Icon.vue`), charts are hand-built SVG
  components, dates via `Intl`.

## 2. Tokens
| Token | Light | Dark | Use |
|---|---|---|---|
| canvas | #f6f7f9 | #0b0f17 | page background |
| surface / surface-2 | #fff / #f1f3f6 | #121826 / #182033 | cards, inputs / hover, table header |
| line | #e3e6eb | #243049 | borders, dividers |
| ink / ink-2 / ink-3 | #0f172a / #475569 / #94a3b8 | #e6ebf5 / #a3b0c6 / #66748d | text hierarchy |
| brand-500/600/700 | #3d6ef5 / #2b57db / #2245b0 | same | primary actions, active nav |
| positive / negative / warning / info | #0f9f6e / #d4364b / #c27a06 / #2b6cd4 | same | money direction, statuses |

Typography: Inter (system fallback), 14 px base, tabular numerals for all money (`num` utility),
headings 20–24 px semibold tight tracking. Radius 14 px cards, 8 px controls. Shadows minimal.
Money: `৳1,23,456` (en-IN grouping), negatives in parentheses and `text-negative`.

## 3. Layout & responsiveness
| Breakpoint | Behaviour |
|---|---|
| < 640 px (phones) | sidebar = slide-over drawer; tables render as stacked cards (`DataTable` `mobile="cards"`); forms = full-screen sheet; sticky bottom action bar; toasts bottom-centre |
| 640–1023 px | drawer sidebar, 2-column KPI grid, tables scroll horizontally with sticky first column |
| ≥ 1024 px | fixed 264 px sidebar, 4-column KPI grid, drawers 480 px from right |
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
