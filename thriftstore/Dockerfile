FROM dunglas/frankenphp:php8.2-bookworm

# 1. Install system dependencies + PHP Extensions
# Added pdo_mysql (fixes your driver error) and bcmath
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    curl \
    gnupg \
    libzip-dev

# Install PHP extensions required for Laravel & MySQL
RUN docker-php-ext-install pdo pdo_mysql bcmath zip

# 2. Install Node.js 22 (For your Vite/NPM assets)
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs

# 3. Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 4. Setup working directory
WORKDIR /app
COPY . /app

# 5. Install PHP dependencies
RUN composer install --optimize-autoloader --no-dev

# 6. Install Node dependencies and build assets
RUN npm install && npm run build

# 7. Set permissions for Laravel
RUN chown -R www-data:www-data storage bootstrap/cache

# 8. Start the built-in FrankenPHP server
# This handles CSS/JS assets correctly and is much faster
CMD ["frankenphp", "php-server", "-v", "--listen", ":8080", "--root", "public/"]