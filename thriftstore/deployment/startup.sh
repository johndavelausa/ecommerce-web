#!/bin/bash
echo "Starting Deployment Script..."

# 1. Detect App Location
if [ -d "/home/site/wwwroot/thriftstore" ]; then
    APP_ROOT="/home/site/wwwroot/thriftstore"
else
    APP_ROOT="/home/site/wwwroot"
fi

cd $APP_ROOT
echo "APP_ROOT = $APP_ROOT"

# 2. Cleanup placeholder files
rm -f /home/site/wwwroot/hostingstart.html

# 3. Setup Permissions
mkdir -p storage/framework/{sessions,views,cache/data} storage/logs
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# 4. Apply Nginx Configuration
cat <<'EOF' > /etc/nginx/sites-available/default
server {
    listen 8080;
    listen [::]:8080;
    root /home/site/wwwroot/public;
    index index.php index.html index.htm;
    server_name _;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_read_timeout 300;
    }

    location ~ /\.ht {
        deny all;
    }
}
EOF

# Update root if the app is in a subfolder
if [ "$APP_ROOT" != "/home/site/wwwroot" ]; then
    sed -i "s|root /home/site/wwwroot/public;|root $APP_ROOT/public;|g" /etc/nginx/sites-available/default
fi

# 5. Laravel Setup
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan storage:link --force || true
php artisan migrate --force || true
php artisan optimize

# 6. Kickstart Services
service nginx restart
php-fpm -D

echo "Deployment Finished Successfully."
tail -f /dev/null
