FROM php:8.3-apache

WORKDIR /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    curl \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    sqlite3 \
    libsqlite3-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo \
        pdo_sqlite \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# Copy Apache configuration
COPY .docker/vhost.conf /etc/apache2/sites-available/000-default.conf

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application files
COPY . /var/www/html

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html

# Install PHP dependencies
RUN composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

# Setup environment and create database
RUN cp .env.example .env \
    && mkdir -p /var/www/html/database \
    && touch /var/www/html/database/database.sqlite \
    && chown www-data:www-data /var/www/html/database/database.sqlite \
    && chmod 664 /var/www/html/database/database.sqlite

# Generate application key and run Laravel setup
RUN php artisan key:generate --force \
    && php artisan migrate --force \
    && php artisan storage:link \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

# Set final permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database \
    && chmod -R 755 /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod 664 /var/www/html/database/database.sqlite \
    && find /var/www/html -type f -name "*.php" -exec chmod 644 {} \; \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && chmod +x /var/www/html/start.sh

# Create directories for generated files
RUN mkdir -p /var/www/html/public/generated \
    && chown -R www-data:www-data /var/www/html/public/generated \
    && chmod -R 755 /var/www/html/public/generated

# Set environment variables for fixed port
ENV SERVER_PORT=8000
ENV APP_URL=http://localhost:8000

EXPOSE 8000

CMD ["/var/www/html/start.sh"]
