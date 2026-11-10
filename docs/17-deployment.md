# 17 — Production Deployment (cPanel · accounts.auxtechsoftware.com)

The release zip is self-contained:
- `vendor/` is installed for PHP 8.2 without dev packages.
- `public/build/` holds the compiled frontend.
- A configured `.env` is included.
- Node.js and Composer are **not** needed on the server.

| Item | Value |
|---|---|
| URL | https://accounts.auxtechsoftware.com |
| Project folder | `/home/auxtechs/accounts.auxtechsoftware.com` |
| PHP binary (cPanel EA4) | `/usr/local/bin/ea-php82` |
| Timezone | Asia/Dhaka (fixed in `config/app.php`; MySQL session `+06:00`) |
| SMTP | `accounts.auxtechsoftware.com:465` (SSL/TLS), user `admin@accounts.auxtechsoftware.com` |
| Realtime | Pusher app `2208649`, cluster `ap2` |

## 1. One-time cPanel preparation
1. **PHP version**: open cPanel → *MultiPHP Manager*. Set `accounts.auxtechsoftware.com` to **PHP 8.2**.
2. **PHP extensions**: open *MultiPHP INI Editor* or *Select PHP Version → Extensions*. Make sure these are on:
   - `gd`, `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `zip`, `intl`, `exif`
3. **Document root**: already set to `/home/auxtechs/accounts.auxtechsoftware.com/public` when the subdomain was created (correct). To check it: *Domains* → `accounts.auxtechsoftware.com` → *Manage*.
   - Set the document root to `/home/auxtechs/accounts.auxtechsoftware.com/public`.
   - If you leave it as the project folder instead, the root `.htaccess` in the zip forwards requests into `public/` and blocks access to `.env`, `vendor/`, `storage/`, etc.
4. **Database**: open *MySQL® Databases*.
   1. Create the database `auxtechs_accounts`.
   2. Create the user `auxtechs_accounts` with a strong password.
   3. Add the user to the database with **ALL PRIVILEGES**.
   4. If you choose different names, edit `DB_*` in `.env`.
5. **SSL**: in *SSL/TLS Status*, run AutoSSL for the subdomain so `https://` works.

## 2. Upload
1. In *File Manager*, open `/home/auxtechs/accounts.auxtechsoftware.com`.
2. Upload `auxtech-accounts-production.zip` and choose **Extract**.
3. Turn on *Settings → Show Hidden Files*, so you can see `.env` and `.htaccess`.
4. Edit `.env` and fill in the two values the package cannot know:
   - `DB_PASSWORD=` the MySQL user password from step 1.4.
   - `MAIL_PASSWORD=` the password of the `admin@accounts.auxtechsoftware.com` mailbox.

## 3. Terminal commands (cPanel → Terminal)
Run these one after another:

```bash
cd ~/accounts.auxtechsoftware.com
```
```bash
chmod -R 775 storage bootstrap/cache
```
```bash
/usr/local/bin/ea-php82 artisan migrate --seed --force
```
```bash
/usr/local/bin/ea-php82 artisan optimize
```
```bash
/usr/local/bin/ea-php82 artisan ops:check --mail=admin@accounts.auxtechsoftware.com
```

What these do:
- `migrate --seed` creates the tables and loads the real workbook data: 18 projects, 142 leads, all ledgers, and the users.
  Run it **once only**, on the first deploy.
- `optimize` caches config, routes, views and events. **Re-run it after every `.env` change.**
- `ops:check` is the smoke test (see §7). It also sends a test email.

On later updates (new zip):
- Keep `.env`.
- Run `/usr/local/bin/ea-php82 artisan migrate --force`, then `/usr/local/bin/ea-php82 artisan optimize`.
- Never run `--seed` or `migrate:fresh` on the live database.

## 4. Cron job (cPanel → Cron Jobs → Add, "Once Per Minute")
```bash
* * * * * cd /home/auxtechs/accounts.auxtechsoftware.com && /usr/local/bin/ea-php82 artisan schedule:run >> /dev/null 2>&1
```
This single line runs everything. A separate `queue:work` cron is **not** needed, because the scheduler drains the mail queue every minute.

| When (Asia/Dhaka) | Job |
|---|---|
| every minute | `deploy:auto-migrate` — applies new database migrations from an uploaded update zip (no-op otherwise) |
| every minute | `queue:work --stop-when-empty` — sends queued emails / notifications |
| every 5 min | `crm:safety-check` — automatic daily / monthly / yearly CRM reports |
| 02:10 daily | `accounting:daily` — revenue recognition, Siam share, partnership rows, recurring bills, controls |
| 03:00 daily | `backup:database` — gzipped SQL backup, 30 days kept |
| 08:30 / 08:35 | `notify:due` (follow-ups, meetings, overdue A/R, payroll) and `crm:digest` |
| 25th 10:00 | `notify:period-close` — month-end checklist |

## 5. Environment reference (`.env` on the host)
```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://accounts.auxtechsoftware.com
DB_DATABASE=auxtechs_accounts
DB_USERNAME=auxtechs_accounts
DB_PASSWORD=…

MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=accounts.auxtechsoftware.com
MAIL_PORT=465
MAIL_USERNAME=admin@accounts.auxtechsoftware.com
MAIL_PASSWORD=…
MAIL_FROM_ADDRESS=admin@accounts.auxtechsoftware.com
MAIL_FROM_NAME="Auxtech Software"

BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=2208649
PUSHER_APP_KEY=a91f1ba8822616d04f60
PUSHER_APP_SECRET=…   (in the zip's .env only, never in git)
PUSHER_APP_CLUSTER=ap2

SESSION_SECURE_COOKIE=true
ADMIN_EMAIL=admin@accounts.auxtechsoftware.com
ADMIN_PASSWORD=…      (initial owner password; change it after first login)
```
After any `.env` edit, run `/usr/local/bin/ea-php82 artisan optimize`.

**Pusher dashboard** (app 2208649 → App settings):
- Keep *Client events* off.
- Keep *Authorised connections* on.
- Add `https://accounts.auxtechsoftware.com` to the allowed origins.

## 6. First login
- Sign in at https://accounts.auxtechsoftware.com with `ADMIN_EMAIL` / `ADMIN_PASSWORD` from `.env`. This is the **Owner** role, which has every permission.
- The CRM callers `abrar@auxtech.local` and `rizwan@auxtech.local` use the same initial password.
- Right after the first login:
  1. **Profile** → change your password.
  2. **Users & roles** → give the callers real emails and their own passwords.
  3. **Settings → Branding** → upload the company logo (it also becomes the browser favicon) and the accounts seal.
  4. **Settings → Policies** → check the company name, address and phone used on documents and emails.

## 7. Smoke test (`ops:check` plus 10 minutes of clicking)
`ops:check` must print **All checks passed**. It verifies:
- PHP 8.2+ and the required extensions;
- `APP_KEY`, and debug turned off;
- the DB connection and the migrations;
- the seed counts;
- **balance check = 0 for every month**;
- writable storage and the built assets;
- the Pusher and SMTP configuration;
- the scheduler heartbeat, plus the test email.

Then in the browser:
1. Login → the dashboard shows Oct-2026 figures. In Statements → Oct 2026, the overdraft is ৳62,160 and the balance check is ৳0.00.
2. Receivables → Shaown legacy portfolio → the statement shows a balance of ৳56,200 and a **PARTIALLY PAID** watermark. PDF downloads with the logo and seal.
3. Projects → New project, with two referrers "Rizwan, Abrar" and a client signature. Open its **Invoice**: it shows **UNPAID** and both signatures. The referral is split 50/50.
4. Customer payments → Record receipt → the toast appears, the bell gets a notification, and clicking the notification opens the receipt row.
5. Expenses → pay an instalment, then try an overpayment → the overpayment is rejected.
6. Send email → Payment reminder → pick a customer → send it to yourself → it arrives from admin@accounts.auxtechsoftware.com with the logo.
7. Open the app in two browsers → record a payment in one → the other refreshes live (Pusher).
8. Phone (360 px): the menu, a lead call log, and dark mode.
9. The next morning: System → Automation shows runs of `accounting:daily` (02:10) and `backup:database` (03:00).

## 8. Backups and restore
- **Automatic**: every day at 03:00, a backup is written to `storage/app/private/backups/auxtech-YYYYmmdd-His.sql.gz`. Backups are kept for 30 days.
- **Manual backup at any time**:
```bash
cd ~/accounts.auxtechsoftware.com && /usr/local/bin/ea-php82 artisan backup:database
```
- **Off-site**: download the newest `.sql.gz` weekly (File Manager → right-click → Download). Also download the uploaded images in `storage/app/private/`:
  - `branding/` (logo, seal);
  - `signatures/` (client signatures);
  - `attachments/` (receipts).
  The cPanel *Backup Wizard → Home Directory* covers all of these.
- **Restore** (replaces the current data):
  1. Optional: put the site in maintenance mode with `/usr/local/bin/ea-php82 artisan down`.
  2. Import the backup in **either** of two ways:
     - phpMyAdmin → database `auxtechs_accounts` → *Import* → choose the `.sql.gz` file → *Go*. phpMyAdmin reads gzip directly; the file drops and recreates each table.
     - Terminal:
```bash
gunzip -c storage/app/private/backups/auxtech-YYYYmmdd-His.sql.gz | mysql -u auxtechs_accounts -p auxtechs_accounts
```
  3. Restore the images by copying the `branding/`, `signatures/` and `attachments/` folders back into `storage/app/private/`.
  4. Run `/usr/local/bin/ea-php82 artisan optimize:clear && /usr/local/bin/ea-php82 artisan optimize && /usr/local/bin/ea-php82 artisan up`.
  5. Run `ops:check` again. It must show *Balance check = 0*.

## 9. Troubleshooting
| Symptom | Fix |
|---|---|
| 500 error right after upload | `storage/` or `bootstrap/cache` is not writable → `chmod -R 775 storage bootstrap/cache`; check `storage/logs/laravel.log` |
| "Vite manifest not found" | `public/build/` was not extracted; re-extract the zip |
| Page loads but URLs/emails use http:// | `APP_URL` must be `https://…`; then run `artisan optimize` |
| Emails not arriving | `ops:check --mail=you@…` shows the SMTP error; check `MAIL_PASSWORD`, port 465 + `MAIL_SCHEME=smtps`; check the cron line exists (the queue is drained by the scheduler) |
| Realtime toasts missing | `PUSHER_APP_SECRET` must be set in `.env`; the domain must be allowed in the Pusher dashboard |
| Changed `.env` but nothing changed | Run `/usr/local/bin/ea-php82 artisan optimize` (config is cached) |
| Logo / seal not showing | Upload again in Settings → Branding. Images are stored in `storage/app/private/branding` (no `storage:link` needed) |
