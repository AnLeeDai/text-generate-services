<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Response Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for API response formatting
    |
    */

    'default_success_message' => 'Success',
    'default_error_message' => 'Error',
    'include_timestamp' => true,
    'timestamp_format' => 'ISO8601', // ISO8601, RFC3339, custom
    'always_include_data_field' => true,
    
    /*
    |--------------------------------------------------------------------------
    | Error Response Configuration
    |--------------------------------------------------------------------------
    */
    
    'error_messages' => [
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden', 
        404 => 'Not Found',
        422 => 'Validation Failed',
        500 => 'Internal Server Error',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Success Response Configuration
    |--------------------------------------------------------------------------
    */
    
    'success_messages' => [
        200 => 'Success',
        201 => 'Created Successfully',
        204 => 'No Content',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Response Headers
    |--------------------------------------------------------------------------
    */
    
    'headers' => [
        'Content-Type' => 'application/json',
        'X-API-Version' => '1.0',
    ],
];
