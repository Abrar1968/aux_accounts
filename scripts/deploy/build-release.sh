#!/usr/bin/env bash
# Builds a self-contained production zip (vendor + built assets, no dev deps, no tests).
#   bash scripts/deploy/build-release.sh <path-to-production.env> [output.zip]
# The .env is supplied from outside the repo so secrets are never committed.
set -euo pipefail
ENV_FILE=${1:?usage: build-release.sh <production.env> [output.zip]}
OUT=$(realpath -m "${2:-auxtech-accounts-production.zip}")
ROOT=$(cd "$(dirname "$0")/../.." && pwd)
STAGE=$(mktemp -d)/release
mkdir -p "$STAGE"

echo "→ exporting committed files"
git -C "$ROOT" archive HEAD | tar -x -C "$STAGE"

echo "→ building frontend assets"
(cd "$ROOT" && npm run build >/dev/null)
cp -r "$ROOT/public/build" "$STAGE/public/build"

echo "→ installing production PHP dependencies (platform PHP 8.2)"
(cd "$STAGE" && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress --quiet)
# packages installed from source carry their whole git history — not needed at runtime
find "$STAGE/vendor" -type d -name .git -prune -exec rm -rf {} +

echo "→ trimming dev-only files"
rm -rf "$STAGE"/{tests,.github,.claude,scripts/cloud,node_modules,phpunit.xml,.editorconfig,.gitattributes,CLAUDE.md}
rm -f "$STAGE"/bootstrap/cache/*.php   # caches are built on the server (paths differ)

cp "$ENV_FILE" "$STAGE/.env"
cp "$ROOT/docs/17-deployment.md" "$STAGE/DEPLOY.md"
chmod -R 775 "$STAGE/storage" "$STAGE/bootstrap/cache"

echo "→ zipping"
rm -f "$OUT"
(cd "$STAGE" && zip -qr -X "$OUT" . -x '*.DS_Store')
echo "✓ $(du -h "$OUT" | cut -f1)  $OUT"
