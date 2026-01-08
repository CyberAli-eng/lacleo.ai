#!/bin/sh

# 1. Run Migrations
echo "Running migrations..."
php artisan migrate --force

# 2. Seed Admin User
echo "Seeding admin user if needed..."
php artisan db:seed --class=EnsureAdminUserSeeder --force

# 3. Clear Cache
php artisan config:clear
php artisan route:clear

# 4. Start Server
echo "Starting Laravel server..."
php artisan serve --host=0.0.0.0 --port=8080
