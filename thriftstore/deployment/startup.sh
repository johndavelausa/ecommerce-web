#!/bin/bash

# Navigate to the app root
cd /home/site/wwwroot

echo "Starting Deployment Script..."

# 1. Ensure required Laravel storage directories exist
echo "Creating missing storage directories..."
mkdir -p storage/framework/{sessions,views,cache/data}
mkdir -p storage/logs

# 2. Fix permissions
echo "Setting permissions..."
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null

# 3. Apply the WORKING Nginx configuration
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
    }
}
EOF

# 4. Restart Nginx
echo "Restarting Nginx..."
service nginx restart

# 5. Finalize Laravel
echo "Finalizing Laravel setup..."
cd /home/site/wwwroot/thriftstore
php artisan storage:link --force || true
php artisan view:cache || true
php artisan config:cache || true
php artisan route:cache || true

echo "Deployment Script Finished."
