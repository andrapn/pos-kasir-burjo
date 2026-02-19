# Stage 1: Build Assets (Vite)
FROM node:22 AS asset-builder
WORKDIR /app
COPY . .
RUN npm install && npm run build

# Stage 2: PHP Application
FROM php:8.4-apache

# Install System Dependencies & PHP Extensions
RUN apt-get update && apt-get install -y \
    libpng-dev libonig-dev libxml2-dev libzip-dev libpq-dev \
    zip unzip git curl \
    && docker-php-ext-install pdo_pgsql pgsql intl zip bcmath gd mbstring

# Enable Apache Mod Rewrite
RUN a2enmod rewrite

# Set Working Directory
WORKDIR /var/www/html
COPY . .
COPY --from=asset-builder /app/public/build ./public/build

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader

# Set Permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Update Apache Config to point to /public
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf

# Expose port 80
EXPOSE 80

CMD ["apache2-foreground"]