# 09 — Implementation Phases

| Phase | Scope | Exit criteria |
|---|---|---|
| **0 — Foundation (DONE)** | Laravel 12 + Inertia v2 + Vue 3 + Tailwind v4 + Echo/Pusher scaffold, PHP 8.2 platform pin, MySQL `.env`, responsive AppLayout + toasts, migrations (verified on MariaDB), models, repository layer, Support (Money, Period), shared services, engine calculators, draft accounting services, workbook data exported to JSON seeds, golden fixture, docs | `npm run build` ✓, `php artisan migrate:fresh` on MariaDB ✓, Pusher test event ✓ |
| **1 — Backend core (DONE)** | finish & verify accounting services; CRM services (PhoneNormalizer, LeadComputer, LeadService, CallLogService, DailyAllocation, CrmReport, Analytics); seeders (roles, users, settings, accounting workbook, CRM workbook); auth; controllers + FormRequests + Resources + routes for every module; policies/permissions; notifications + preferences + scheduled commands; GD ImageService + attachments; audit; period close | golden test passes for Apr–Nov 2026; all service/HTTP tests green; `php artisan schedule:list` shows jobs |
| **2 — Hardening** | idempotency everywhere, concurrency locks, closed-period guards, CSV export, global search, error pages, rate limits, security review, MariaDB CI job | 85 % service coverage, security checklist done |
| **3 — Frontend** | component library, all pages of 06 §5, charts, Kanban, command palette, print/PDF, notification centre, dark mode, mobile layouts | Lighthouse ≥ 90, manual QA checklist passes on phone + desktop |
| **4 — New features** | bank accounts & reconciliation, budgets vs actual, cash-flow forecast, recurring expenses, payslips, A/R aging & customer statements, caller targets/leaderboard, lead CSV import, Won → Project conversion | each feature has tests + docs |
| **5 — Deploy** | hosting setup (cron, SMTP, Pusher), `npm run build` committed artefacts or CI deploy, backups, monitoring of automation runs | production smoke test, first automatic month (Dec-2026) created by cron |

**Phase 1 result:** 49 tests / 2,319 assertions green on SQLite and MariaDB; golden statements 400/400 values;
CRM snapshot exact; 107 routes; 6 scheduled commands. See [13-backend-status.md](13-backend-status.md).

Order inside Phase 1 (dependency-driven): Seeders → golden test green → remaining accounting services →
CRM services → auth/roles → controllers/routes → notifications/scheduler → attachments.
