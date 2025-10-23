web: php-fpm
nginx: nginx -g 'daemon off;'
queue: php artisan queue:work --sleep=3 --tries=3 --max-time=3600
reverb: php artisan reverb:start --host=0.0.0.0 --port=9090 --debug