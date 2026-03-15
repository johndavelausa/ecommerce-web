#!/bin/bash
echo "Starting Deployment Script..."

# 1. Detect App Location
# Sometimes Azure zips the files as a subfolder, sometimes as root. We handle both.
if [ -d "/home/site/wwwroot/thriftstore" ]; then
    APP_ROOT="/home/site/wwwroot/thriftstore"
    echo "Found app in subfolder: $APP_ROOT"
else
    APP_ROOT="/home/site/wwwroot"
    echo "Using root folder: $APP_ROOT"
fi

cd $APP_ROOT

# 2. Setup Permissions
echo "Setting permissions on $APP_ROOT..."
mkdir -p storage/framework/{sessions,views,cache/data}
mkdir -p storage/logs
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# 3. Laravel Setup
echo "Cleaning and optimizing Laravel..."
php artisan optimize:clear
php artisan view:clear
php artisan storage:link --force || true
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# 4. Nginx Configuration
echo "Forcing Nginx to point to $APP_ROOT/public..."
cat <<EOF > /etc/nginx/sites-available/default
server {
    listen 8080;
    listen [::]:8080;
    root $APP_ROOT/public;
    index index.php index.html index.htm;
    server_name _;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        include fastcgi_params;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_read_timeout 300;
    }
}
EOF

# Ensure the config is active
ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default

# 5. Refresh Services
echo "Restarting services..."
service nginx restart
# Force PHP to reload everything
killall php-fpm || true
php-fpm -D

echo "Deployment Script Finished Successfully. Site should be live at $APP_ROOT/public"
