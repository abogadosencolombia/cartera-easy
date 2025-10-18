#!/usr/bin/env bash
set -e

# permisos y carpetas runtime
mkdir -p storage/framework/{cache,sessions,views} bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# limpieza y caches
php artisan config:clear || true
php artisan cache:clear  || true
php artisan route:clear  || true
php artisan view:clear   || true

php artisan migrate --force || true

php artisan config:cache
php artisan route:cache
php artisan view:cache

# servir
exec php -S 0.0.0.0:8000 -t public
