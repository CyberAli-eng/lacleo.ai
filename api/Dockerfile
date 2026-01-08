FROM php:8.2-cli

# System deps
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libpq-dev default-mysql-client \
    && docker-php-ext-install pdo pdo_mysql zip

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

RUN composer install --no-dev --optimize-autoloader

RUN chmod +x startup.sh

EXPOSE 8080

CMD ["./startup.sh"]

