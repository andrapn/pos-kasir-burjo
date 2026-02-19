# Stage 1: Build Assets & Dependencies
FROM php:8.4-cli AS builder
WORKDIR /app

# Install System Dependencies untuk PHP & Node.js
RUN apt-get update && apt-get install -y \
    libpng-dev libonig-dev libxml2-dev libzip-dev libpq-dev libicu-dev \
    zip unzip git curl gnupg \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && docker-php-ext-install pdo_pgsql pgsql intl zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy semua file project
COPY . .

# 1. Jalankan Composer Install agar folder 'vendor' tersedia untuk Vite
RUN composer install --no-dev --optimize-autoloader

# 2. Jalankan NPM Install & Build
RUN npm install && npm run build

# Stage 2: Production Image
FROM php:8.4-apache

# Install Runtime Dependencies
RUN apt-get update && apt-get install -y \
    libpq-dev libicu-dev libzip-dev libpng-dev \
    && docker-php-ext-install pdo_pgsql pgsql intl zip bcmath gd mbstring

# Enable Apache Mod Rewrite
RUN a2enmod rewrite

WORKDIR /var/www/html

# Copy semua file dari stage builder
COPY --from=builder /app .

# Set Permissions untuk Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Update Apache Config
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf

EXPOSE 80

CMD ["apache2-foreground"]