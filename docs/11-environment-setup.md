# 11 — Environment Setup

Three environments: **local (Windows + XAMPP)**, **cloud agent environment "garp"** (where Claude Code
sessions build the project), and **production hosting** (cron + SMTP).

## 1. Local — Windows + XAMPP (PHP 8.2.12, MySQL/MariaDB)

Prerequisites: XAMPP 8.2.x (Apache optional, **MySQL** module running), Composer 2, Node.js 20+ (only for
building assets), Git.

1. Enable PHP extensions in `C:\xampp\php\php.ini`: `extension=gd`, `extension=zip`, `extension=intl`,
   `extension=mbstring`, `extension=fileinfo`, `extension=pdo_mysql`, `extension=openssl`, `extension=exif`.
   Restart XAMPP.
2. Create the database (phpMyAdmin → New → `auxtech_ops`, collation `utf8mb4_unicode_ci`) or:
```bash
C:\xampp\mysql\bin\mysql -u root -e "CREATE DATABASE auxtech_ops CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
```
3. Install and configure:
```bash
git clone https://github.com/abrar1968/aux_accounts.git && cd aux_accounts
```
```bash
composer install
```
```bash
npm ci
```
```bash
copy .env.example .env
```
```bash
php artisan key:generate
```
   Then edit `.env`: `PUSHER_APP_SECRET=<your secret>` (app id `2208649`, key and cluster `ap2` are already
   filled in), SMTP values, `ADMIN_EMAIL` / `ADMIN_PASSWORD`.
4. Migrate + seed the real workbook data:
```bash
php artisan migrate:fresh --seed
```
5. Run (three terminals):
```bash
php artisan serve
```
```bash
npm run dev
```
```bash
php artisan schedule:work
```
   Open http://localhost:8000. Tests: `php artisan test`.

### `.env` reference (local)
```
APP_NAME="Auxtech Ops"          APP_TIMEZONE=Asia/Dhaka        APP_URL=http://localhost:8000
DB_CONNECTION=mysql  DB_HOST=127.0.0.1  DB_PORT=3306  DB_DATABASE=auxtech_ops  DB_USERNAME=root  DB_PASSWORD=
SESSION_DRIVER=database  CACHE_STORE=database  QUEUE_CONNECTION=database
BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=2208649  PUSHER_APP_KEY=a91f1ba8822616d04f60  PUSHER_APP_SECRET=***  PUSHER_APP_CLUSTER=ap2
VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"  VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
MAIL_MAILER=smtp  MAIL_HOST=…  MAIL_PORT=465  MAIL_SCHEME=smtps  MAIL_USERNAME=…  MAIL_PASSWORD=…
```
Troubleshooting: *SQLSTATE[HY000] [2002]* → MySQL not started in XAMPP; *could not find driver* →
`pdo_mysql` disabled; *GD not found* → `extension=gd`; Vite manifest missing → run `npm run build` or `npm run dev`.

## 2. Cloud agent environment "garp" (Claude Code on the web)

The environment injects its own variables (`DB_DATABASE=garp`, `MAIL_MAILER=log`, `APP_NAME=Laravel`, …).
**Process variables override `.env`**, so set them to match this project. Edit them in the environment
settings (cloud environment menu in the session title bar → Edit):

**Environment variables** (replace the Laravel defaults):
```
APP_NAME="Auxtech Ops"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_TIMEZONE=Asia/Dhaka
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=auxtech_ops
DB_USERNAME=root
DB_PASSWORD=
BROADCAST_CONNECTION=pusher
QUEUE_CONNECTION=database
SESSION_DRIVER=database
CACHE_STORE=database
MAIL_MAILER=log
PUSHER_APP_ID=2208649
PUSHER_APP_KEY=a91f1ba8822616d04f60
PUSHER_APP_CLUSTER=ap2
PUSHER_APP_SECRET=<add your secret here as a variable/secret — never in chat or git>
```

**Setup script**: paste the contents of `scripts/cloud/setup.sh` (installs MariaDB, starts it, root with
empty password like XAMPP, creates `auxtech_ops` and `garp` databases). Inside a session the agent runs
`bash scripts/cloud/bootstrap.sh` (composer/npm install, build, migrate).

**Network**: the environment must reach `repo.packagist.org`, `api.github.com`/`codeload.github.com`
(Composer dists), `registry.npmjs.org`, and `api-ap2.pusher.com` (realtime tests). All were reachable
during setup.

## 3. Production hosting
See **[17-deployment.md](17-deployment.md)** for the cPanel deployment of accounts.auxtechsoftware.com:
- the release zip;
- the exact terminal commands and the cron line;
- SMTP / Pusher env;
- backup and restore;
- the smoke test.
