#!/bin/sh
set -e

# TEMPORARILY DISABLED EVERYTHING TO BYPASS DATABASE BLOCK
# php artisan config:cache
# php artisan route:cache
# php artisan view:cache
# php artisan migrate --force
# php artisan storage:link || true

echo "Starting Apache without initialization steps..."

# Start Apache in foreground
exec apache2-foreground
