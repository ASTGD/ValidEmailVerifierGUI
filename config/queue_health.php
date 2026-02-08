<?php

$requiredSupervisors = (string) env(
    'QUEUE_HEALTH_REQUIRED_SUPERVISORS',
    'supervisor-default,supervisor-prepare,supervisor-parse,supervisor-smtp-probe,supervisor-finalize,supervisor-imports,supervisor-cache-writeback'
);

$requiredSupervisors = array_values(array_filter(array_map('trim', explode(',', $requiredSupervisors))));

return [
    'enabled' => (bool) env('QUEUE_HEALTH_ENABLED', true),

    'report_cache_key' => env('QUEUE_HEALTH_REPORT_CACHE_KEY', 'queue_health:last_report'),

    'max_metric_age_seconds' => (int) env('QUEUE_HEALTH_MAX_METRIC_AGE_SECONDS', 180),

    'required_supervisors' => $requiredSupervisors,

    'alerts' => [
        'enabled' => (bool) env('QUEUE_HEALTH_ALERTS_ENABLED', true),
        'cooldown_seconds' => (int) env('QUEUE_HEALTH_ALERT_COOLDOWN_SECONDS', 600),
        'email' => env('QUEUE_HEALTH_ALERT_EMAIL'),
        'slack_webhook_url' => env('QUEUE_HEALTH_SLACK_WEBHOOK_URL'),
        'slack_channel' => env('QUEUE_HEALTH_SLACK_CHANNEL', '#queue-alerts'),
        'cache_prefix' => env('QUEUE_HEALTH_ALERT_CACHE_PREFIX', 'queue_health:alerts'),
    ],

    'lanes' => [
        'default' => [
            'driver' => 'redis',
            'queue' => env('REDIS_QUEUE', 'default'),
            'max_depth' => (int) env('QUEUE_SLO_DEFAULT_MAX_DEPTH', 100),
            'max_oldest_age_seconds' => (int) env('QUEUE_SLO_DEFAULT_MAX_AGE_SECONDS', 300),
        ],
        'prepare' => [
            'driver' => 'redis_prepare',
            'queue' => env('QUEUE_PREPARE_NAME', 'prepare'),
            'max_depth' => (int) env('QUEUE_SLO_PREPARE_MAX_DEPTH', 100),
            'max_oldest_age_seconds' => (int) env('QUEUE_SLO_PREPARE_MAX_AGE_SECONDS', 120),
        ],
        'parse' => [
            'driver' => 'redis_parse',
            'queue' => env('QUEUE_PARSE_NAME', 'parse'),
            'max_depth' => (int) env('QUEUE_SLO_PARSE_MAX_DEPTH', 20),
            'max_oldest_age_seconds' => (int) env('QUEUE_SLO_PARSE_MAX_AGE_SECONDS', 900),
        ],
        'smtp_probe' => [
            'driver' => 'redis_smtp_probe',
            'queue' => env('QUEUE_SMTP_PROBE_NAME', 'smtp_probe'),
            'max_depth' => (int) env('QUEUE_SLO_SMTP_PROBE_MAX_DEPTH', 60),
            'max_oldest_age_seconds' => (int) env('QUEUE_SLO_SMTP_PROBE_MAX_AGE_SECONDS', 1800),
        ],
        'finalize' => [
            'driver' => 'redis_finalize',
            'queue' => env('QUEUE_FINALIZE_NAME', 'finalize'),
            'max_depth' => (int) env('QUEUE_SLO_FINALIZE_MAX_DEPTH', 50),
            'max_oldest_age_seconds' => (int) env('QUEUE_SLO_FINALIZE_MAX_AGE_SECONDS', 120),
        ],
        'imports' => [
            'driver' => 'redis_import',
            'queue' => env('QUEUE_IMPORT_NAME', 'imports'),
            'max_depth' => (int) env('QUEUE_SLO_IMPORTS_MAX_DEPTH', 20),
            'max_oldest_age_seconds' => (int) env('QUEUE_SLO_IMPORTS_MAX_AGE_SECONDS', 1800),
        ],
        'cache_writeback' => [
            'driver' => 'redis_cache_writeback',
            'queue' => env('QUEUE_CACHE_WRITEBACK_NAME', 'cache_writeback'),
            'max_depth' => (int) env('QUEUE_SLO_CACHE_WRITEBACK_MAX_DEPTH', 200),
            'max_oldest_age_seconds' => (int) env('QUEUE_SLO_CACHE_WRITEBACK_MAX_AGE_SECONDS', 3600),
        ],
    ],
];
