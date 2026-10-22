#!/bin/sh
set -e

if [ "${APP_KEY:-}" = "" ] || [ "${APP_KEY}" = "base64:" ]; then
    php artisan key:generate --force --no-interaction
fi

if [ ! -f /var/www/html/vendor/autoload.php ]; then
    composer install --no-dev --no-interaction --optimize-autoloader --prefer-dist
fi

php artisan package:discover --ansi 2>/dev/null || true

php artisan config:clear
php artisan route:clear

php artisan migrate --force --no-interaction 2>/dev/null || true

exec "$@"
