#!/bin/bash
echo "Starting Final Light-Speed Script..."

# 1. App is at the root based on your last 'ls'
APP_ROOT="/home/site/wwwroot"
cd $APP_ROOT

# 2. Cleanup and Folders
rm -f hostingstart.html
mkdir -p storage/framework/{sessions,views,cache/data}
mkdir -p storage/logs

# 3. Fast Permissions (Only the essentials!)
echo "Applying fast permissions..."
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# 4. Clear everything to kill the 'syntax error' ghost
php artisan optimize:clear
php artisan view:clear

# 5. Nginx Configuration (Precise & Clean)
echo "Configuring Nginx..."
cat <<EOF > /etc/nginx/sites-available/default
server {
    listen 8080;
    listen [::]:8080;
    root /home/site/wwwroot/public;
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

# Ensure symlink
ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default

# 6. Restart Services
service nginx restart
killall php-fpm || true
php-fpm -D

echo "All done! Site should be live."
