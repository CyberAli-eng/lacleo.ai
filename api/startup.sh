#!/bin/sh

# 1. Run Migrations (Ignore failures so server can still try to start)
echo "Running migrations..."
php artisan migrate --force || echo "Migration failed, but continuing..."

# 2. Seed Admin User
echo "Seeding admin user if needed..."
php artisan db:seed --class=EnsureAdminUserSeeder --force || echo "Seeding failed, but continuing..."

# 3. Clear Cache
php artisan config:clear
php artisan route:clear

# 4. Start Server
echo "Starting Laravel server..."
php artisan serve --host=0.0.0.0 --port=8080
