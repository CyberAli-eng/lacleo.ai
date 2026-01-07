FROM php:8.2-cli

# System deps
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libpq-dev default-mysql-client curl \
    && docker-php-ext-install pdo pdo_mysql zip

# Node.js (for Vite build)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

# Install PHP deps
RUN composer install --no-dev --optimize-autoloader

# Install Node deps and build assets
RUN npm install && npm run build

EXPOSE 8080

CMD php artisan migrate --force || true && \
    php artisan config:clear && \
    php artisan route:clear && \
    php artisan serve --host=0.0.0.0 --port=8080
