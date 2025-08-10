<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class FormatApiResponse
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only format JSON responses for API routes
        if ($response instanceof JsonResponse && $request->is('api/*')) {
            $data = $response->getData(true);
            
            // If response already has our format, don't modify it
            if (isset($data['success']) && isset($data['timestamp'])) {
                return $response;
            }

            // Format the response
            $formattedData = [
                'success' => $response->isSuccessful(),
                'message' => $data['message'] ?? ($response->isSuccessful() ? 'Success' : 'Error'),
                'data' => $data,
                'timestamp' => now()->toISOString()
            ];

            $response->setData($formattedData);
        }

        return $response;
    }
}
