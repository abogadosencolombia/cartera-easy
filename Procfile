web: php-fpm
nginx: nginx -g 'daemon off;'
queue: php artisan queue:work --sleep=3 --tries=3
reverb: php artisan reverb:start --host=127.0.0.1 --port=9090