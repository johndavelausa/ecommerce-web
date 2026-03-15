#!/bin/bash

# Navigate to the app root
cd /home/site/wwwroot

echo "Starting Deployment Script..."

# 1. Ensure required Laravel storage directories exist
echo "Creating missing storage directories..."
mkdir -p storage/framework/{sessions,views,cache/data}
mkdir -p storage/logs

# 2. Fix permissions for the entire project
echo "Setting permissions for the whole project..."
chown -R www-data:www-data /home/site/wwwroot/thriftstore
chmod -R 755 /home/site/wwwroot/thriftstore
chmod -R 775 /home/site/wwwroot/thriftstore/storage 
chmod -R 775 /home/site/wwwroot/thriftstore/bootstrap/cache

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
        fastcgi_read_timeout 300;
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
php artisan optimize:clear || true
php artisan config:cache || true

echo "Deployment Script Finished."
