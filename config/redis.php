<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Redis Connection Retry Settings
    |--------------------------------------------------------------------------
    |
    | These settings control how Redis connections handle failures and retries.
    | When Redis connections fail, the system will retry the connection
    | automatically according to these settings.
    |
    */

    'max_retries' => (int) env('REDIS_MAX_RETRIES', 3),
    'retry_delay' => (int) env('REDIS_RETRY_DELAY', 1), // Initial delay in seconds
    'max_retry_delay' => (int) env('REDIS_MAX_RETRY_DELAY', 30), // Maximum delay in seconds
    'queue_read_timeout' => (int) env('REDIS_QUEUE_READ_TIMEOUT', 30), // Timeout for queue blocking operations

    /*
    |--------------------------------------------------------------------------
    | Redis Health Check Settings
    |--------------------------------------------------------------------------
    |
    | These settings control Redis health monitoring to detect connection
    | issues early and take preventive action.
    |
    */

    'health_check_interval' => (int) env('REDIS_HEALTH_CHECK_INTERVAL', 60), // seconds
    'health_check_enabled' => env('REDIS_HEALTH_CHECK_ENABLED', 'true') === 'true',

    /*
    |--------------------------------------------------------------------------
    | Redis Connection Pool Settings
    |--------------------------------------------------------------------------
    |
    | Connection pool settings to manage Redis connections more efficiently
    | and prevent connection exhaustion.
    |
    */

    'pool_size' => (int) env('REDIS_POOL_SIZE', 10),
    'pool_timeout' => (int) env('REDIS_POOL_TIMEOUT', 30),
];
