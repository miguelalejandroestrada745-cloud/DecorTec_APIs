FROM php:8.2-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql && docker-php-ext-enable mysqli
RUN a2enmod rewrite

# Copiar archivos al contenedor
COPY . /var/www/html/

# Permisos
RUN chown -R www-data:www-data /var/www/html/
RUN chmod -R 755 /var/www/html/

EXPOSE 80
