# Changelog

Tất cả các thay đổi quan trọng của dự án Text Generate Services sẽ được ghi lại trong file này.

## [1.1.0] - 2025-08-10

### Removed
- 🗑️ **Performance Testing API**: Removed `/api/system/performance` endpoint
- Cleaned up ServerInfo controller by removing performance testing methods
- Updated documentation to reflect API changes

### Technical Improvements
- Simplified ServerInfo controller to focus only on system information
- Reduced code complexity and maintenance overhead

## [1.0.0] - 2025-08-10

### Added
- ✨ **Bank Bill Generation**: Tạo sao kê ngân hàng với template BTG Pactual
- 📁 **File Management**: Quản lý files đã tạo (list, delete, download)
- 🖥️ **System Monitoring**: Theo dõi thông tin server và performance
- 🔧 **API Standardization**: Chuẩn hóa format response cho tất cả endpoints
- 📖 **Documentation**: API documentation và setup guide

### Technical Improvements
- Implemented `ApiResponseTrait` for consistent response format
- Added `FormatApiResponse` middleware for automatic response formatting
- Created configuration system for API responses
- Added comprehensive error handling and validation
- Implemented disk space monitoring with auto-cleanup

### API Endpoints
- `GET /api/system/server-info` - Server information
- `GET /api/system/performance` - Performance testing
- `GET /api/system/list` - List generated files
- `DELETE /api/system/delete-all` - Delete all files
- `DELETE /api/system/delete/{fileName}` - Delete specific file
- `POST /api/bank-bill/generate` - Generate bank statements

### Features
- **Document Generation**: 
  - Random transaction generation with realistic data
  - Batch processing with ZIP download
  - Template-based Word document generation
  
- **System Monitoring**:
  - CPU, RAM, disk usage monitoring
  - Performance testing with speed metrics
  - System health checks
  
- **File Management**:
  - Bulk operations (delete all, download all)
  - Disk space monitoring
  - Automatic cleanup when storage is low

### Configuration
- Environment-based configuration
- Customizable response messages and headers
- Configurable timestamp formats
- Template path management

### Dependencies
- Laravel 12.x
- PHP 8.3+
- PhpOffice/PhpWord for document processing
- SQLite for database
- Required PHP extensions: GD, SQLite3, ZIP

### Documentation
- Comprehensive README.md
- API response format documentation
- Test scripts for format validation
- Installation and troubleshooting guides

---

## Legend
- ✨ New features
- 🐛 Bug fixes
- 🔧 Technical improvements
- 📁 File management
- 🖥️ System features
- 📖 Documentation
- ⚡ Performance improvements
- 🔒 Security improvements
