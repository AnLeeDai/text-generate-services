# Deploy to Render.com

Hướng dẫn deploy dự án Text Generate Services lên Render.com

## 📋 Yêu cầu

- Tài khoản Render.com
- Repository GitHub public hoặc private
- Code đã được push lên GitHub

## 🚀 Bước 1: Chuẩn bị Repository

### Push code lên GitHub
```bash
git add .
git commit -m "Prepare for Render deployment with correct port binding"
git push origin main
```

## 🔧 Port Configuration

Service đã được cấu hình để:
- ✅ Bind to `0.0.0.0` (tất cả interfaces) thay vì localhost
- ✅ Sử dụng PORT environment variable từ Render
- ✅ Fallback to port 8000 nếu PORT không được set
- ✅ Apache được cấu hình động để listen trên đúng port

## 🚀 Bước 2: Tạo Web Service trên Render

1. **Đăng nhập Render.com** và click "New +"
2. **Chọn "Web Service"**
3. **Connect Repository**: 
   - Chọn GitHub repository của bạn
   - Branch: `main`

4. **Service Configuration**:
   ```
   Name: text-generate-services
   Environment: Docker
   Region: Chọn region gần nhất
   Branch: main
   Root Directory: (để trống)
   ```

5. **Build Configuration**:
   ```
   Build Command: (để trống - Docker sẽ handle)
   Start Command: (để trống - Docker sẽ handle)
   ```

## 🔧 Bước 3: Environment Variables

Trong Render dashboard, thêm các Environment Variables sau:

### Required Variables
```
APP_NAME=Text Generate Services
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-service-name.onrender.com
```

### Optional Variables (có default values)
```
LOG_LEVEL=error
CACHE_STORE=file
SESSION_DRIVER=database
QUEUE_CONNECTION=database
```

## 📁 Bước 4: File Structure

Đảm bảo project có các files sau:

```
├── Dockerfile                 # Container configuration
├── .dockerignore             # Docker ignore rules
├── .env.production           # Production environment template
├── start.sh                  # Startup script
├── .docker/
│   └── vhost.conf           # Apache virtual host config
└── database/
    └── .gitkeep             # Ensure directory exists
```

## 🗄️ Bước 5: Database Configuration

Project sử dụng SQLite nên không cần external database:

- Database file: `database/database.sqlite`
- Được tạo tự động trong container
- Migrations chạy tự động khi deploy

## 🚀 Bước 6: Deploy

1. **Click "Create Web Service"**
2. **Chờ build và deploy** (5-10 phút)
3. **Check logs** để đảm bảo không có lỗi
4. **Test URL** được cung cấp bởi Render

## 🔍 Verification

Sau khi deploy thành công, test các endpoints:

```bash
# Test homepage
curl https://your-service.onrender.com/

# Test API
curl https://your-service.onrender.com/api/system/server-info

# Test file management
curl https://your-service.onrender.com/api/system/list
```

## 📊 Health Check

Render sẽ tự động monitor service health qua:
- **Health Check Path**: `/api/system/server-info`
- **Expected Status**: 200
- **Timeout**: 30 seconds

## 🛠️ Troubleshooting

### Common Issues

1. **Build Failed**
   ```bash
   # Check Dockerfile syntax
   docker build -t test .
   ```

2. **Database Errors**
   - SQLite file được tạo tự động
   - Check file permissions trong logs

3. **Permission Errors**
   ```bash
   # Files có proper permissions trong Dockerfile
   chmod 755 storage bootstrap/cache
   chmod 664 database/database.sqlite
   ```

4. **Apache Errors**
   - Check `.docker/vhost.conf`
   - Verify DocumentRoot path

### Debug Commands

Check logs trong Render dashboard:
```bash
# Application logs
tail -f /var/log/apache2/error.log

# Laravel logs  
tail -f storage/logs/laravel.log
```

## 🔧 Configuration Files

### Dockerfile
- Base image: `php:8.3-apache`
- PHP extensions: SQLite, GD, ZIP
- Auto database creation
- Proper permissions setup

### Start Script (`start.sh`)
- Environment setup
- Database creation
- Migrations
- Cache optimization
- Apache startup

### Environment (.env.production)
- Production-optimized settings
- SQLite database configuration
- Security headers enabled

## 📈 Performance Tips

1. **Enable OPcache** (included in Dockerfile)
2. **Cache optimization** (automatic in start.sh)
3. **File upload limits** configured in vhost.conf
4. **Security headers** enabled

## 🔒 Security

- **Debug mode**: Disabled in production
- **Security headers**: X-Frame-Options, X-XSS-Protection
- **File permissions**: Properly restricted
- **Environment variables**: Sensitive data via Render env vars

## 💰 Cost Estimation

Render Free Tier:
- ✅ 750 hours/month (enough for 1 service)
- ✅ Automatic deploys from GitHub
- ✅ SSL certificates included
- ❌ Service sleeps after 15 min inactivity

Paid Plans (starting $7/month):
- ✅ No sleeping
- ✅ Custom domains
- ✅ More resources

## 🚀 Auto-Deploy

Render tự động deploy khi:
1. Push code to `main` branch
2. Environment variables thay đổi
3. Manual deploy từ dashboard

## 📞 Support

Nếu gặp vấn đề:
1. Check Render logs
2. Verify environment variables
3. Test local Docker build
4. Check GitHub repository connection
