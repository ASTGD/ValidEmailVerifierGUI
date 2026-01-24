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
    'role_accounts_behavior' => env('ENGINE_ROLE_ACCOUNTS_BEHAVIOR', 'risky'),
    'role_accounts_list' => env('ENGINE_ROLE_ACCOUNTS_LIST', 'info,admin,support,sales,contact,hello,hr'),
    'catch_all_policy' => env('ENGINE_CATCH_ALL_POLICY', 'risky_only'),
    'catch_all_promote_threshold' => env('ENGINE_CATCH_ALL_PROMOTE_THRESHOLD') !== null
        ? (int) env('ENGINE_CATCH_ALL_PROMOTE_THRESHOLD')
        : null,
    'policy_contract_version' => env('ENGINE_POLICY_CONTRACT_VERSION', 'v1'),
    'policy_defaults' => [
        'standard' => array_merge($policyDefaults, [
            'enabled' => true,
            'catch_all_detection_enabled' => (bool) env('ENGINE_POLICY_CATCH_ALL_ENABLED_STANDARD', false),
        ]),
        'enhanced' => array_merge($policyDefaults, [
            'enabled' => (bool) env('ENGINE_POLICY_ENHANCED_ENABLED', false),
            'catch_all_detection_enabled' => (bool) env('ENGINE_POLICY_CATCH_ALL_ENABLED_ENHANCED', true),
        ]),
    ],
    'worker_registry' => env('ENGINE_WORKER_REGISTRY'),
    'worker_image' => env('ENGINE_WORKER_IMAGE'),
    'worker_env_path' => env('ENGINE_WORKER_ENV_PATH'),
    'worker_provisioning_disk' => env('ENGINE_WORKER_PROVISIONING_DISK', 'local'),
    'worker_provisioning_prefix' => env('ENGINE_WORKER_PROVISIONING_PREFIX', 'provisioning/worker'),
    'worker_provisioning_ttl_minutes' => (int) env('ENGINE_WORKER_PROVISIONING_TTL_MINUTES', 60),
    'deliverability_score' => [
        'base' => [
            'valid' => (int) env('ENGINE_SCORE_BASE_VALID', 90),
            'invalid' => (int) env('ENGINE_SCORE_BASE_INVALID', 10),
            'risky' => (int) env('ENGINE_SCORE_BASE_RISKY', 55),
        ],
        'reason_overrides' => [
            'smtp_connect_ok' => (int) env('ENGINE_SCORE_REASON_SMTP_CONNECT_OK', 95),
            'rcpt_ok' => (int) env('ENGINE_SCORE_REASON_RCPT_OK', 95),
            'syntax' => (int) env('ENGINE_SCORE_REASON_SYNTAX', 5),
            'mx_missing' => (int) env('ENGINE_SCORE_REASON_MX_MISSING', 10),
            'rcpt_rejected' => (int) env('ENGINE_SCORE_REASON_RCPT_REJECTED', 10),
            'smtp_unavailable' => (int) env('ENGINE_SCORE_REASON_SMTP_UNAVAILABLE', 10),
            'catch_all' => (int) env('ENGINE_SCORE_REASON_CATCH_ALL', 55),
            'disposable_domain' => (int) env('ENGINE_SCORE_REASON_DISPOSABLE', 40),
            'role_account' => (int) env('ENGINE_SCORE_REASON_ROLE_ACCOUNT', 65),
            'domain_typo_suspected' => (int) env('ENGINE_SCORE_REASON_DOMAIN_TYPO', 50),
            'smtp_timeout' => (int) env('ENGINE_SCORE_REASON_SMTP_TIMEOUT', 45),
            'smtp_connect_timeout' => (int) env('ENGINE_SCORE_REASON_SMTP_CONNECT_TIMEOUT', 45),
            'smtp_tempfail' => (int) env('ENGINE_SCORE_REASON_SMTP_TEMPFAIL', 40),
            'dns_timeout' => (int) env('ENGINE_SCORE_REASON_DNS_TIMEOUT', 45),
            'dns_servfail' => (int) env('ENGINE_SCORE_REASON_DNS_SERVFAIL', 40),
        ],
        'sub_status_caps' => [
            'catch_all' => (int) env('ENGINE_SCORE_CAP_CATCH_ALL', 80),
        ],
        'cache_adjustments' => [
            'valid' => (int) env('ENGINE_SCORE_CACHE_VALID', 10),
            'invalid' => (int) env('ENGINE_SCORE_CACHE_INVALID', -30),
            'risky' => (int) env('ENGINE_SCORE_CACHE_RISKY', -10),
        ],
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
