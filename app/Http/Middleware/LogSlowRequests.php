<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogSlowRequests
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);
        
        $response = $next($request);
        
        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000; // Convert to milliseconds
        
        // Log requests that take longer than the configured threshold (default 3 seconds)
        $threshold = config('app.slow_request_threshold', 3000); // milliseconds
        
        if ($duration > $threshold) {
            Log::channel('slow_requests')->warning('Slow API request detected', [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'path' => $request->path(),
                'duration_ms' => round($duration, 2),
                'duration_seconds' => round($duration / 1000, 2),
                'user_id' => $request->user()?->id,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'response_status' => $response->getStatusCode(),
                'memory_usage_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                'timestamp' => now()->toISOString(),
            ]);
        }
        
        return $response;
    }
}
