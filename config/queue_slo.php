<?php

return [
    'enabled' => (bool) env('QUEUE_SLO_ENABLED', true),

    'retry_safety_buffer_seconds' => (int) env('QUEUE_SLO_RETRY_SAFETY_BUFFER_SECONDS', 30),

    'critical_lanes' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('QUEUE_SLO_CRITICAL_LANES', 'default,finalize'))
    ))),

    'heavy_submission_lanes' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('QUEUE_SLO_HEAVY_SUBMISSION_LANES', 'prepare,parse,imports,cache_writeback'))
    ))),

    'retry_contracts' => [
        'redis' => [
            'timeout' => (int) env('QUEUE_SLO_REDIS_TIMEOUT_SECONDS', 90),
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 180),
        ],
        'redis_prepare' => [
            'timeout' => (int) env('QUEUE_SLO_PREPARE_TIMEOUT_SECONDS', 120),
            'retry_after' => (int) env('REDIS_PREPARE_RETRY_AFTER', 300),
        ],
        'redis_parse' => [
            'timeout' => (int) env('QUEUE_SLO_PARSE_TIMEOUT_SECONDS', 1800),
            'retry_after' => (int) env('REDIS_PARSE_RETRY_AFTER', 3600),
        ],
        'redis_finalize' => [
            'timeout' => (int) env('QUEUE_SLO_FINALIZE_TIMEOUT_SECONDS', 900),
            'retry_after' => (int) env('REDIS_FINALIZE_RETRY_AFTER', 1200),
        ],
        'redis_import' => [
            'timeout' => (int) env('QUEUE_SLO_IMPORT_TIMEOUT_SECONDS', 1200),
            'retry_after' => (int) env('REDIS_IMPORT_RETRY_AFTER', 1800),
        ],
        'redis_cache_writeback' => [
            'timeout' => (int) env('QUEUE_SLO_CACHE_WRITEBACK_TIMEOUT_SECONDS', 1800),
            'retry_after' => (int) env('REDIS_CACHE_WRITEBACK_RETRY_AFTER', 3600),
        ],
    ],

    'backpressure' => [
        'enabled' => (bool) env('QUEUE_BACKPRESSURE_ENABLED', true),
        'block_on_statuses' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('QUEUE_BACKPRESSURE_BLOCK_ON_STATUSES', 'critical'))
        ))),
        'max_report_age_seconds' => (int) env('QUEUE_BACKPRESSURE_MAX_REPORT_AGE_SECONDS', 180),
    ],

    'escalation' => [
        'critical_email' => env('QUEUE_HEALTH_ONCALL_EMAIL'),
        'critical_slack_webhook_url' => env('QUEUE_HEALTH_ONCALL_SLACK_WEBHOOK_URL'),
    ],

    'rollups' => [
        'queue_metrics_retention_days' => (int) env('QUEUE_METRICS_RETENTION_DAYS', 14),
        'queue_metrics_rollup_retention_days' => (int) env('QUEUE_METRICS_ROLLUP_RETENTION_DAYS', 90),
        'failed_jobs_retention_hours' => (int) env('QUEUE_FAILED_JOBS_RETENTION_HOURS', 168),
    ],
];
