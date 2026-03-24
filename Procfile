web: sh -c "php -S 0.0.0.0:${PORT:-8000} -t public"
queue: php artisan queue:work --sleep=3 --tries=3 --max-time=3600
