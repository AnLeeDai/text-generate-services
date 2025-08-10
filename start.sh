#!/bin/bash

echo "Starting Text Generate Services deployment..."

# Copy production environment file
if [ ! -f .env ]; then
    echo "Copying production environment configuration..."
    cp .env.production .env
fi

# Generate application key if not set
if grep -q "APP_KEY=$" .env; then
    echo "Generating application key..."
    php artisan key:generate --force
fi

# Create database directory and file if not exists
if [ ! -f database/database.sqlite ]; then
    echo "Creating SQLite database..."
    mkdir -p database
    touch database/database.sqlite
    chmod 664 database/database.sqlite
fi

# Run database migrations
echo "Running database migrations..."
php artisan migrate --force

# Create storage link
echo "Creating storage link..."
php artisan storage:link

# Create generated files directory
echo "Setting up directories..."
mkdir -p public/generated
chmod 755 public/generated

# Cache optimization
echo "Optimizing caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set proper permissions
echo "Setting permissions..."
chmod -R 755 storage bootstrap/cache
chmod 664 database/database.sqlite

echo "Deployment completed successfully!"

# Start Apache
echo "Starting web server..."
apache2-foreground
