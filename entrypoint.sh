#!/bin/sh
php artisan config:clear
php artisan cache:clear
php artisan migrate --force
exec apache2-foreground