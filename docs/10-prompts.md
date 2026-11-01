# 10 — Prompts

Copy one prompt per session. Each assumes the agent reads `CLAUDE.md` and `docs/` first.

## Prompt A — Complete the full backend in one go (Phase 1 + 2)

```
Read CLAUDE.md and every file in docs/ (01 → 12) before writing code. Then complete the ENTIRE backend of
Auxtech Ops (Laravel 12, PHP 8.2-compatible, MySQL/MariaDB, Inertia v2) exactly as specified:

1. Seeders: RoleSeeder (owner, accountant, sales_manager, caller, viewer — docs/05 §4), UserSeeder (admin from
   ADMIN_* env + Abrar, Rizwan as callers), SettingsSeeder (SettingsService::DEFAULTS + workbook Settings),
   AccountingWorkbookSeeder and CrmWorkbookSeeder loading database/seeders/data/**.json using the mapping in
   docs/12-data-migration.md. Mute auditing while seeding. Bump every SequenceService counter past imported IDs.
2. Make tests/Feature/GoldenStatementsTest.php pass: every line, every month Apr–Nov 2026 must equal
   tests/Fixtures/golden_monthly_statements.json and balance check must be 0. Fix the services, never the fixture.
3. Finish/verify all accounting services in app/Services/Accounting (docs/01 §2 rules, docs/03 §3).
4. Implement CRM services: PhoneNormalizer, LeadComputer, LeadService, CallLogService (idempotent),
   calls-made delta posting, DailyAllocationService, CrmDashboardService, CrmReportService (SHA-256 dedupe,
   daily/monthly/yearly cursors), AnalyticsService. CRM golden: 142 leads, 93 active, 0 won.
5. Auth (session login/logout/password reset via SMTP), roles/permissions middleware, policies.
6. Thin controllers + FormRequests + Resources + routes/web.php for every route in docs/05, returning
   Inertia::render with array props (pages may be minimal placeholders), writes redirect back with toast flash.
7. Notifications per docs/07: notification classes (database + broadcast + mail), NotificationService with
   preferences, notifications centre routes, scheduled commands in routes/console.php (docs/03 §5):
   accounting:daily, crm:safety-check, notify:due, crm:digest, notify:period-close, backup:database.
8. ImageService (GD only) + AttachmentService + signed downloads. AuditService everywhere. Period close/reopen.
9. Tests per docs/08 (unit, service, golden, HTTP, notifications, commands). All green with `php artisan test`;
   also run them against MariaDB once.
Constraints: service–repository architecture (controllers call one service; services use repository
interfaces), no business logic in controllers/models, money via App\Support\Money, months via App\Support\Period,
only third-party packages allowed: inertiajs/inertia-laravel and pusher/pusher-php-server. Commit in logical
steps with clear messages and push to the working branch. Report what passed, what is left, and any data
discrepancy found in the workbook.
```

## Prompt B — Complete the frontend (Phase 3)

```
Read CLAUDE.md, docs/05, docs/06 and docs/07. Build the complete Inertia + Vue 3 + Tailwind v4 frontend:
component library (docs/06 §4), every page in docs/06 §5 wired to the existing controllers/props, pure-SVG
charts, Kanban pipeline with keyboard alternative, Ctrl K command palette using /ajax/search, notification bell +
centre + realtime toasts via Echo/Pusher, print/PDF views (no PDF library) for statements, payslips and CRM
reports, dark mode, and fully responsive layouts (360 px phones: card lists, full-screen sheets, bottom action
bars). No UI kits, icon packs or chart libraries. Keep pages thin: logic in composables, formatting in
resources/js/lib/format.js. `npm run build` must pass; check every page at 360, 768 and 1440 px in a headless
browser and fix overflow. Commit and push.
```

## Prompt C — New features (Phase 4)

```
Read CLAUDE.md and docs/02 (FR marked "new"). Implement, with migrations, services, repositories, pages and
tests: bank accounts & reconciliation, budgets vs actual, 90-day cash-flow forecast, recurring expense templates,
payslips, A/R aging + customer statement PDF, caller targets & leaderboard, lead CSV import with duplicate
preview, Won lead → Project conversion. Keep golden tests green. Commit and push.
```

## Prompt D — Deployment (Phase 5)

```
Read docs/11-environment-setup.md. Prepare production: optimise (config/route/view cache), build assets,
document the exact cron line, SMTP and Pusher env for the host, backup command and restore steps, and a smoke-test
checklist. Do not commit secrets.
```
