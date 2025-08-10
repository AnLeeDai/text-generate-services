<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LocalhostOnly
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Chỉ cho phép truy cập từ localhost
        $allowedIps = ['127.0.0.1', '::1'];
        
        $clientIp = $request->ip();
        
        if (!in_array($clientIp, $allowedIps)) {
            abort(403, 'Access denied. This service is only available from localhost.');
        }

        return $next($request);
    }
}
