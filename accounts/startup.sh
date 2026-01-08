#!/bin/sh

# 1. Run Migrations
echo "Running migrations..."
php artisan migrate --force

# 2. Clear Cache
php artisan config:clear
php artisan route:clear

# 3. Start Server
echo "Starting Laravel server..."
php artisan serve --host=0.0.0.0 --port=8080
