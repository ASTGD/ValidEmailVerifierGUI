<?php

$policyDefaults = [
    'dns_timeout_ms' => (int) env('ENGINE_POLICY_DNS_TIMEOUT_MS', 2000),
    'smtp_connect_timeout_ms' => (int) env('ENGINE_POLICY_SMTP_CONNECT_TIMEOUT_MS', 2000),
    'smtp_read_timeout_ms' => (int) env('ENGINE_POLICY_SMTP_READ_TIMEOUT_MS', 2000),
    'max_mx_attempts' => (int) env('ENGINE_POLICY_MAX_MX_ATTEMPTS', 2),
    'max_concurrency_default' => (int) env('ENGINE_POLICY_MAX_CONCURRENCY_DEFAULT', 1),
    'per_domain_concurrency' => (int) env('ENGINE_POLICY_PER_DOMAIN_CONCURRENCY', 2),
    'global_connects_per_minute' => env('ENGINE_POLICY_GLOBAL_CONNECTS_PER_MINUTE') !== null
        ? (int) env('ENGINE_POLICY_GLOBAL_CONNECTS_PER_MINUTE')
        : null,
    'tempfail_backoff_seconds' => env('ENGINE_POLICY_TEMPFAIL_BACKOFF_SECONDS') !== null
        ? (int) env('ENGINE_POLICY_TEMPFAIL_BACKOFF_SECONDS')
        : null,
    'circuit_breaker_tempfail_rate' => env('ENGINE_POLICY_CIRCUIT_BREAKER_TEMPFAIL_RATE') !== null
        ? (float) env('ENGINE_POLICY_CIRCUIT_BREAKER_TEMPFAIL_RATE')
        : null,
];

return [
    'lease_seconds' => (int) env('ENGINE_LEASE_SECONDS', env('VERIFIER_ENGINE_CLAIM_LEASE_SECONDS', 600)),
    'max_attempts' => (int) env('ENGINE_MAX_ATTEMPTS', 3),
    'chunk_size_default' => (int) env('ENGINE_CHUNK_SIZE', env('VERIFIER_CHUNK_SIZE', 5000)),
    'max_emails_per_upload' => (int) env('ENGINE_MAX_EMAILS_PER_UPLOAD', env('VERIFIER_MAX_EMAILS_PER_UPLOAD', 100000)),
    'cache_batch_size' => (int) env('ENGINE_CACHE_BATCH_SIZE', env('VERIFIER_CACHE_BATCH_SIZE', 100)),
    'cache_freshness_days' => (int) env('ENGINE_CACHE_FRESHNESS_DAYS', env('VERIFIER_CACHE_FRESHNESS_DAYS', 30)),
    'cache_store_driver' => env('ENGINE_CACHE_STORE_DRIVER'),
    'dedupe_in_memory_limit' => (int) env('ENGINE_DEDUPE_IN_MEMORY_LIMIT', env('VERIFIER_DEDUPE_IN_MEMORY_LIMIT', 100000)),
    'xlsx_row_batch_size' => (int) env('ENGINE_XLSX_ROW_BATCH_SIZE', env('VERIFIER_XLSX_ROW_BATCH_SIZE', 1000)),
    'signed_url_expiry_seconds' => (int) env('ENGINE_SIGNED_URL_EXPIRY_SECONDS', env('VERIFIER_SIGNED_URL_EXPIRY_SECONDS', 300)),
    'chunk_inputs_prefix' => env('ENGINE_CHUNK_INPUT_PREFIX', 'chunks'),
    'chunk_outputs_prefix' => env('ENGINE_CHUNK_OUTPUT_PREFIX', 'results/chunks'),
    'result_prefix' => env('ENGINE_RESULT_PREFIX', 'results/jobs'),
    'finalization_temp_disk' => env('ENGINE_FINALIZATION_TEMP_DISK', 'local'),
    'finalization_write_mode' => env('ENGINE_FINALIZATION_WRITE_MODE', 'stream_to_temp_then_upload'),
    'health_window_days' => (int) env('ENGINE_HEALTH_WINDOW_DAYS', 7),
    'engine_paused' => (bool) env('ENGINE_PAUSED', false),
    'enhanced_mode_enabled' => env('ENGINE_ENHANCED_MODE_ENABLED', false),
    'policy_contract_version' => env('ENGINE_POLICY_CONTRACT_VERSION', 'v1'),
    'policy_defaults' => [
        'standard' => array_merge($policyDefaults, [
            'enabled' => true,
        ]),
        'enhanced' => array_merge($policyDefaults, [
            'enabled' => (bool) env('ENGINE_POLICY_ENHANCED_ENABLED', false),
        ]),
    ],
    'feedback_imports_prefix' => env('ENGINE_FEEDBACK_IMPORTS_PREFIX', 'feedback/imports'),
    'feedback_api_enabled' => (bool) env(
        'ENGINE_FEEDBACK_API_ENABLED',
        in_array(env('APP_ENV'), ['local', 'testing'], true)
    ),
    'feedback_max_items_per_request' => (int) env('ENGINE_FEEDBACK_MAX_ITEMS_PER_REQUEST', 500),
    'feedback_max_payload_kb' => (int) env('ENGINE_FEEDBACK_MAX_PAYLOAD_KB', 512),
    'feedback_rate_limit_per_minute' => (int) env('ENGINE_FEEDBACK_RATE_LIMIT_PER_MINUTE', 30),
    'feedback_retention_days' => (int) env('ENGINE_FEEDBACK_RETENTION_DAYS', 180),
    'feedback_import_retention_days' => (int) env('ENGINE_FEEDBACK_IMPORT_RETENTION_DAYS', 90),
];
