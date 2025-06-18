<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use RedisException;

class RedisHealthCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redis:health-check 
                            {--continuous : Run continuously}
                            {--interval=60 : Check interval in seconds for continuous mode}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check Redis connection health';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('continuous')) {
            $this->runContinuous();
        } else {
            return $this->runOnce();
        }
    }

    /**
     * Run health check once.
     */
    private function runOnce(): int
    {
        $this->info('Performing Redis health check...');
        
        $checks = [
            'default' => $this->checkConnection('default'),
            'cache' => $this->checkConnection('cache'),
        ];
        
        $allHealthy = true;
        
        foreach ($checks as $connection => $result) {
            if ($result['healthy']) {
                $this->info("✓ Redis connection '{$connection}': Healthy (response: {$result['response_time']}ms)");
            } else {
                $this->error("✗ Redis connection '{$connection}': Failed - {$result['error']}");
                $allHealthy = false;
            }
        }
        
        if ($allHealthy) {
            $this->info('All Redis connections are healthy!');
            return 0;
        } else {
            $this->error('Some Redis connections are failing!');
            return 1;
        }
    }

    /**
     * Run health check continuously.
     */
    private function runContinuous(): void
    {
        $interval = (int) $this->option('interval');
        $this->info("Starting continuous Redis health monitoring (every {$interval}s)...");
        $this->info('Press Ctrl+C to stop');
        
        while (true) {
            $timestamp = now()->format('Y-m-d H:i:s');
            $this->line("[$timestamp] Checking Redis health...");
            
            $this->runOnce();
            
            sleep($interval);
        }
    }

    /**
     * Check a specific Redis connection.
     */
    private function checkConnection(string $connection): array
    {
        try {
            $startTime = microtime(true);
            
            $redis = Redis::connection($connection);
            $result = $redis->ping();
            
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            // Additional checks
            $testKey = 'health_check_' . time();
            $redis->set($testKey, 'test', 'EX', 10);
            $retrieved = $redis->get($testKey);
            $redis->del($testKey);
            
            if ($retrieved !== 'test') {
                throw new \Exception('Read/write test failed');
            }
            
            return [
                'healthy' => true,
                'response_time' => $responseTime,
                'result' => $result
            ];
            
        } catch (RedisException $e) {
            Log::warning("Redis health check failed for connection '{$connection}'", [
                'error' => $e->getMessage(),
                'connection' => $connection
            ]);
            
            return [
                'healthy' => false,
                'error' => $e->getMessage()
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
