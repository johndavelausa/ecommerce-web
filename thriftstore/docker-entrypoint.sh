#!/bin/sh
set -e

# Cache config/routes/views for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# TEMPORARILY DISABLED MIGRATIONS TO FIX HEALTHCHECK
# php artisan migrate --force

# Link storage
php artisan storage:link || true

# Start Apache in foreground
exec apache2-foreground
