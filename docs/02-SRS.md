# 02 — Software Requirements Specification (SRS)

**Product:** Auxtech Ops — Accrual Accounting & Lead CRM
**Version:** 1.0 · **Date:** 2026‑11‑01 · **Status:** Baseline for implementation

---

## 1. Introduction

### 1.1 Purpose
Replace the two Google-Sheets workbooks and their Apps Script automation with a single, audited web
application: a Laravel 12 monolith rendering Vue 3 pages through **Inertia.js v2**, styled with
Tailwind CSS v4. All current
data is migrated through seeders and every existing calculation is preserved (see
[01-workbook-analysis.md](01-workbook-analysis.md)).

### 1.2 Scope
Two products sharing one login, one audit trail and one design system:

* **Accounting** — projects, revenue recognition, receivables, customer payments, marketing services,
  partnerships, operating expenses and payments, payroll and salary payments, employee advances,
  referrals, fixed assets, liabilities, capital, Siam contract, monthly financial statements, controls.
* **Lead CRM** — leads, daily slots, call log, follow-up queue, meetings/office visits, pipeline,
  conversion, analytics, daily/monthly/yearly reports.

### 1.3 Definitions
| Term | Meaning |
|---|---|
| Period | Calendar month, identified by its first day (`2026-10-01`) |
| Accrual date | Date an item is earned/incurred |
| Cash date | Date money actually moved |
| Revenue ID / Receivable | Any record a customer can owe against: project, international project, marketing service, opening A/R, partnership revenue |
| Gross settlement | Cash received + processing fee withheld |
| Request ID | Client-generated UUID that makes a posting idempotent |
| Golden values | Statement values from the workbook used as regression oracle |

### 1.4 Constraints
* PHP **8.2.12** (developer machine: Windows + XAMPP) — code must not use PHP ≥ 8.3 features
  (`composer.json` pins `config.platform.php = 8.2.12`).
* Laravel 12, **MySQL / MariaDB from XAMPP** (database `auxtech_ops`, utf8mb4). SQLite in-memory is used
  only by the automated test-suite.
* Frontend: **Inertia.js v2** (`inertiajs/inertia-laravel`, `@inertiajs/vue3`), Vue 3 (Composition API,
  `<script setup>`), Tailwind CSS v4 (`@tailwindcss/vite`), Vite 7.
* Permitted third-party runtime libraries: **Inertia.js** and **Pusher** (`pusher/pusher-php-server`,
  `pusher-js`) only. Laravel first-party packages (Echo) are allowed. No UI kits, chart libraries, icon
  packs, date libraries, Ziggy, Axios-direct usage, dompdf or Intervention.
* Pusher Channels app `2208649`, cluster `ap2` (credentials only in `.env`, never committed).
* Images: **PHP GD** only. PDFs: generated **in the browser with JavaScript** (print-optimised Inertia
  pages + `window.print()` / a small in-house PDF writer for tabular exports) — no server PDF library.
* Hosting: shared host with cron (`* * * * * php artisan schedule:run`) and SMTP; no long-running
  daemons are assumed → queue driver `database` drained by the scheduler; broadcasts use
  `ShouldBroadcastNow` so Pusher works without a worker.
* UI must be **fully responsive** (360 px phones → 1440 px+ desktops), keyboard-accessible, light/dark.

---

## 2. Overall description

### 2.1 User classes & roles
| Role | Capabilities |
|---|---|
| Owner / Admin | Everything, users & roles, settings, period close/reopen, delete |
| Accountant | All accounting entry & reports, cannot change policies or reopen closed periods |
| Sales Manager | CRM full, read accounting dashboard |
| Caller / Sales Rep | Own leads & calls, today/follow-ups, own analytics |
| Investor (viewer) | Read-only dashboard, statements, capital |

Permissions are granular (`accounting.expenses.create`, `crm.leads.view_all`, …) and assigned to roles.

### 2.2 Operating environment
Modern evergreen browsers (Chrome, Edge, Firefox, Safari), responsive from 360 px. Server timezone
Asia/Dhaka; all timestamps stored UTC, all business dates stored as `DATE` in Dhaka.

### 2.3 Design principles
1. **Ledger immutability** — posted cash entries are never edited in place; they are *reversed* with an
   audited counter-entry.
2. **Two dates everywhere** — accrual vs cash.
3. **Calculation in services, never in controllers or views.** Derived values are computed by the
   accounting engine; reports are reproducible from ledgers.
4. **Idempotency** for every posting endpoint (`Idempotency-Key` / `request_id`).
5. **Controls over convenience** — warnings are surfaced, never silently "fixed".

---

## 3. Functional requirements — Accounting

Identifiers: `FR-ACC-xx`. Priority M = must, S = should, C = could.

### 3.1 Settings & periods
* FR-ACC-01 (M) Manage policies: AC useful life, residual, Siam start/months/share %, referral policy,
  referral tier table, default currency, model start date, opening balance date.
* FR-ACC-02 (M) Reporting periods are created automatically daily (02:00 Dhaka) through the current month
  (replaces `dailyAuxtechAutomation`). Status: Historical · Open · Closed.
* FR-ACC-03 (M) **Period close**: closing a month blocks create/update/delete of any record whose
  accrual *or* cash date falls in it; reopen requires Admin and a reason (audited).
* FR-ACC-04 (S) Source aliases (Source_Mapping) searchable and used by importers.

### 3.2 Owners & capital
* FR-ACC-10 (M) Investors with ownership history (effective-dated percentages).
* FR-ACC-11 (M) Capital transactions: contribution, distribution, share transfer/reclassification,
  opening paid-in capital; fields date, investor, type, cash impact, equity impact, counterparty, notes.
* FR-ACC-12 (M) Current paid-in capital per investor; cap table at any date.

### 3.3 Projects & revenue
* FR-ACC-20 (M) Domestic and international project registers with auto IDs `PRJ-YYMM-NNN` /
  `IPRJ-YYMM-NNN`; lead projects (`LEAD-…`) never generate revenue.
* FR-ACC-21 (M) Change orders as separate rows (value, date, note) summed into total contract value.
* FR-ACC-22 (M) Revenue methods: Point in Time, Over Time, Point in Time (Provisional), Lead / No Revenue.
* FR-ACC-23 (M) Revenue recognition ledger with entry checks of §2.2 of the analysis.
* FR-ACC-24 (M) Daily automation: auto-recognise delivered point-in-time projects; create over-time
  progress drafts each month.
* FR-ACC-25 (M) Project 360° page: payments, recognition, A/R, advance, referral, timeline, attachments.
* FR-ACC-26 (M) Referral memo calculated from tier table; referral record auto-created/updated.

### 3.4 Receivables & customer payments
* FR-ACC-30 (M) Customer payment: date-time, receivable (searchable picker across all revenue sources),
  cash received, processing fee, method, account (bank/wallet), reference, notes. ID `PAY-YYMMDD-NNN`.
* FR-ACC-31 (M) Processing fees automatically expensed in Bank & Transfer Fees in the payment month.
* ✅ FR-ACC-32 (M) Receivables report: month-end A/R and customer advance per receivable for any period;
  current balances; aging buckets 0‑30/31‑60/61‑90/90+ (new).
* FR-ACC-33 (M) Opening A/R (legacy) records.
* FR-ACC-34 (M) Marketing services `MKT-YYMM-NNN` with A/R / advance / status.

### 3.5 Partnerships
* FR-ACC-40 (M) Collaborators, packages, assignments (effective from/through, Direct Amount or Share of
  Sales, Auxtech share %).
* FR-ACC-41 (M) Monthly partnership revenue rows generated for active assignments with a real start date;
  blank earned = not recognised; share % and method snapshotted per row.
* FR-ACC-42 (M) Partnership receipts post to customer payments (single source of cash).

### 3.6 Expenses
* FR-ACC-50 (M) Expense bills `EXP-NNN`: accrual period, expense date, category, subcategory, description,
  amount incurred, attachments (receipt images processed with GD).
* FR-ACC-51 (M) Expense payments (installments) via **PaymentPostingService** with all rules of
  analysis §2.4 (idempotent, no overpayment, no future dates, no double processing fee).
* FR-ACC-52 (M) Status Unpaid / Partially Paid / Paid, payable, entry checks.
* ✅ FR-ACC-53 (S) Recurring expense templates (rent, wifi, hosting) generating draft bills monthly (new).
* FR-ACC-54 (M) Expense categories are managed lists; unknown categories roll into *Other operating*.

### 3.7 Payroll, advances, referrals
* FR-ACC-60 (M) Employees master (name, role, base salary, joined/left, bank details, active).
* FR-ACC-61 (M) Payroll run per month: one row per employee, gross salary; auto-draft from base salary
  for active employees (new) — never assumed zero when missing (a *Missing payroll* control).
* FR-ACC-62 (M) Salary payments with the same posting service (remaining = gross − recoveries − paid).
* FR-ACC-63 (M) Employee advances (Disbursement / Recovery) linked to payroll id; recoveries are non-cash.
* FR-ACC-64 (M) Referral payments: paid date + amount; expense only when paid.
* ✅ FR-ACC-65 (S) Payslip (JS PDF) per employee/month (new).

### 3.8 Assets & liabilities
* FR-ACC-70 (M) Fixed assets register, straight-line monthly depreciation schedule, disposal.
* FR-ACC-71 (M) Liability accounts (Prior dues, Asset financing, Deferred contract, Siam share) and
  transactions (increase, decrease, cash impact).
* FR-ACC-72 (M) Siam contract tracker: funding, eligible revenue, accrued share, share paid, payable.

### 3.9 Statements & reporting
* FR-ACC-80 (M) Monthly statement (all 53 lines) for any period; multi-month comparison grid.
* FR-ACC-81 (M) Month pack: Income statement, Cash reconciliation, Balance sheet — printable/PDF in browser.
* FR-ACC-82 (M) Dashboard KPIs: revenue, net income, ending cash, A/R, advances, assets, liabilities,
  equity, balance check; trend charts (pure SVG, no chart library).
* FR-ACC-83 (M) Controls page reproducing every check of analysis §2.12 with drill-down.
* ✅ FR-ACC-84 (S) Budget vs actual per category per month (new).
* ✅ FR-ACC-85 (S) 90-day cash-flow forecast from open A/R, payables, payroll and recurring expenses (new).
* ✅ FR-ACC-86 (S) Bank/wallet accounts (DBBL, EBL, bKash, Cash) with running balance and reconciliation
  against statement balance (new — resolves the "book overdraft" caveat).
* FR-ACC-87 (M) CSV export of every table/report.

## 4. Functional requirements — Lead CRM

* FR-CRM-01 (M) Configurable lists: outcomes (stage, score, follow-up days), services, sources, owners
  (users), business types, industries.
* FR-CRM-02 (M) Leads with all fields of analysis §3; Lead ID `YYYYMMDD-XXXXXXXX`.
* FR-CRM-03 (M) Phone normalisation, duplicate detection (on normalised phone), WhatsApp deep link.
* FR-CRM-04 (M) Auto stage, score, next follow-up, priority, days open, closed date, data checks.
* FR-CRM-05 (M) Daily slot allocation (Settings daily slots 1–500) with reuse of untouched blanks; in the
  app this becomes a **daily target** (calls/leads) instead of blank rows, while still supporting quick
  bulk entry of N blank rows.
* FR-CRM-06 (M) Call log: initial/follow-up calls with outcome, caller, notes, next follow-up; idempotent;
  updates the lead. Count updates/corrections keep history.
* FR-CRM-07 (M) Today action centre: new today, due/overdue, meetings today.
* FR-CRM-08 (M) Follow-up queue, Meetings & office visits (scheduled / time pending).
* FR-CRM-09 (M) Pipeline Kanban by stage with drag & drop (new).
* ✅ FR-CRM-10 (M) Conversion: won deals with deal value; **Convert to Project** creates a project in
  accounting pre-filled from the lead (new integration).
* FR-CRM-11 (M) Reports: daily / monthly / yearly snapshots generated by the scheduler (previous day after
  midnight, closed month on the 1st, closed year on 1 Jan), clipped to launch date, SHA‑256 hash
  de-duplication, immutable archive; PDF rendered in browser.
* FR-CRM-12 (M) Analytics: funnel, by owner, by source, by service, by business type, by industry,
  conversion by cohort (lead date) and wins by closed date.
* FR-CRM-13 (S) Email digests via SMTP: daily follow-up list per caller at 08:30, weekly summary (new).
* ✅ FR-CRM-14 (S) Caller targets & leaderboard (calls/day, conversions) (new).
* ✅ FR-CRM-15 (C) CSV import of leads with phone validation and duplicate preview.

## 5. Cross-cutting requirements

* FR-X-01 (M) Authentication: email + password (Laravel session guard via Inertia), password reset via SMTP,
  optional TOTP 2FA (C), session timeout, login throttling.
* FR-X-02 (M) Audit log of every create/update/delete/post/reverse with before/after JSON, user, IP.
* FR-X-03 (M) Attachments: images resized/compressed with GD to max 1600 px + 320 px thumbnail, WebP/JPEG;
  PDFs stored as-is; private storage, signed URLs.
* FR-X-04 (M) Real-time updates via Pusher: payment posted, lead updated, call saved, period closed,
  automation finished (private channel `auxtech` + per-user notification channel).
* FR-X-05 (M) **Notification system** (full spec in [07-notifications-realtime.md](07-notifications-realtime.md)):
  database notifications + Pusher broadcast + SMTP mail per event type; notification centre page and
  bell with unread badge; per-user channel preferences (in-app / email / off); toast feedback for every
  write; scheduled reminders (follow-ups due, overdue receivables, unpaid payroll, failed automation,
  month-end close checklist).
* FR-X-06 (M) Global search (⌘K) across projects, payments, expenses, employees, leads.
* FR-X-07 (M) Automation monitor: scheduled job runs, last success, failures (replaces Apps_Script tab).
* FR-X-08 (S) Database backup command (mysqldump via cron) and downloadable JSON export.
* FR-X-09 (M) Light/dark theme, keyboard shortcuts, Bengali-Taka formatting (৳ 1,23,456 lakh grouping
  optional setting).

## 6. Non-functional requirements

| ID | Requirement |
|---|---|
| NFR-01 | Statement for one month computes in < 300 ms with 50k ledger rows (indexed SQL aggregates) |
| NFR-02 | API p95 < 250 ms for list endpoints (paginated, 25/page) |
| NFR-03 | Money stored as `DECIMAL(15,2)`; arithmetic in integer paisa; rounding half-up to 2 dp |
| NFR-04 | All posting endpoints idempotent and wrapped in DB transactions with row locks |
| NFR-05 | OWASP ASVS L2: CSRF, XSS escaping, mass-assignment protection, rate limits, secure cookies |
| NFR-06 | Test coverage ≥ 85 % for `app/Services`; golden statement tests must pass for Apr–Nov 2026 |
| NFR-07 | Works on shared hosting: no Node in production (pre-built `public/build`), cron-driven queue |
| NFR-11 | Responsive: every page usable at 360 px; tables collapse to cards < 640 px; touch targets ≥ 40 px |
| NFR-08 | WCAG 2.1 AA: contrast, focus rings, keyboard navigation, aria labels |
| NFR-09 | Availability 99.5 %; daily backups retained 30 days |
| NFR-10 | PHP 8.2 compatible; PSR-12; static analysis clean (Laravel Pint) |

## 7. External interfaces
* Inertia page routes + a few JSON endpoints for pickers/search — see [05-routes-and-pages.md](05-routes-and-pages.md).
* Pusher Channels (private `auxtech`, private `App.Models.User.{id}`), SMTP, cron.

## 8. Data migration
All workbook rows are converted to JSON (`database/seeders/data/**`) and loaded by `WorkbookSeeder`
classes. Acceptance: after `php artisan migrate:fresh --seed` (on XAMPP MySQL), the statement service returns the golden values
for every month Apr–Nov 2026 with balance check 0 (see [08-testing.md](08-testing.md)).

## 9. Acceptance criteria (summary)
1. Golden values reproduced to the paisa.
2. Every Apps Script behaviour has an equivalent scheduled command or service with tests.
3. No calculation implemented in controllers or Vue components.
4. Lighthouse ≥ 90 for performance/accessibility on dashboard.

## 10. Traceability (workbook → system)

| Workbook artefact | System component |
|---|---|
| Monthly_Statements / month tabs | `FinancialStatementService`, page `/accounting/statements/{period}` |
| Receivables | `ReceivableService` |
| Asset_Schedule | `DepreciationService` |
| Checks | `ControlsService` |
| dailyAuxtechAutomation | `accounting:daily` command |
| AuxtechPayments.gs | `PaymentPostingService` |
| AuxtechPartnerships.gs (not installed) | `PartnershipService`, `accounting:partnerships` |
| LeadCRM crmSafetyCheck | `crm:safety-check` command |
| LeadCRM exportReport_ | `CrmReportService` + browser PDF |
| onEdit timestamps | Eloquent timestamps + audit log |
