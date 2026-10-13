#!/bin/sh
set -eu

mkdir -p \
    storage/app \
    storage/app/private \
    storage/app/private/generated-documents \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

chmod 770 storage/app/private
chmod 770 storage/app/private/generated-documents

php artisan package:discover --ansi
php artisan config:cache
php artisan route:cache
php artisan view:cache

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
    # Seeds reference data once on a new database. On a database that is
    # already initialized or in use it seeds nothing, so a restart can never
    # recreate edited inventory or reset organizational units and roles.
    php artisan spmu:bootstrap
fi

exec "$@"
