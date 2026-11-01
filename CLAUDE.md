# Auxtech Ops — agent guide

Accrual accounting + Lead CRM replacing two Google-Sheets workbooks. **Read `docs/` before coding**
(start with `docs/00-README.md`). The workbook rules in `docs/01-workbook-analysis.md` are the source of truth.

## Stack (fixed)
- Laravel 12, **PHP 8.2-compatible code** (`composer.json` platform pin 8.2.12 — no PHP 8.3+ syntax).
- MySQL/MariaDB (user runs XAMPP locally, DB `auxtech_ops`, root, empty password). Tests use SQLite memory.
- Inertia.js v2 + Vue 3 `<script setup>` + Tailwind CSS v4 (`@tailwindcss/vite`), Vite 7.
- Only third-party packages allowed: `inertiajs/inertia-laravel`, `@inertiajs/vue3`, `pusher/pusher-php-server`,
  `pusher-js` (+ first-party `laravel-echo`). No UI kits, chart/icon/date libs, Ziggy, dompdf, Intervention.
- Images via PHP **GD**; PDFs in the **browser** (print views / in-house `resources/js/lib/pdf.js`).
- Pusher app 2208649, cluster ap2. Secret only in `.env` / environment variables — never commit it.

## Architecture rules
- Controller → FormRequest → **one Service call** → Repository interface → Model. Controllers return
  `Inertia::render()` or redirect with `->with('toast', [...])`.
- Business rules and calculations only in `app/Services`; pure formula ports in `app/Services/Accounting/Engine`.
- Money via `App\Support\Money` (integer paisa), months via `App\Support\Period` (first-of-month, `cut()` = next month).
- Every write checks `PeriodService::assertOpen()`; postings are idempotent via `request_id`.
- Bind new repositories in `App\Providers\RepositoryServiceProvider`.
- Golden test `tests/Fixtures/golden_monthly_statements.json` must keep passing — fix services, never the fixture.

## Commands
- Cloud session setup: `bash scripts/cloud/bootstrap.sh` (MariaDB + deps + build + migrate).
- Tests: `php artisan test` · Build: `npm run build` · Seed real data: `php artisan migrate:fresh --seed`.
- The cloud environment injects `DB_*`/`MAIL_*` env vars that override `.env`; `phpunit.xml` forces test values.

## Conventions
- PSR-12 (`vendor/bin/pint`), typed signatures, docblocks referencing the workbook origin.
- Frontend: tokens in `resources/css/app.css`, components in `resources/js/Components`, pages in
  `resources/js/Pages/<Module>`, must work at 360 px, light + dark.
- Commit in logical steps; never commit `.env`, `vendor/`, `node_modules/`.
