#!/bin/sh

# Verifica configuração do Apache
echo "=== Configuração do Apache ==="
cat /etc/apache2/sites-available/000-default.conf

php artisan config:clear
php artisan cache:clear
php artisan migrate --force

exec apache2-foreground