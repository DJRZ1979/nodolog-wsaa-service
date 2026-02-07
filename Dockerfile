FROM php:8.2-apache

# Instalar extensiones necesarias
RUN apt-get update && apt-get install -y \
    libxml2-dev \
    libssl-dev \
    && docker-php-ext-install soap

# Habilitar mod_rewrite
RUN a2enmod rewrite

# Configurar DocumentRoot en /var/www/html/public
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/000-default.conf \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf

# Copiar proyecto
COPY . /var/www/html/

# Permisos
RUN mkdir -p /var/www/html/logs && chmod -R 777 /var/www/html/logs

EXPOSE 80
