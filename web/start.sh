#!/bin/sh
set -eu
mkdir -p storage/app storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
if [ ! -s storage/app/application-key ]; then php -r 'echo "base64:".base64_encode(random_bytes(32));' > storage/app/application-key; fi
export APP_KEY="$(cat storage/app/application-key)"
touch "${DB_DATABASE:-/var/www/app/storage/app/auth.sqlite}"
php artisan config:clear
php artisan migrate --force
php artisan app:bootstrap-admin
exec php artisan serve --no-reload --host=0.0.0.0 --port=8080
