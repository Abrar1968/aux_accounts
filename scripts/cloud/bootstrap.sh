#!/usr/bin/env bash
# Run from the repository root inside a cloud session: installs deps, builds assets, migrates + seeds.
set -euo pipefail
cd "$(dirname "$0")/../.."
bash scripts/cloud/setup.sh
export COMPOSER_ALLOW_SUPERUSER=1
[ -d vendor ] || composer install --no-interaction --prefer-dist
[ -d node_modules ] || npm ci --no-audit --no-fund
[ -f .env ] || { cp .env.example .env; php artisan key:generate; }
npm run build
php artisan migrate --force
echo "Auxtech Ops bootstrapped"
