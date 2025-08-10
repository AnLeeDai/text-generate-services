# API Response Format Documentation

## Chuẩn hóa Response Format

Tất cả API endpoints hiện tại đều trả về response theo format chuẩn sau:

### Success Response Format:
```json
{
    "success": true,
    "message": "Thông điệp mô tả kết quả",
    "data": {
        // Dữ liệu trả về
    },
    "timestamp": "2025-08-10T16:23:28.000000Z"
}
```

### Error Response Format:
```json
{
    "success": false,
    "message": "Thông điệp lỗi",
    "errors": {
        // Chi tiết lỗi (nếu có)
    },
    "timestamp": "2025-08-10T16:23:28.000000Z"
}
```

## Các endpoint đã được chuẩn hóa:

### 1. Server Info
- **GET** `/api/system/server-info`
- **Response**: Thông tin hệ thống trong field `data`

### 2. File Management
- **GET** `/api/system/list` - Liệt kê files
- **DELETE** `/api/system/delete-all` - Xóa tất cả files  
- **DELETE** `/api/system/delete/{fileName}` - Xóa file cụ thể

### 3. Bank Bill Generation
- **POST** `/api/bank-bill/generate`
- **Response**: Thông tin file đã tạo trong field `data`

## Implementation Details

### Sử dụng ApiResponseTrait
Tất cả controllers extend từ `Controller` base class đã sử dụng `ApiResponseTrait` với các method:

- `successResponse($data, $message, $statusCode)` 
- `errorResponse($message, $errors, $statusCode)`
- `validationErrorResponse($errors, $message)`
- `notFoundResponse($message)`
- `createdResponse($data, $message)`

### Middleware Support
`FormatApiResponse` middleware đảm bảo format thống nhất cho tất cả response, kể cả những response chưa sử dụng trait.

## Benefits

1. **Consistency**: Tất cả API có cùng format response
2. **Predictability**: Client dễ dàng parse và xử lý response
3. **Error Handling**: Format lỗi thống nhất giúp debug dễ dàng
4. **Timestamps**: Theo dõi thời gian response để monitoring
5. **Success Flag**: Dễ dàng kiểm tra trạng thái success/error
