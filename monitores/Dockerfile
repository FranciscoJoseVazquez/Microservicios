FROM php:8.1-apache

# Evitar problemas con firmas GPG en ambientes sin certificados válidos
RUN echo 'Acquire::AllowInsecureRepositories "true"; Acquire::AllowDowngradeToInsecureRepositories "true";' > /etc/apt/apt.conf.d/99insecure

# Preparar sistema y paquetes necesarios
RUN apt-get update && apt-get install -y \
    gnupg \
    ca-certificates \
    curl \
    unzip \
    libpq-dev \
    supervisor \
    apt-transport-https \
    lsb-release \
    software-properties-common && \
    docker-php-ext-install sockets

# Instalar Docker CLI (sin fallos de GPG ni de espacio)
RUN curl -fsSL https://download.docker.com/linux/debian/gpg | gpg --dearmor -o /usr/share/keyrings/docker-archive-keyring.gpg && \
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/docker-archive-keyring.gpg] https://download.docker.com/linux/debian $(lsb_release -cs) stable" > /etc/apt/sources.list.d/docker.list && \
    apt-get update && \
    apt-get install -y docker-ce-cli

# Instalar Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Crear carpeta para logs de supervisord
RUN mkdir -p /var/log/supervisor

# Definir directorio de trabajo
WORKDIR /var/www/html

# Copiar archivos del proyecto
COPY . .

# Permisos básicos (ajustables según tus necesidades)
RUN chmod -R 755 /var/www/html

# Habilitar mod_rewrite de Apache
RUN a2enmod rewrite

# Configurar supervisord
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Exponer el puerto HTTP
EXPOSE 80

# Comando por defecto
CMD ["bash", "-c", "composer install --no-interaction --no-dev --prefer-dist && /usr/bin/supervisord -n"]
