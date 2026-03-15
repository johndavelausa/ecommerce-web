#!/bin/bash

# Navigate to the root
cd /home/site/wwwroot

echo "Starting Deployment Script..."

# 1. Fix permissions (Critical for Laravel on Azure)
echo "Setting permissions..."
chmod -R 775 storage bootstrap/cache 2>/dev/null
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null

# 2. Modify the default Azure Nginx config to point to /public and support Laravel routing
# This is safer than replacing the whole file as it preserves the system PHP-FPM socket
echo "Updating Nginx configuration..."
sed -i "s|root /home/site/wwwroot;|root /home/site/wwwroot/public;|g" /etc/nginx/sites-available/default

# Add try_files for Laravel routing if not already there
if ! grep -q "try_files" /etc/nginx/sites-available/default; then
    sed -i '/index index.php/a \ \ \ \ \ \ \ \ try_files $uri $uri/ /index.php?$query_string;' /etc/nginx/sites-available/default
fi

# 3. Restart Nginx to apply changes
echo "Restarting Nginx..."
service nginx restart

# 4. Link storage
echo "Linking storage..."
php artisan storage:link --force

echo "Deployment Script Finished."
