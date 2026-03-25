# ── Stage 1: Node – build Vite assets ─────────────────────────────────────────
FROM node:20-alpine AS node-builder

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci --ignore-scripts

COPY resources ./resources
COPY vite.config.js ./
COPY tailwind.config.js* ./
COPY postcss.config.js* ./

RUN npm run build

# ── Stage 2: PHP – production image ───────────────────────────────────────────
FROM php:8.2-apache

# System deps
RUN apt-get update && apt-get install -y --no-install-recommends \
    git curl zip unzip libpng-dev libonig-dev libxml2-dev libzip-dev \
    libcurl4-openssl-dev libssl-dev libexif-dev libintl-perl libicu-dev \
    && docker-php-ext-install \
        pdo_mysql \
        gd zip bcmath exif intl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable mod_rewrite for Laravel and fix MPM conflict
RUN a2dismod mpm_event || true && a2enmod mpm_prefork && a2enmod rewrite

# Set document root to Laravel public/
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy app source
COPY . .

# Copy built Vite assets from node-builder stage
COPY --from=node-builder /app/public/build ./public/build

# Install PHP dependencies (no dev)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# Storage & permissions
RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions \
             storage/framework/views bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Expose port and start Apache
EXPOSE 80

# Entrypoint: run migrations + storage link, then start Apache
COPY ./docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
