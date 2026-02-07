FROM php:8.2-apache

# Instalar extensiones necesarias
RUN apt-get update && apt-get install -y \
    libxml2-dev \
    libssl-dev \
    && docker-php-ext-install soap

# Habilitar mod_rewrite por si lo necesitás
RUN a2enmod rewrite

# Copiar todo el proyecto al contenedor
COPY . /var/www/html/

# Dar permisos a logs
RUN mkdir -p /var/www/html/logs && chmod -R 777 /var/www/html/logs

# Exponer el puerto 80 (Render lo usa automáticamente)
EXPOSE 80
