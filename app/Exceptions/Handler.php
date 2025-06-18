<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Log;
use RedisException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            // Enhanced Redis exception handling
            if ($e instanceof RedisException) {
                Log::warning('Redis connection issue detected', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'context' => $this->getRedisContext()
                ]);
                
                // Don't report Redis exceptions as frequently to avoid spam
                if ($this->shouldThrottleRedisReport($e)) {
                    return false;
                }
            }
        });

        // Handle Redis exceptions in queue jobs
        $this->renderable(function (RedisException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Service temporarily unavailable. Please try again.',
                    'code' => 'REDIS_CONNECTION_ERROR'
                ], 503);
            }
            
            return response()->view('errors.503', [], 503);
        });
    }

    /**
     * Get context information for Redis errors.
     */
    private function getRedisContext(): array
    {
        return [
            'redis_host' => config('database.redis.default.host'),
            'redis_port' => config('database.redis.default.port'),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Throttle Redis error reporting to prevent log spam.
     */
    private function shouldThrottleRedisReport(RedisException $e): bool
    {
        $cacheKey = 'redis_error_throttle:' . md5($e->getMessage());
        $cache = app('cache');
        
        try {
            if ($cache->has($cacheKey)) {
                return true; // Throttle this error
            }
            
            // Cache this error for 5 minutes to throttle similar errors
            $cache->put($cacheKey, true, 300);
            return false; // Don't throttle, report it
        } catch (RedisException $cacheException) {
            // If cache is also failing (Redis), don't throttle
            return false;
        }
    }
}
