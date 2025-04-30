# Usar la imagen oficial de PHP 8.1 con Apache
FROM php:8.1-apache

# Instalar dependencias necesarias y extensiones de PHP para PostgreSQL
RUN apt-get update && apt-get install -y \
    curl \
    unzip \
    libpq-dev \
    libzip-dev \
    zip \
    && docker-php-ext-install sockets pdo_pgsql \
    && docker-php-ext-enable pdo_pgsql

# Instalar Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Establecer el directorio de trabajo
WORKDIR /var/www/html

# Copiar archivos del proyecto
COPY . .

# Asegurar permisos adecuados
RUN chmod -R 777 /var/www/html

# Habilitar mod_rewrite de Apache
RUN a2enmod rewrite

# Exponer puerto 80
EXPOSE 80

# Ejecutar composer install y arrancar Apache
CMD ["/bin/bash", "-c", "composer install --no-interaction --no-dev --prefer-dist && apache2-foreground"]


