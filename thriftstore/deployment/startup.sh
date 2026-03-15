#!/bin/bash
echo "Starting Final robust Deployment Script..."

# 1. Detect App Location correctly
if [ -f "/home/site/wwwroot/thriftstore/artisan" ]; then
    APP_ROOT="/home/site/wwwroot/thriftstore"
elif [ -f "/home/site/wwwroot/artisan" ]; then
    APP_ROOT="/home/site/wwwroot"
else
    echo "ERROR: Could not find Artisan!"
    exit 1
fi

cd $APP_ROOT

# 2. Cleanup placeholder files
rm -f /home/site/wwwroot/hostingstart.html

# 3. Setup Permissions (Full Sweep)
echo "Setting permissions..."
mkdir -p storage/framework/{sessions,views,cache/data}
mkdir -p storage/logs
# Ensure Nginx can read the public and root folders
chown -R www-data:www-data $APP_ROOT
chmod -R 755 $APP_ROOT
# Storage needs extra write permissions
chmod -R 775 storage bootstrap/cache

# 4. Laravel Setup
php artisan optimize:clear
php artisan view:clear
php artisan storage:link --force || true

# 5. Nginx Configuration (Using quoted EOF to prevent symbol errors)
echo "Configuring Nginx..."
cat > /etc/nginx/sites-available/default <<'EOF'
server {
    listen 8080;
    listen [::]:8080;
    root /home/site/wwwroot/public;
    index index.php index.html index.htm;
    server_name _;

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
}
EOF

# 6. Refresh Services
service nginx restart
killall php-fpm || true
php-fpm -D

echo "Deployment Finished. Site should be live."
