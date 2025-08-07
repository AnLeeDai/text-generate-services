# Use official PHP image with necessary extensions
FROM php:8.3-fpm

# Install system dependencies and clear apt cache
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /var/www

# Copy composer installer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy application files into the container. This must happen before `composer install`
# so that the composer.json file is available.
COPY . .

# Install PHP dependencies and clear composer cache
RUN composer install --no-interaction --prefer-dist --optimize-autoloader \
    && composer clear-cache

# Set permissions for Laravel
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Expose port 8000
EXPOSE 8000

# Set environment variables for SQLite
ENV DB_CONNECTION=sqlite
ENV DB_DATABASE=/var/www/database/database.sqlite

# Create SQLite database file
RUN mkdir -p /var/www/database && touch /var/www/database/database.sqlite && chown -R www-data:www-data /var/www/database

# Clear Laravel cache
RUN php artisan cache:clear && php artisan config:clear && php artisan route:clear && php artisan view:clear

# Start Laravel server
CMD php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=8000