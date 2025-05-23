FROM php:8.1-apache

#Permitir repositorios inseguros (evitar GPG error temporalmente)
RUN echo 'Acquire::AllowInsecureRepositories "true"; Acquire::AllowDowngradeToInsecureRepositories "true";' > /etc/apt/apt.conf.d/99insecure

# Instalar dependencias y extensiones necesarias
RUN apt-get update && apt-get install -y --no-install-recommends \
    apt-transport-https \
    ca-certificates \
    gnupg \
    curl \
    unzip \
    libpq-dev \
 && docker-php-ext-install sockets \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

# Instalar Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copiar archivos del proyecto
WORKDIR /var/www/html
COPY . .

# Asegurar permisos (opcional)
RUN chown -R www-data:www-data /var/www/html

# Habilitar mod_rewrite
RUN a2enmod rewrite

# Exponer puertos
EXPOSE 80

# Ejecutar Composer solo si composer.json existe
CMD ["bash", "-c", "if [ -f composer.json ]; then composer install --no-interaction --no-dev --prefer-dist; fi && apache2-foreground"]
