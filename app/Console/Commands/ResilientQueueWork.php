<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Queue\Console\WorkCommand;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use RedisException;

class ResilientQueueWork extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:work-resilient 
                            {connection? : The name of the queue connection to work}
                            {--queue= : The names of the queues to work}
                            {--daemon : Run the worker in daemon mode (Deprecated)}
                            {--once : Only process the next job on the queue}
                            {--stop-when-empty : Stop when the queue is empty}
                            {--delay=0 : The number of seconds to delay failed jobs (Deprecated)}
                            {--backoff=0 : The number of seconds to wait before retrying a job that encountered an uncaught exception}
                            {--max-jobs=0 : The number of jobs to process before stopping}
                            {--max-time=0 : The maximum number of seconds the worker should run}
                            {--force : Force the worker to run even in maintenance mode}
                            {--memory=128 : The memory limit in megabytes}
                            {--sleep=3 : Number of seconds to sleep when no job is available}
                            {--rest=0 : Number of seconds to rest between jobs}
                            {--timeout=60 : The number of seconds a child process can run}
                            {--tries=1 : Number of times to attempt a job before logging it failed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start processing queue jobs with Redis connection resilience';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $maxRestarts = config('queue.max_restarts', 10);
        $restartDelay = config('queue.restart_delay', 5); // seconds
        $restartCount = 0;

        while ($restartCount < $maxRestarts) {
            try {
                $this->info("Starting resilient queue worker (attempt " . ($restartCount + 1) . "/{$maxRestarts})");
                
                // Test Redis connection before starting
                $this->testRedisConnection();
                
                // Run the standard queue work command
                $exitCode = $this->call('queue:work', $this->getQueueWorkArguments());
                
                if ($exitCode === 0) {
                    $this->info('Queue worker exited normally');
                    break;
                }
                
                throw new \Exception("Queue worker exited with code {$exitCode}");
                
            } catch (RedisException $e) {
                $restartCount++;
                $this->error("Redis connection failed: " . $e->getMessage());
                
                if ($restartCount >= $maxRestarts) {
                    $this->error("Maximum restart attempts ({$maxRestarts}) reached. Exiting.");
                    Log::critical('Queue worker failed after maximum restart attempts', [
                        'error' => $e->getMessage(),
                        'restart_count' => $restartCount,
                        'max_restarts' => $maxRestarts
                    ]);
                    return 1;
                }
                
                $this->warn("Restarting in {$restartDelay} seconds... (attempt {$restartCount}/{$maxRestarts})");
                Log::warning('Queue worker restarting due to Redis failure', [
                    'error' => $e->getMessage(),
                    'restart_count' => $restartCount,
                    'restart_delay' => $restartDelay
                ]);
                
                sleep($restartDelay);
                
                // Exponential backoff for restart delay
                $restartDelay = min($restartDelay * 2, 60);
                
            } catch (\Exception $e) {
                $restartCount++;
                $this->error("Queue worker failed: " . $e->getMessage());
                
                if ($restartCount >= $maxRestarts) {
                    $this->error("Maximum restart attempts ({$maxRestarts}) reached. Exiting.");
                    Log::critical('Queue worker failed after maximum restart attempts', [
                        'error' => $e->getMessage(),
                        'restart_count' => $restartCount,
                        'max_restarts' => $maxRestarts
                    ]);
                    return 1;
                }
                
                $this->warn("Restarting in {$restartDelay} seconds... (attempt {$restartCount}/{$maxRestarts})");
                sleep($restartDelay);
                
                // Increase delay for non-Redis errors too
                $restartDelay = min($restartDelay * 1.5, 30);
            }
        }

        return 0;
    }

    /**
     * Test Redis connection before starting the worker.
     */
    private function testRedisConnection(): void
    {
        try {
            $redis = app('redis');
            $redis->ping();
            $this->info('Redis connection test successful');
        } catch (RedisException $e) {
            $this->error('Redis connection test failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get arguments to pass to the queue:work command.
     */
    private function getQueueWorkArguments(): array
    {
        $arguments = [];
        
        if ($this->argument('connection')) {
            $arguments['connection'] = $this->argument('connection');
        }
        
        $options = [
            'queue', 'once', 'stop-when-empty', 'delay', 'backoff', 'max-jobs', 
            'max-time', 'force', 'memory', 'sleep', 'rest', 'timeout', 'tries'
        ];
        
        foreach ($options as $option) {
            if ($this->option($option) !== null && $this->option($option) !== false) {
                $arguments["--{$option}"] = $this->option($option);
            }
        }
        
        return $arguments;
    }
}
