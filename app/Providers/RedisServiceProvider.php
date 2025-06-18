<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Redis;
use Illuminate\Redis\RedisManager;
use Illuminate\Contracts\Redis\Factory;
use RedisException;
use Illuminate\Support\Facades\Log;

class RedisServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Override the Redis manager to add retry logic
        $this->app->singleton('redis', function ($app) {
            $config = $app->make('config')->get('database.redis', []);
            
            return new class($app, 'phpredis', $config) extends RedisManager {
                /**
                 * Get a Redis connection with retry logic.
                 */
                public function connection($name = null)
                {
                    $maxRetries = config('redis.max_retries', 3);
                    $retryDelay = config('redis.retry_delay', 1); // seconds
                    
                    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                        try {
                            $connection = parent::connection($name);
                            
                            // Configure connection-specific timeouts for queue operations
                            if ($this->isQueueConnection($connection)) {
                                $this->configureQueueConnection($connection);
                            }
                            
                            return $connection;
                        } catch (RedisException $e) {
                            if ($attempt === $maxRetries) {
                                Log::error("Redis connection failed after {$maxRetries} attempts", [
                                    'connection' => $name,
                                    'error' => $e->getMessage(),
                                    'trace' => $e->getTraceAsString()
                                ]);
                                throw $e;
                            }
                            
                            Log::warning("Redis connection attempt {$attempt} failed, retrying in {$retryDelay}s", [
                                'connection' => $name,
                                'error' => $e->getMessage(),
                                'attempt' => $attempt,
                                'max_retries' => $maxRetries
                            ]);
                            
                            sleep($retryDelay);
                            
                            // Exponential backoff
                            $retryDelay = min($retryDelay * 2, 30);
                        }
                    }
                }
                
                /**
                 * Configure connection for queue operations.
                 */
                private function configureQueueConnection($connection)
                {
                    try {
                        // Set shorter timeouts for blocking operations
                        $connection->setOption(\Redis::OPT_READ_TIMEOUT, config('redis.queue_read_timeout', 30));
                        $connection->setOption(\Redis::OPT_TCP_KEEPALIVE, 1);
                    } catch (\Exception $e) {
                        Log::debug('Could not configure Redis connection options', [
                            'error' => $e->getMessage()
                        ]);
                    }
                }
                
                /**
                 * Check if this is a queue-related connection.
                 */
                private function isQueueConnection($connection): bool
                {
                    // Check if we're in a queue context
                    return app()->runningInConsole() && 
                           (str_contains(request()->server('argv.0', ''), 'queue:work') ||
                            str_contains(request()->server('argv.0', ''), 'queue:work-resilient'));
                }
                
                /**
                 * Run a command against the Redis database with retry logic.
                 */
                public function command($method, array $parameters = [])
                {
                    $maxRetries = $this->getRetriesForMethod($method);
                    $retryDelay = config('redis.retry_delay', 1);
                    
                    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                        try {
                            return $this->connection()->command($method, $parameters);
                        } catch (RedisException $e) {
                            // For blocking operations, don't retry as aggressively
                            if ($this->isBlockingOperation($method) && $attempt === 1) {
                                Log::info("Blocking Redis operation '{$method}' failed, will restart worker", [
                                    'method' => $method,
                                    'error' => $e->getMessage()
                                ]);
                                throw $e; // Let the resilient worker handle this
                            }
                            
                            if ($attempt === $maxRetries) {
                                Log::error("Redis command '{$method}' failed after {$maxRetries} attempts", [
                                    'method' => $method,
                                    'parameters' => $parameters,
                                    'error' => $e->getMessage()
                                ]);
                                throw $e;
                            }
                            
                            Log::warning("Redis command '{$method}' attempt {$attempt} failed, retrying", [
                                'method' => $method,
                                'error' => $e->getMessage(),
                                'attempt' => $attempt
                            ]);
                            
                            // Reset connection on failure
                            $this->disconnect();
                            
                            sleep($retryDelay);
                            $retryDelay = min($retryDelay * 2, 15);
                        }
                    }
                }
                
                /**
                 * Get retry count based on method type.
                 */
                private function getRetriesForMethod(string $method): int
                {
                    if ($this->isBlockingOperation($method)) {
                        return 1; // Don't retry blocking operations, let worker restart
                    }
                    
                    return config('redis.max_retries', 3);
                }
                
                /**
                 * Check if this is a blocking operation.
                 */
                private function isBlockingOperation(string $method): bool
                {
                    return in_array(strtolower($method), [
                        'blpop', 'brpop', 'blmove', 'brpoplpush', 'bzpopmin', 'bzpopmax'
                    ]);
                }
            };
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
