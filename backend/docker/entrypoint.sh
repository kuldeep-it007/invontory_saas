#!/usr/bin/env bash
# Backend container entrypoint.
#
# Shared by the API and the queue-worker containers. It:
#   1) ensures a .env exists and has an APP_KEY,
#   2) waits for the MySQL service to accept connections,
#   3) runs migrations + seed ONCE (only the API container, flagged by RUN_SETUP),
#   4) execs the container command (serve or queue:work).
#
# Idempotent: safe to run on every container start/restart.

set -euo pipefail

cd /app

# 0) Drop any stale cached manifests baked into the image. A cached
#    bootstrap/cache/packages.php may reference dev-only providers (Laravel
#    Pail) that are absent from the --no-dev vendor tree and would crash every
#    artisan call. Removing them forces a clean rediscovery below.
rm -f bootstrap/cache/packages.php bootstrap/cache/services.php \
      bootstrap/cache/config.php bootstrap/cache/routes-*.php 2>/dev/null || true

# 1) Environment file + app key.
if [ ! -f .env ]; then
  cp .env.example .env
  echo "[entrypoint] created .env from template"
fi

# 1a) Align .env with the container's runtime environment.
#     The template ships localhost defaults (DB_HOST=127.0.0.1), but inside the
#     compose network the services are reachable by their service names
#     (mysql, redis). `php artisan serve` loads .env into a long-lived worker,
#     and the tenancy identification middleware rebuilds the central connection
#     from that config - so the FILE must carry the correct hosts, not just the
#     process environment. Rewrite the relevant keys in place from env vars.
set_env() {
  key="$1"; val="$2"
  [ -z "$val" ] && return 0
  if grep -q "^${key}=" .env; then
    # Escape ampersands/slashes for sed replacement safety.
    esc=$(printf '%s' "$val" | sed -e 's/[\/&]/\\&/g')
    sed -i "s/^${key}=.*/${key}=${esc}/" .env
  else
    printf '%s=%s\n' "$key" "$val" >> .env
  fi
}
set_env DB_HOST       "${DB_HOST:-}"
set_env DB_PORT       "${DB_PORT:-}"
set_env DB_DATABASE   "${DB_DATABASE:-}"
set_env DB_USERNAME   "${DB_USERNAME:-}"
set_env DB_PASSWORD   "${DB_PASSWORD:-}"
set_env REDIS_HOST    "${REDIS_HOST:-}"
set_env REDIS_PORT    "${REDIS_PORT:-}"
set_env QUEUE_CONNECTION "${QUEUE_CONNECTION:-}"
set_env CACHE_STORE   "${CACHE_STORE:-}"
set_env APP_URL       "${APP_URL:-}"
echo "[entrypoint] synced .env DB/Redis hosts from container environment"

if ! grep -q '^APP_KEY=base64:' .env; then
  php artisan key:generate --force
  echo "[entrypoint] generated APP_KEY"
fi

# 2) Wait for MySQL (host/port from env, defaulting to the compose service).
DB_HOST="${DB_HOST:-mysql}"
DB_PORT="${DB_PORT:-3306}"
echo "[entrypoint] waiting for MySQL at ${DB_HOST}:${DB_PORT}..."
until mysqladmin ping -h"${DB_HOST}" -P"${DB_PORT}" --silent 2>/dev/null; do
  sleep 2
done
echo "[entrypoint] MySQL is up"

# 3) Migrate + seed once (the API container sets RUN_SETUP=true in compose).
if [ "${RUN_SETUP:-false}" = "true" ]; then
  echo "[entrypoint] running migrations + seed"
  php artisan migrate --force
  php artisan db:seed --force || echo "[entrypoint] seed skipped/failed (continuing)"
  php artisan config:clear
fi

# 4) Hand off to the container command.
exec "$@"
