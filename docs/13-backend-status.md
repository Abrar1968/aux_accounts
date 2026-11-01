# 13 — Backend Status (Phase 1 complete)

## Verified
| Check | Result |
|---|---|
| Golden statements (Apr–Nov 2026, 50 lines × 8 months) | **400 / 400 values match**, balance check 0 every month (SQLite + MariaDB) |
| CRM snapshot (2 Nov 2026) | 142 leads, 93 active, 0 won, 81 due/overdue, 7 time-pending visits; every lead's stage, score, next follow-up, priority, normalised phone, data checks and call total match |
| Controls | delivery-evidence count 16 (= workbook), missing payroll detected, opening balance −1,910,700 |
| Test suite | 49 tests / 2,319 assertions — unit, service, golden, HTTP, notifications, commands |
| Databases | SQLite (tests) and MariaDB 10.11 (XAMPP parity) |
| Style | Laravel Pint clean; no PHP ≥ 8.3 syntax; platform pinned to 8.2.12 |

## Delivered
* Seeders: Role, User (ADMIN_* + Abrar/Rizwan callers), Settings, AccountingWorkbook, CrmWorkbook — audit muted,
  sequences bumped (next IDs: EXP-110, PAY-…-044, ADV-051, REF-018, PRJ-…-019).
* Services (service–repository): accounting (statements, receivables + aging, recognition + automation, payments,
  posting, payroll, advances, referrals, assets, liabilities + Siam tracker, capital + cap table, partnerships,
  controls, dashboard), CRM (phone, lead computer, leads, calls, daily target, dashboard, reports, analytics, settings),
  notifications (13 types, preferences, reminders), media (GD images, attachments, signed URLs), system (auth,
  users/roles, search, automation monitor, backup, settings).
* HTTP: 107 routes, 43 FormRequests, resources, thin controllers, `permission:*` middleware, `LeadPolicy`
  (callers see own leads), owner gate, domain-exception → toast handling, JSON helpers (search, receivable picker,
  phone check, bell feed).
* Scheduler (Asia/Dhaka): queue drain, `crm:safety-check` (5 min), `accounting:daily` (02:10), `backup:database`
  (03:00), `notify:due` (08:30), `crm:digest` (08:35), `notify:period-close` (25th 10:00).
* Pages: Login / Forgot / Reset are real forms; the other 36 pages render a data scaffold until Phase 3.

## Default accounts (change after first login)
| Email | Role | Password |
|---|---|---|
| `ADMIN_EMAIL` (admin@auxtech.local) | owner | `ADMIN_PASSWORD` (ChangeMe!2026) |
| abrar@auxtech.local, rizwan@auxtech.local | caller | same as ADMIN_PASSWORD |

## Left for later phases
* Phase 3 frontend (all pages beyond the scaffold, charts, Kanban UI, print/PDF views, bell dropdown).
* Phase 4 features (bank reconciliation, budgets, forecast, recurring expenses, CSV import/export, 2FA).
* Real SMTP credentials in `.env` (mail currently uses the configured mailer; tests use `array`).
