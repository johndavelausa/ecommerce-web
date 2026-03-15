#!/bin/bash

# Navigate to the app root
cd /home/site/wwwroot

echo "Starting Deployment Script..."

# 1. Ensure required Laravel storage directories exist
echo "Creating missing storage directories..."
mkdir -p storage/framework/{sessions,views,cache/data}
mkdir -p storage/logs

# 2. Fast Permissions (Only target what matters)
echo "Setting permissions (Optimized)..."
# We only need to ensure these two folders are writable for the app to work
chown -R www-data:www-data /home/site/wwwroot/thriftstore/storage
chown -R www-data:www-data /home/site/wwwroot/thriftstore/bootstrap/cache
chmod -R 775 /home/site/wwwroot/thriftstore/storage
chmod -R 775 /home/site/wwwroot/thriftstore/bootstrap/cache

# 3. Finalize Laravel (Do this BEFORE Nginx restart)
echo "Pre-warming Laravel..."
cd /home/site/wwwroot/thriftstore
php artisan storage:link --force || true
php artisan optimize:clear || true
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# 4. Apply Nginx configuration
echo "Updating Nginx configuration..."
cat <<EOF > /etc/nginx/sites-available/default
server {
    listen 8080;
    listen [::]:8080;
    root /home/site/wwwroot/thriftstore/public;
    index index.php index.html index.htm;
    server_name _;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_read_timeout 300;
    }
}
EOF

# 5. Restart services in one go
echo "Restarting services for updated config..."
service nginx restart
# Force PHP-FPM to refresh its worker pool to pick up new code changes
killall php-fpm || true
php-fpm -D

echo "Deployment Script Finished Successfully."
