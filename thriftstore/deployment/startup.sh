#!/bin/bash

# Navigate to the root
cd /home/site/wwwroot

echo "Starting Deployment Script..."

# Copy Nginx config
if [ -f "/home/site/wwwroot/deployment/nginx.conf" ]; then
    echo "Updating Nginx configuration..."
    cp /home/site/wwwroot/deployment/nginx.conf /etc/nginx/sites-available/default
    service nginx reload
else
    echo "Nginx config not found at /home/site/wwwroot/deployment/nginx.conf"
fi

# Link storage
echo "Linking storage..."
php artisan storage:link --force

# Cache config and routes for performance
echo "Caching Laravel configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Deployment Script Finished."
