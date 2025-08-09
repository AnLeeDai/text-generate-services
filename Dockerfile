FROM php:8.2-apache

WORKDIR /var/www

RUN apt-get update && apt-get install -y \
    git zip unzip curl libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    libonig-dev libxml2-dev sqlite3 libsqlite3-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql pdo_sqlite mbstring exif pcntl bcmath gd zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY .docker/vhost.conf /etc/apache2/sites-available/000-default.conf

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY . /var/www
RUN chown -R www-data:www-data /var/www

RUN composer install --no-dev --prefer-dist --optimize-autoloader

COPY .env.example .env
RUN php artisan key:generate --force \
    && php artisan storage:link || true \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && find /var/www -type f -exec chmod 0644 {} \; \
    && find /var/www -type d -exec chmod 0755 {} \;

EXPOSE 80
