# Text Generate Services

A Laravel-based microservice for document generation and file management, specifically designed for bank statement generation and system monitoring.

## Features

### 🏦 Bank Bill Generation
- Generate realistic bank statements using Word templates
- Support for BTG Pactual bank format
- Random transaction generation with realistic data
- Batch processing with ZIP download
- Customizable account information

### 📁 File Management
- List and manage generated files
- Bulk file operations (delete all, download all)
- Disk space monitoring
- Auto-cleanup when storage is low

### 🖥️ System Monitoring
- Real-time server information
- CPU, RAM, and disk usage monitoring
- System health checks

### 🔧 API Features
- Consistent JSON response format
- Comprehensive error handling
- Request validation
- API documentation

## Tech Stack

- **Framework**: Laravel 12.x
- **PHP**: 8.3+
- **Database**: SQLite
- **Document Processing**: PhpOffice/PhpWord
- **Frontend**: Blade templates with Tailwind CSS

## Installation

### Prerequisites
- PHP 8.3 or higher
- Composer
- PHP Extensions: GD, SQLite3, ZIP

### Setup Steps

1. **Clone the repository**
   ```bash
   git clone https://github.com/AnLeeDai/text-generate-services.git
   cd text-generate-services
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Environment setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Database setup**
   ```bash
   touch database/database.sqlite
   php artisan migrate
   ```

5. **Start development server**
   ```bash
   # Using custom script (recommended)
   ./start-dev.sh
   
   # Or manually
   php artisan serve --host=127.0.0.1 --port=8081
   ```

6. **Access the application**
   - Web Interface: http://127.0.0.1:8081
   - API Base URL: http://127.0.0.1:8081/api
   - Server Info: http://127.0.0.1:8081/api/system/server-info

## Security Configuration

### Localhost Only Access
This service is configured to run only on localhost (127.0.0.1:8081) for security reasons:

- **Host binding**: Server only listens on 127.0.0.1
- **IP restriction**: Middleware blocks external IP addresses
- **Port isolation**: Uses dedicated port 8081

### Environment Variables
```env
APP_URL=http://127.0.0.1:8081
APP_HOST=127.0.0.1
APP_PORT=8081
```
   cd text-generate-services
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Environment setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Database setup**
   ```bash
   touch database/database.sqlite
   php artisan migrate
   ```

5. **Start the development server**
   ```bash
   php artisan serve
   ```

   The application will be available at `http://127.0.0.1:8000`

## API Endpoints

### System Information
```bash
GET /api/system/server-info     # Get server information
```

### File Management
```bash
GET /api/system/list            # List generated files
DELETE /api/system/delete-all   # Delete all files
DELETE /api/system/delete/{fileName}  # Delete specific file
```

### Bank Bill Generation
```bash
POST /api/bank-bill/generate    # Generate bank statements
```

#### Example Request for Bank Bill Generation:
```json
POST /api/bank-bill/generate
Content-Type: application/json

[
  {
    "fullname": "John Doe",
    "accountNumber": "BT123456789012",
    "totalOn": 50000.00
  }
]
```

## Response Format

All API endpoints return a consistent JSON format:

### Success Response
```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": {
    // Response data here
  },
  "timestamp": "2025-08-10T16:23:28.000000Z"
}
```

### Error Response
```json
{
  "success": false,
  "message": "Error description",
  "errors": {
    // Validation errors or error details
  },
  "timestamp": "2025-08-10T16:23:28.000000Z"
}
```

## Configuration

### Bank Templates
Place your Word templates in `storage/app/private/`:
- `btg_pactual_bank_bill_template.docx` - BTG Pactual bank statement template

### Environment Variables
Key environment variables in `.env`:

```env
APP_NAME="Text Generate Services"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite
CACHE_STORE=database
SESSION_DRIVER=database
```

## Development

### Testing API Format
Run the included test script to verify API response consistency:

```bash
./test_api_format.sh
```

### Code Structure
```
app/
├── Http/
│   ├── Controllers/Api/      # API Controllers
│   ├── Middleware/           # Custom middleware
│   └── Traits/              # Reusable traits
├── Models/                  # Eloquent models
└── Providers/              # Service providers

storage/app/private/        # Document templates
public/generated/           # Generated output files
```

### Key Components

- **ApiResponseTrait**: Standardizes all API responses
- **FormatApiResponse**: Middleware ensuring consistent response format
- **BankBillController**: Handles document generation
- **ServerInfo**: System monitoring and performance testing
- **FileCheckController**: File management operations

## Troubleshooting

### Common Issues

1. **Missing PHP Extensions**
   ```bash
   sudo apt-get install php8.3-gd php8.3-sqlite3 php8.3-zip
   ```

2. **Permission Issues**
   ```bash
   chmod -R 755 storage/
   chmod -R 755 bootstrap/cache/
   ```

3. **Database Issues**
   ```bash
   rm database/database.sqlite
   touch database/database.sqlite
   php artisan migrate:fresh
   ```

## Performance

- Optimized for batch processing
- Automatic memory management for large files
- Disk space monitoring and cleanup
- Configurable timeouts and limits

## Security

- Input validation on all endpoints
- File type restrictions
- Path traversal protection
- Rate limiting ready

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Support

For issues and questions:
- Create an issue on GitHub
- Check the API documentation in `API_RESPONSE_FORMAT.md`
- Review the troubleshooting section above
