#!/bin/bash

# Navigate to the root
cd /home/site/wwwroot

echo "Starting Deployment Script..."

# 1. Ensure required Laravel storage directories exist
echo "Creating missing storage directories..."
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/app/cache
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/logs

# 2. Fix permissions (Critical for Laravel on Azure)
echo "Setting permissions..."
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null

# 3. Modify the default Azure Nginx config to point to /public and support Laravel routing
echo "Updating Nginx configuration..."
sed -i "s|root /home/site/wwwroot;|root /home/site/wwwroot/public;|g" /etc/nginx/sites-available/default

# Add try_files for Laravel routing if not already there
if ! grep -q "try_files" /etc/nginx/sites-available/default; then
    sed -i '/index index.php/a \ \ \ \ \ \ \ \ try_files $uri $uri/ /index.php?$query_string;' /etc/nginx/sites-available/default
fi

# 4. Restart Nginx to apply changes
echo "Restarting Nginx..."
service nginx restart

# 5. Link storage and clear caches
echo "Finalizing Laravel setup..."
php artisan storage:link --force || true
php artisan view:clear || true
php artisan cache:clear || true

echo "Deployment Script Finished."
