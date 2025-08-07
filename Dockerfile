FROM php:8.2-fpm

# Set the working directory
WORKDIR /var/www

# Install system dependencies
RUN apt-get update && apt-get install -y \
    zip unzip curl git libxml2-dev libzip-dev libpng-dev libjpeg-dev libonig-dev \
    sqlite3 libsqlite3-dev

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application files and set ownership
COPY . /var/www
COPY --chown=www-data:www-data . /var/www

# Install Composer dependencies
RUN composer install --prefer-dist --no-dev --optimize-autoloader

# Set environment variables and generate app key
COPY .env.example .env
RUN php artisan key:generate

# Set the correct permissions
RUN chmod -R 755 /var/www

# Expose port 8000 for the app
EXPOSE 8000

# Start the application
CMD php artisan serve --host=0.0.0.0 --port=8000
