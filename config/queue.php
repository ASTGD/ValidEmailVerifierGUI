<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection Name
    |--------------------------------------------------------------------------
    |
    | Laravel's queue supports a variety of backends via a single, unified
    | API, giving you convenient access to each backend using identical
    | syntax for each. The default queue connection is defined below.
    |
    */

    'default' => env('QUEUE_CONNECTION', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Queue Connections
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection options for every queue backend
    | used by your application. An example configuration is provided for
    | each backend supported by Laravel. You're also free to add more.
    |
    | Drivers: "sync", "database", "beanstalkd", "sqs", "redis",
    |          "deferred", "background", "failover", "null"
    |
    */

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_QUEUE_CONNECTION'),
            'table' => env('DB_QUEUE_TABLE', 'jobs'),
            'queue' => env('DB_QUEUE', 'default'),
            'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 90),
            'after_commit' => false,
        ],

        'beanstalkd' => [
            'driver' => 'beanstalkd',
            'host' => env('BEANSTALKD_QUEUE_HOST', 'localhost'),
            'queue' => env('BEANSTALKD_QUEUE', 'default'),
            'retry_after' => (int) env('BEANSTALKD_QUEUE_RETRY_AFTER', 90),
            'block_for' => 0,
            'after_commit' => false,
        ],

        'sqs' => [
            'driver' => 'sqs',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'prefix' => env('SQS_PREFIX', 'https://sqs.us-east-1.amazonaws.com/your-account-id'),
            'queue' => env('SQS_QUEUE', 'default'),
            'suffix' => env('SQS_SUFFIX'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'after_commit' => false,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 180),
            'block_for' => null,
            'after_commit' => false,
        ],

        'redis_prepare' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('QUEUE_PREPARE_NAME', 'prepare'),
            'retry_after' => (int) env('REDIS_PREPARE_RETRY_AFTER', 300),
            'block_for' => (int) env('REDIS_PREPARE_BLOCK_FOR', 5),
            'after_commit' => false,
        ],

        'redis_parse' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('QUEUE_PARSE_NAME', 'parse'),
            'retry_after' => (int) env('REDIS_PARSE_RETRY_AFTER', 3600),
            'block_for' => (int) env('REDIS_PARSE_BLOCK_FOR', 5),
            'after_commit' => false,
        ],

        'redis_smtp_probe' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('QUEUE_SMTP_PROBE_NAME', 'smtp_probe'),
            'retry_after' => (int) env('REDIS_SMTP_PROBE_RETRY_AFTER', 3600),
            'block_for' => (int) env('REDIS_SMTP_PROBE_BLOCK_FOR', 5),
            'after_commit' => false,
        ],

        'redis_finalize' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('QUEUE_FINALIZE_NAME', 'finalize'),
            'retry_after' => (int) env('REDIS_FINALIZE_RETRY_AFTER', 1200),
            'block_for' => (int) env('REDIS_FINALIZE_BLOCK_FOR', 5),
            'after_commit' => false,
        ],

        'redis_import' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('QUEUE_IMPORT_NAME', 'imports'),
            'retry_after' => (int) env('REDIS_IMPORT_RETRY_AFTER', 1800),
            'block_for' => (int) env('REDIS_IMPORT_BLOCK_FOR', 5),
            'after_commit' => false,
        ],

        'redis_cache_writeback' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('QUEUE_CACHE_WRITEBACK_NAME', 'cache_writeback'),
            'retry_after' => (int) env('REDIS_CACHE_WRITEBACK_RETRY_AFTER', 3600),
            'block_for' => (int) env('REDIS_CACHE_WRITEBACK_BLOCK_FOR', 5),
            'after_commit' => false,
        ],

        'redis_seed_send_dispatch' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('QUEUE_SEED_SEND_DISPATCH_NAME', 'seed_send_dispatch'),
            'retry_after' => (int) env('REDIS_SEED_SEND_DISPATCH_RETRY_AFTER', 3600),
            'block_for' => (int) env('REDIS_SEED_SEND_DISPATCH_BLOCK_FOR', 5),
            'after_commit' => false,
        ],

        'redis_seed_send_events' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('QUEUE_SEED_SEND_EVENTS_NAME', 'seed_send_events'),
            'retry_after' => (int) env('REDIS_SEED_SEND_EVENTS_RETRY_AFTER', 900),
            'block_for' => (int) env('REDIS_SEED_SEND_EVENTS_BLOCK_FOR', 5),
            'after_commit' => false,
        ],

        'redis_seed_send_reconcile' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('QUEUE_SEED_SEND_RECONCILE_NAME', 'seed_send_reconcile'),
            'retry_after' => (int) env('REDIS_SEED_SEND_RECONCILE_RETRY_AFTER', 3600),
            'block_for' => (int) env('REDIS_SEED_SEND_RECONCILE_BLOCK_FOR', 5),
            'after_commit' => false,
        ],

        'deferred' => [
            'driver' => 'deferred',
        ],

        'background' => [
            'driver' => 'background',
        ],

        'failover' => [
            'driver' => 'failover',
            'connections' => [
                'database',
                'deferred',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Job Batching
    |--------------------------------------------------------------------------
    |
    | The following options configure the database and table that store job
    | batching information. These options can be updated to any database
    | connection and table which has been defined by your application.
    |
    */

    'batching' => [
        'database' => env('DB_CONNECTION', 'sqlite'),
        'table' => 'job_batches',
    ],

    /*
    |--------------------------------------------------------------------------
    | Failed Queue Jobs
    |--------------------------------------------------------------------------
    |
    | These options configure the behavior of failed queue job logging so you
    | can control how and where failed jobs are stored. Laravel ships with
    | support for storing failed jobs in a simple file or in a database.
    |
    | Supported drivers: "database-uuids", "dynamodb", "file", "null"
    |
    */

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'sqlite'),
        'table' => 'failed_jobs',
    ],

];
