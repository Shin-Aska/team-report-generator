#!/usr/bin/env sh
set -e

# Optional: prewarm Laravel caches and run migrations
# Controlled via env vars to avoid surprises in dev.
# CACHE_PREWARM=true to run config/route/view caches
# RUN_MIGRATIONS=true to run php artisan migrate --force

wait_for_database() {
  if [ -z "${DB_HOST}" ]; then
    return
  fi

  ATTEMPTS=0
  until php -r "try {
    new PDO(
      'mysql:host='.(getenv('DB_HOST') ?: '127.0.0.1').
      ';port='.(getenv('DB_PORT') ?: 3306).
      ';dbname='.(getenv('DB_DATABASE') ?: ''),
      getenv('DB_USERNAME') ?: '',
      getenv('DB_PASSWORD') ?: '',
      [PDO::ATTR_TIMEOUT => 2]
    );
    exit(0);
  } catch (Throwable \$e) { exit(1); }" >/dev/null 2>&1; do
    ATTEMPTS=$((ATTEMPTS + 1))
    if [ "${ATTEMPTS}" -ge 15 ]; then
      echo "Database is still unavailable after waiting; continuing startup."
      break
    fi
    echo "Waiting for database... (${ATTEMPTS}/15)"
    sleep 2
  done
}

cd /var/www/html

# Default to php-fpm unless a custom command is provided (e.g., queue/scheduler)
if [ "$#" -eq 0 ]; then
  set -- php-fpm
fi

if [ "${APP_ENV}" = "production" ] && [ "${CACHE_PREWARM}" = "true" ]; then
  php artisan config:clear || true
  php artisan config:cache || true
  php artisan route:cache || true
  php artisan view:cache || true
fi

if [ "${RUN_MIGRATIONS}" = "true" ]; then
  wait_for_database
  php artisan migrate --force || true
fi

# Finally start PHP-FPM or the supplied command
exec docker-php-entrypoint "$@"
