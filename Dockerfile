FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    git curl zip unzip libsqlite3-dev

RUN docker-php-ext-install pdo pdo_sqlite

WORKDIR /var/www/html

COPY . .

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN composer install --no-dev --optimize-autoloader

RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html/storage && \
    chmod -R 755 /var/www/html/bootstrap/cache

RUN a2enmod rewrite

RUN echo '<IfModule mod_rewrite.c>\n    RewriteEngine On\n    RewriteCond %{REQUEST_FILENAME} !-f\n    RewriteCond %{REQUEST_FILENAME} !-d\n    RewriteRule ^ index.php [QSA,L]\n</IfModule>' > /var/www/html/public/.htaccess

COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

CMD ["/entrypoint.sh"]