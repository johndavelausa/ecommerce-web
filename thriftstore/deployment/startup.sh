#!/bin/bash
echo "Starting Final robust Deployment Script..."

# 1. Detect App Location
if [ -d "/home/site/wwwroot/thriftstore" ]; then
    APP_ROOT="/home/site/wwwroot/thriftstore"
    CONF_PATH="/home/site/wwwroot/thriftstore/deployment/nginx.conf"
else
    APP_ROOT="/home/site/wwwroot"
    CONF_PATH="/home/site/wwwroot/deployment/nginx.conf"
fi

cd $APP_ROOT

# 2. Cleanup placeholder files
rm -f /home/site/wwwroot/hostingstart.html

# 3. Setup Permissions (FAST & LIGHT)
mkdir -p storage/framework/{sessions,views,cache/data} storage/logs
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# 4. Apply the Clean Nginx Config
echo "Applying clean Nginx config from $CONF_PATH..."
if [ -f "$CONF_PATH" ]; then
    # Update the root path in the static config if we are in a subfolder
    sed -i "s|root /home/site/wwwroot/public;|root $APP_ROOT/public;|g" "$CONF_PATH"
    cp "$CONF_PATH" /etc/nginx/sites-available/default
else
    echo "ERROR: nginx.conf not found!"
fi

# 5. Laravel Pulse
php artisan optimize:clear
php artisan view:clear
php artisan storage:link --force || true

# 6. Kickstart Services
service nginx restart
killall php-fpm || true
php-fpm -D

echo "Deployment Finished. Site should be live."

