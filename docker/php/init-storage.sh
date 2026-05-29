#!/bin/sh
set -eu

mkdir -p \
  /app/storage/framework/cache/installation/data \
  /app/storage/framework/sessions \
  /app/storage/framework/views \
  /app/storage/logs \
  /app/bootstrap/cache

if [ "${LEAN_DB_DEFAULT_CONNECTION:-${DB_CONNECTION:-sqlite}}" = "sqlite" ]; then
  mkdir -p /app/database
  touch /app/database/database.sqlite
fi

if command -v chown >/dev/null 2>&1; then
  chown -R www-data:www-data /app/storage /app/bootstrap/cache || true
fi

if command -v find >/dev/null 2>&1; then
  find /app/storage /app/bootstrap/cache -type d -exec chmod 0777 {} + || true
  find /app/storage /app/bootstrap/cache -type f -exec chmod 0666 {} + || true
else
  chmod -R 0777 /app/storage /app/bootstrap/cache || true
fi

if [ -f /app/database/database.sqlite ]; then
  chown www-data:www-data /app/database/database.sqlite || true
  chmod 0666 /app/database/database.sqlite || true
fi

exec php-fpm
