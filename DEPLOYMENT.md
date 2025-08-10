# Deployment Guide

Hướng dẫn deploy dự án Text Generate Services lên các môi trường khác nhau.

## 📋 Checklist trước khi Deploy

### Requirements
- [ ] PHP 8.3 hoặc cao hơn
- [ ] Composer
- [ ] Web server (Apache/Nginx)
- [ ] PHP Extensions: GD, SQLite3, ZIP
- [ ] Write permissions cho storage và bootstrap/cache

### Files cần thiết
- [ ] Template files trong `storage/app/private/`
- [ ] File `.env` đã được cấu hình
- [ ] Database đã được migrate

## 🚀 Production Deployment

### 1. Chuẩn bị Server
```bash
# Cài đặt PHP và extensions
sudo apt update
sudo apt install php8.3 php8.3-fpm php8.3-gd php8.3-sqlite3 php8.3-zip php8.3-mbstring php8.3-xml

# Cài đặt Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### 2. Deploy Code
```bash
# Clone repository
git clone https://github.com/AnLeeDai/text-generate-services.git
cd text-generate-services

# Install dependencies
composer install --optimize-autoloader --no-dev

# Copy environment file
cp .env.example .env
```

### 3. Configuration
```bash
# Generate application key
php artisan key:generate

# Set correct permissions
sudo chown -R www-data:www-data storage/
sudo chown -R www-data:www-data bootstrap/cache/
chmod -R 755 storage/
chmod -R 755 bootstrap/cache/
```

### 4. Database Setup
```bash
# Create database file
touch database/database.sqlite
sudo chown www-data:www-data database/database.sqlite
chmod 664 database/database.sqlite

# Run migrations
php artisan migrate --force
```

### 5. Optimize for Production
```bash
# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Optimize autoloader
composer dump-autoload --optimize
```

## 🌐 Web Server Configuration

### Nginx Configuration
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/text-generate-services/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Increase file upload limits
    client_max_body_size 50M;
}
```

### Apache Configuration
```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /var/www/text-generate-services/public

    <Directory /var/www/text-generate-services/public>
        AllowOverride All
        Require all granted
    </Directory>

    # Increase file upload limits
    php_value upload_max_filesize 50M
    php_value post_max_size 50M
</VirtualHost>
```

## 🔧 Environment Configuration

### Production `.env` settings
```env
APP_NAME="Text Generate Services"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=sqlite

LOG_CHANNEL=daily
LOG_LEVEL=error

CACHE_STORE=file
SESSION_DRIVER=file
```

## 📊 Monitoring & Maintenance

### Health Check Endpoint
```bash
curl https://your-domain.com/api/system/server-info
```

### Log Monitoring
```bash
tail -f storage/logs/laravel.log
```

### Disk Space Monitoring
```bash
# Check disk space
df -h

# Clean up old files
php artisan cache:clear
rm -rf storage/logs/*.log.old
```

### Backup Strategy
```bash
# Backup database
cp database/database.sqlite backup/database_$(date +%Y%m%d).sqlite

# Backup generated files
tar -czf backup/generated_$(date +%Y%m%d).tar.gz public/generated/

# Backup templates
tar -czf backup/templates_$(date +%Y%m%d).tar.gz storage/app/private/
```

## 🔒 Security Considerations

### File Permissions
```bash
# Secure file permissions
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
chmod -R 755 storage/
chmod -R 755 bootstrap/cache/
```

### Firewall Rules
```bash
# Allow HTTP/HTTPS only
sudo ufw allow 80
sudo ufw allow 443
sudo ufw allow 22  # SSH only if needed
```

### SSL Certificate
```bash
# Using Let's Encrypt
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.com
```

## 🐛 Troubleshooting

### Common Issues

1. **Permission Errors**
   ```bash
   sudo chown -R www-data:www-data storage/ bootstrap/cache/
   ```

2. **Database Issues**
   ```bash
   php artisan migrate:fresh --force
   ```

3. **Cache Issues**
   ```bash
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   ```

4. **File Upload Issues**
   - Check `upload_max_filesize` and `post_max_size` in PHP config
   - Verify web server upload limits

### Performance Optimization

1. **Enable OPcache**
   ```bash
   # Add to php.ini
   opcache.enable=1
   opcache.memory_consumption=128
   opcache.max_accelerated_files=4000
   ```

2. **Configure PHP-FPM**
   ```bash
   # Optimize php-fpm pool settings
   pm.max_children = 50
   pm.start_servers = 10
   pm.min_spare_servers = 5
   pm.max_spare_servers = 20
   ```

## 📞 Support

Nếu gặp vấn đề trong quá trình deploy:
1. Kiểm tra logs trong `storage/logs/`
2. Verify environment configuration
3. Check file permissions
4. Test API endpoints using the test script
