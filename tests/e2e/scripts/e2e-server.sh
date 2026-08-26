#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../../.."   # repo root
# APP_ENV=e2e (not "testing"): LoadEnvironmentVariables redirects dotenv to
# .env.{APP_ENV}, and `php artisan serve` strips non-passthrough vars
# (APP_KEY, SESSION_DRIVER, ...) from its workers whenever a .env exists.
# Under APP_ENV=testing the worker booted from .env.testing — deliberately
# key-less (pest safety file) — so every page 500'd with
# MissingAppKeyException. .env.e2e is self-sufficient instead; pest keeps
# using .env.testing via phpunit.xml and is unaffected.
export APP_ENV=e2e
export APP_DEBUG=false
export APP_URL="http://127.0.0.1:${ET_E2E_PORT:-8015}"
export APP_LOCALE=id            # production parity — real label widths
export APP_FALLBACK_LOCALE=id
# APP_KEY lives in .env.e2e (single source of truth). The old per-boot
# random export was stripped by `php artisan serve` before workers saw it,
# which is how the MissingAppKeyException boot loop stayed hidden.
export DB_CONNECTION=sqlite
export DB_DATABASE="database/e2e.sqlite"
export SESSION_DRIVER=database
export CACHE_STORE=array
export QUEUE_CONNECTION=sync
export MAIL_MAILER=log
export BCRYPT_ROUNDS=4

# Host PHP (nova) lacks pdo_sqlite — run the Laravel server inside the
# expense-tracker container instead. Same env, same port, file-backed sqlite
# shared through the worktree bind mount.
if php -m 2>/dev/null | grep -qi 'pdo_sqlite'; then
  php artisan migrate:fresh --seed --seeder=E2ESeeder --force
  exec php artisan serve --host=127.0.0.1 --port="${ET_E2E_PORT:-8015}"
else
  # Deterministic name + forced replace: playwright kills this docker CLIENT
  # on teardown, but the container itself survives — without this, every
  # suite run leaks a server and the next run dies on "port already used".
  E2E_CONTAINER="et-e2e-server-${ET_E2E_PORT:-8015}"
  docker rm -f "$E2E_CONTAINER" >/dev/null 2>&1 || true
  exec docker run --rm \
    --name "$E2E_CONTAINER" \
    -v "$PWD":/app \
    -w /app \
    -p "127.0.0.1:${ET_E2E_PORT:-8015}:8015" \
    -e APP_ENV=e2e \
    -e APP_DEBUG=false \
    -e APP_URL="http://127.0.0.1:${ET_E2E_PORT:-8015}" \
    -e APP_LOCALE=id \
    -e APP_FALLBACK_LOCALE=id \
    -e DB_CONNECTION=sqlite \
    -e DB_DATABASE=database/e2e.sqlite \
    -e SESSION_DRIVER=database \
    -e CACHE_STORE=array \
    -e QUEUE_CONNECTION=sync \
    -e MAIL_MAILER=log \
    -e BCRYPT_ROUNDS=4 \
    --entrypoint sh expense-tracker:latest-fpm -c '
      php artisan migrate:fresh --seed --seeder=E2ESeeder --force
      exec php artisan serve --host=0.0.0.0 --port=8015
    '
fi
