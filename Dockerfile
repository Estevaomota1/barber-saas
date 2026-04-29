FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    git curl zip unzip libsqlite3-dev

RUN docker-php-ext-install pdo pdo_sqlite

ENV APACHE_DOCUMENT_ROOT /var/www/html/public

RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

COPY . .

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN composer install --no-dev --optimize-autoloader

RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html/storage && \
    chmod -R 755 /var/www/html/bootstrap/cache

RUN apt-get update && apt-get install -y \
git curl zip unzip libsqlite3-dev libpq-dev

RUN docker-php-ext-install pdo pdo_sqlite pdo_pgsql
RUN a2enmod rewrite

RUN sed -i '/<\/VirtualHost>/i\\t<Directory /var/www/html/public>\n\t\tAllowOverride All\n\t\tRequire all granted\n\t</Directory>' /etc/apache2/sites-available/000-default.conf

COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

CMD ["/entrypoint.sh"]
