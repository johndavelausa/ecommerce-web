#!/bin/bash

# Navigate to the ACTUAL app root
cd /home/site/wwwroot/thriftstore

echo "Starting Deployment Script..."

# 1. Ensure required Laravel storage directories exist
echo "Creating missing storage directories..."
mkdir -p storage/framework/{sessions,views,cache/data}
mkdir -p storage/logs

# 2. Fix permissions
echo "Setting permissions..."
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null

# 3. Update Nginx to point to the thriftstore/public subfolder
echo "Updating Nginx configuration..."
sed -i "s|root /home/site/wwwroot/public;|root /home/site/wwwroot/thriftstore/public;|g" /etc/nginx/sites-available/default
sed -i "s|root /home/site/wwwroot;|root /home/site/wwwroot/thriftstore/public;|g" /etc/nginx/sites-available/default

# Add try_files for Laravel routing
if ! grep -q "try_files" /etc/nginx/sites-available/default; then
    sed -i '/index index.php/a \ \ \ \ \ \ \ \ try_files $uri $uri/ /index.php?$query_string;' /etc/nginx/sites-available/default
fi

# 4. Restart Nginx
echo "Restarting Nginx..."
service nginx restart

# 5. Finalize Laravel
echo "Finalizing Laravel setup..."
php artisan storage:link --force || true
php artisan view:clear || true
php artisan config:cache || true

echo "Deployment Script Finished."
