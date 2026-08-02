#!/bin/sh
set -e

# Run pending migrations (idempotent — safe on every boot).
php artisan migrate --force

# Clear + repopulate caches in case the image was built without them.
php artisan config:cache
php artisan route:cache

exec "$@"
