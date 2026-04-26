#!/bin/sh
touch /tmp/database.sqlite
chmod 777 /tmp/database.sqlite
php artisan migrate --force
exec apache2-foreground