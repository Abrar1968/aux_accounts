# 14 — Frontend Status (Phase 3 complete)

## Structure (`resources/js`)
| Path | Contents |
|---|---|
| `Layouts/AppLayout.vue` | sidebar (drawer < 1024 px), top bar, Ctrl K / "/" palette, theme toggle, notification bell, account menu, skip link, realtime |
| `Layouts/GuestLayout.vue` | auth screens |
| `Components/ui/` | Button, Field, Input, MoneyInput, Select, Textarea, Switch, Combobox (async, keyboard), Badge, Card, StatCard, PageHeader, DataTable (table ≥ 640 px, cards below, footer totals), Pagination (paginator or resource meta), Tabs, Drawer (right panel / full-screen sheet + sticky footer), Modal (bottom sheet on phones), ConfirmDialog (typed reason), Dropdown, FilterBar, MonthPicker, BottomBar, EmptyState, Skeleton, FileDrop (GD uploads + signed links), NotificationBell, CommandPalette, ToastStack, Icon (inline SVG) |
| `Components/charts/` | BarChart, LineChart (hover guide + tooltip), Donut, Sparkline, Funnel — pure SVG, measured width (`useChartWidth`) so labels stay legible on phones, screen-reader tables |
| `Components/accounting/` | StatementTable, PaymentDrawer (idempotent `request_id`), ReceivablePicker |
| `Components/crm/` | LeadCard, PriorityBadge, PhoneField (live normalise + duplicate check), CallDrawer (`call_uuid`), LeadFormDrawer, ReportBody |
| `Components/print/PrintLayout.vue` | A4 document frame for statements, payslips, CRM reports |
| `Composables/` | useFilters (URL-synced), useRequestId, useHotkey, useJson, usePermissions, useRealtime (Echo/Pusher), useToasts |
| `lib/` | format.js (BDT en-IN grouping, Dhaka dates, tones), pdf.js (in-house PDF 1.4 writer + print helper), exports.js, nav.js, echo.js |

## Behaviour
* **Realtime:** `private-auxtech` events reload the current page's props; `App.Models.User.{id}` notifications raise a toast and bump the bell.
* **PDF:** "Print / PDF" uses print CSS (A4, light palette, chrome hidden, only `.print-doc` printed). "PDF" buttons on statements and receivables generate a file with `lib/pdf.js` (Helvetica, BDT text).
* **Responsive:** phones get card lists, full-screen drawers, bottom action bars, a horizontally snapping Kanban, and charts measured to the container.
* **Dates:** models serialize Dhaka dates (`Y-m-d`; timestamps with `+06:00`) so the UI never shifts a day.

## QA (headless Chromium, real seeded data)
* 39 pages × 3 widths (360, 768, 1440) = 117 page-views: HTTP 200, 0 JS errors, 0 horizontal overflow.
* Flows: Ctrl K search ("wtb" → WTB_SG), dark mode, expense payment via drawer → "SAVED EPAY-…" toast, print → A4 PDF (statement, payslip, CRM report), phone lead drawer 360×780 full screen.
* `npm run build` and `php artisan test` (49 tests) green.

## Known limits
* Lighthouse not run in this environment; the manual QA checklist in docs/08 §5 still applies before release.
* Realtime needs `PUSHER_APP_SECRET` in `.env`; without it pages work, toasts appear only on your own actions.
