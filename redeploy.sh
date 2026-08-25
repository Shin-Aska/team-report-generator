#!/usr/bin/env sh
# Deploys (first time) or redeploys the stack with the latest code and src/.env.
# Rebuilds app/web images, recreates containers, generates APP_KEY if empty,
# clears stale Laravel caches, runs migrations, restarts web last (nginx IP).
#
# Usage:
#   sh redeploy.sh            # build + recreate + migrate
#   sh redeploy.sh --seed     # also seed demo data (needed on first deploy)
#   sh redeploy.sh --skip-build   # skip image rebuild (env/config-only change)

set -e
cd "$(dirname "$0")"

SEED=0
SKIP_BUILD=0
for arg in "$@"; do
  case "$arg" in
    --seed) SEED=1 ;;
    --skip-build) SKIP_BUILD=1 ;;
  esac
done

if command -v podman >/dev/null 2>&1; then
  ENGINE=podman
elif command -v docker >/dev/null 2>&1; then
  ENGINE=docker
else
  echo "Error: podman or docker is required." >&2
  exit 1
fi
echo "Using: $ENGINE compose"

if [ ! -f src/.env ]; then
  echo "Error: src/.env not found. Copy src/.env.example to src/.env first." >&2
  exit 1
fi

if grep -qE '^APP_KEY=\s*$' src/.env; then
  echo "\n==> APP_KEY is empty - generating one..."
  KEY="base64:$(head -c 32 /dev/urandom | base64)"
  if [ "$(uname)" = "Darwin" ]; then
    sed -i '' "s/^APP_KEY=.*/APP_KEY=$KEY/" src/.env
  else
    sed -i "s/^APP_KEY=.*/APP_KEY=$KEY/" src/.env
  fi
fi

if [ "$SKIP_BUILD" -eq 0 ]; then
  echo "\n==> Building images (app, web)..."
  $ENGINE compose build app web
fi

echo "\n==> Clearing stale Laravel caches (host-side, bind-mounted)..."
rm -f src/bootstrap/cache/*.php

echo "\n==> Recreating containers with current .env..."
$ENGINE compose up -d --force-recreate

echo "\n==> Discovering packages and caching config..."
$ENGINE compose exec -T app sh -c "php artisan package:discover --ansi >/dev/null 2>&1; php artisan config:clear && php artisan config:cache" >/dev/null

echo "\n==> Running migrations..."
$ENGINE compose exec -T app php artisan migrate --force || echo "Warning: migrate reported an error."

if [ "$SEED" -eq 1 ]; then
  echo "\n==> Seeding demo data..."
  $ENGINE compose exec -T app php artisan db:seed --force
fi

echo "\n==> Restarting web last (nginx re-resolves app IP)..."
$ENGINE compose restart web >/dev/null
sleep 3

echo "\n==> Status:"
$ENGINE compose ps
echo "\nDone. App: http://localhost:8080"
