<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponseTrait
{
    /**
     * Success response with data
     */
    protected function successResponse($data = null, string $message = null, int $statusCode = 200): JsonResponse
    {
        $message = $message ?: (config('api_response.success_messages.' . $statusCode) ?: config('api_response.default_success_message'));
        
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];
        
        if (config('api_response.include_timestamp', true)) {
            $response['timestamp'] = now()->toISOString();
        }

        $headers = config('api_response.headers', []);
        
        return response()->json($response, $statusCode, $headers, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Error response
     */
    protected function errorResponse(string $message = null, $errors = null, int $statusCode = 400): JsonResponse
    {
        $message = $message ?: (config('api_response.error_messages.' . $statusCode) ?: config('api_response.default_error_message'));
        
        $response = [
            'success' => false,
            'message' => $message,
        ];
        
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        
        if (config('api_response.include_timestamp', true)) {
            $response['timestamp'] = now()->toISOString();
        }

        $headers = config('api_response.headers', []);
        
        return response()->json($response, $statusCode, $headers, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Validation error response
     */
    protected function validationErrorResponse($errors, string $message = 'Validation failed'): JsonResponse
    {
        return $this->errorResponse($message, $errors, 422);
    }

    /**
     * Not found response
     */
    protected function notFoundResponse(string $message = 'Resource not found'): JsonResponse
    {
        return $this->errorResponse($message, null, 404);
    }

    /**
     * Created response
     */
    protected function createdResponse($data = null, string $message = 'Created successfully'): JsonResponse
    {
        return $this->successResponse($data, $message, 201);
    }

    /**
     * No content response
     */
    protected function noContentResponse(): JsonResponse
    {
        return response()->json(null, 204);
    }
}
