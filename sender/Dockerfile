# Usar la imagen oficial de PHP 8.1 con Apache
FROM php:8.1-apache

# Instalar dependencias y extensiones necesarias
RUN apt-get update && apt-get install -y \
    curl \
    unzip \
    libpq-dev \
    && docker-php-ext-install sockets

# Instalar Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copiar archivos del proyecto
WORKDIR /var/www/html
COPY . .

# Asegurar permisos
RUN chmod -R 777 /var/www/html

# Habilitar mod_rewrite
RUN a2enmod rewrite

# Exponer puertos
EXPOSE 80

# Instalar dependencias de PHP usando Composer y luego iniciar Apache
CMD ["bash", "-c", "composer install --no-interaction --no-dev --prefer-dist && apache2-foreground"]



