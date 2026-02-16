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

$cacheWritebackStatuses = env('ENGINE_CACHE_WRITEBACK_STATUSES', 'valid,invalid');
$cacheWritebackStatuses = is_string($cacheWritebackStatuses)
    ? array_values(array_filter(array_map('trim', explode(',', $cacheWritebackStatuses))))
    : (is_array($cacheWritebackStatuses) ? $cacheWritebackStatuses : ['valid', 'invalid']);

return [
    'lease_seconds' => (int) env('ENGINE_LEASE_SECONDS', env('VERIFIER_ENGINE_CLAIM_LEASE_SECONDS', 600)),
    'max_attempts' => (int) env('ENGINE_MAX_ATTEMPTS', 3),
    'chunk_size_default' => (int) env('ENGINE_CHUNK_SIZE', env('VERIFIER_CHUNK_SIZE', 5000)),
    'max_emails_per_upload' => (int) env('ENGINE_MAX_EMAILS_PER_UPLOAD', env('VERIFIER_MAX_EMAILS_PER_UPLOAD', 100000)),
    'cache_batch_size' => (int) env('ENGINE_CACHE_BATCH_SIZE', env('VERIFIER_CACHE_BATCH_SIZE', 100)),
    'cache_freshness_days' => (int) env('ENGINE_CACHE_FRESHNESS_DAYS', env('VERIFIER_CACHE_FRESHNESS_DAYS', 30)),
    'cache_store_driver' => env('ENGINE_CACHE_STORE_DRIVER'),
    'cache_only_mode_enabled' => (bool) env('ENGINE_CACHE_ONLY_MODE_ENABLED', false),
    'cache_only_miss_status' => env('ENGINE_CACHE_ONLY_MISS_STATUS', 'risky'),
    'cache_capacity_mode' => env('ENGINE_CACHE_CAPACITY_MODE', 'on_demand'),
    'cache_consistent_read' => (bool) env('ENGINE_CACHE_CONSISTENT_READ', false),
    'cache_ondemand_max_batches_per_second' => env('ENGINE_CACHE_ONDEMAND_MAX_BATCHES_PER_SECOND') !== null
        ? (int) env('ENGINE_CACHE_ONDEMAND_MAX_BATCHES_PER_SECOND')
        : null,
    'cache_ondemand_sleep_ms_between_batches' => (int) env('ENGINE_CACHE_ONDEMAND_SLEEP_MS_BETWEEN_BATCHES', 0),
    'cache_provisioned_max_batches_per_second' => (int) env('ENGINE_CACHE_PROVISIONED_MAX_BATCHES_PER_SECOND', 5),
    'cache_provisioned_sleep_ms_between_batches' => (int) env('ENGINE_CACHE_PROVISIONED_SLEEP_MS_BETWEEN_BATCHES', 100),
    'cache_provisioned_max_retries' => (int) env('ENGINE_CACHE_PROVISIONED_MAX_RETRIES', 5),
    'cache_provisioned_backoff_base_ms' => (int) env('ENGINE_CACHE_PROVISIONED_BACKOFF_BASE_MS', 200),
    'cache_provisioned_backoff_max_ms' => (int) env('ENGINE_CACHE_PROVISIONED_BACKOFF_MAX_MS', 2000),
    'cache_provisioned_jitter_enabled' => (bool) env('ENGINE_CACHE_PROVISIONED_JITTER_ENABLED', true),
    'cache_failure_mode' => env('ENGINE_CACHE_FAILURE_MODE', 'fail_job'),
    'cache_writeback_enabled' => (bool) env('ENGINE_CACHE_WRITEBACK_ENABLED', false),
    'cache_writeback_statuses' => $cacheWritebackStatuses,
    'cache_writeback_batch_size' => (int) env('ENGINE_CACHE_WRITEBACK_BATCH_SIZE', 25),
    'cache_writeback_max_writes_per_second' => env('ENGINE_CACHE_WRITEBACK_MAX_WRITES_PER_SECOND') !== null
        ? (int) env('ENGINE_CACHE_WRITEBACK_MAX_WRITES_PER_SECOND')
        : null,
    'cache_writeback_retry_attempts' => (int) env('ENGINE_CACHE_WRITEBACK_RETRY_ATTEMPTS', 5),
    'cache_writeback_backoff_base_ms' => (int) env('ENGINE_CACHE_WRITEBACK_BACKOFF_BASE_MS', 200),
    'cache_writeback_backoff_max_ms' => (int) env('ENGINE_CACHE_WRITEBACK_BACKOFF_MAX_MS', 2000),
    'cache_writeback_failure_mode' => env('ENGINE_CACHE_WRITEBACK_FAILURE_MODE', 'fail_job'),
    'cache_writeback_test_mode_enabled' => (bool) env('ENGINE_CACHE_WRITEBACK_TEST_MODE_ENABLED', false),
    'cache_writeback_test_table' => env('ENGINE_CACHE_WRITEBACK_TEST_TABLE'),
    'cache_writeback_test_result' => env('ENGINE_CACHE_WRITEBACK_TEST_RESULT', 'Cache_miss'),
    'cache_dynamodb' => [
        'table' => env('ENGINE_CACHE_DYNAMODB_TABLE'),
        'key_attribute' => env('ENGINE_CACHE_DYNAMODB_KEY_ATTRIBUTE', 'email'),
        'result_attribute' => env('ENGINE_CACHE_DYNAMODB_RESULT_ATTRIBUTE', 'result'),
        'datetime_attribute' => env('ENGINE_CACHE_DYNAMODB_DATETIME_ATTRIBUTE', 'DateTime'),
        'region' => env('ENGINE_CACHE_DYNAMODB_REGION', env('AWS_DEFAULT_REGION')),
        'endpoint' => env('ENGINE_CACHE_DYNAMODB_ENDPOINT'),
    ],
    'dedupe_in_memory_limit' => (int) env('ENGINE_DEDUPE_IN_MEMORY_LIMIT', env('VERIFIER_DEDUPE_IN_MEMORY_LIMIT', 100000)),
    'xlsx_row_batch_size' => (int) env('ENGINE_XLSX_ROW_BATCH_SIZE', env('VERIFIER_XLSX_ROW_BATCH_SIZE', 1000)),
    'signed_url_expiry_seconds' => (int) env('ENGINE_SIGNED_URL_EXPIRY_SECONDS', env('VERIFIER_SIGNED_URL_EXPIRY_SECONDS', 300)),
    'single_check_rate_limit_standard' => (int) env('ENGINE_SINGLE_CHECK_RATE_STANDARD_PER_MINUTE', 30),
    'single_check_rate_limit_enhanced' => (int) env('ENGINE_SINGLE_CHECK_RATE_ENHANCED_PER_MINUTE', 10),
    'single_check_rate_limit' => (int) env(
        'ENGINE_SINGLE_CHECK_RATE_PER_MINUTE',
        (int) env('ENGINE_SINGLE_CHECK_RATE_ENHANCED_PER_MINUTE', 10)
    ),
    'single_check_rate_limit_decay_seconds' => (int) env('ENGINE_SINGLE_CHECK_RATE_DECAY_SECONDS', 60),
    'chunk_inputs_prefix' => env('ENGINE_CHUNK_INPUT_PREFIX', 'chunks'),
    'chunk_outputs_prefix' => env('ENGINE_CHUNK_OUTPUT_PREFIX', 'results/chunks'),
    'result_prefix' => env('ENGINE_RESULT_PREFIX', 'results/jobs'),
    'finalization_temp_disk' => env('ENGINE_FINALIZATION_TEMP_DISK', 'local'),
    'finalization_write_mode' => env('ENGINE_FINALIZATION_WRITE_MODE', 'stream_to_temp_then_upload'),
    'health_window_days' => (int) env('ENGINE_HEALTH_WINDOW_DAYS', 7),
    'engine_paused' => (bool) env('ENGINE_PAUSED', false),
    'enhanced_mode_enabled' => env('ENGINE_ENHANCED_MODE_ENABLED', false),
    'enhanced_requires_entitlement' => (bool) env('ENGINE_ENHANCED_REQUIRES_ENTITLEMENT', false),
    'role_accounts_behavior' => env('ENGINE_ROLE_ACCOUNTS_BEHAVIOR', 'risky'),
    'role_accounts_list' => env('ENGINE_ROLE_ACCOUNTS_LIST', 'info,admin,support,sales,contact,hello,hr'),
    'catch_all_policy' => env('ENGINE_CATCH_ALL_POLICY', 'risky_only'),
    'catch_all_promote_threshold' => env('ENGINE_CATCH_ALL_PROMOTE_THRESHOLD') !== null
        ? (int) env('ENGINE_CATCH_ALL_PROMOTE_THRESHOLD')
        : null,
    'tempfail_retry_enabled' => (bool) env('ENGINE_TEMPFAIL_RETRY_ENABLED', false),
    'tempfail_retry_max_attempts' => (int) env('ENGINE_TEMPFAIL_RETRY_MAX_ATTEMPTS', 2),
    'tempfail_retry_backoff_minutes' => env('ENGINE_TEMPFAIL_RETRY_BACKOFF_MINUTES', '10,30,60'),
    'tempfail_retry_reasons' => env(
        'ENGINE_TEMPFAIL_RETRY_REASONS',
        'smtp_tempfail,smtp_timeout,smtp_connect_timeout,dns_timeout,dns_servfail'
    ),
    'screening_hard_invalid_reasons' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ENGINE_SCREENING_HARD_INVALID_REASONS', 'syntax,mx_missing'))
    ))),
    'advanced_smtp_probing_enabled' => (bool) env('ADVANCED_SMTP_PROBING_ENABLED', false),
    'probe_sharding_enabled' => (bool) env('PROBE_SHARDING_ENABLED', true),
    'probe_shard_target_size' => (int) env('PROBE_SHARD_TARGET_SIZE', 1000),
    'probe_shard_min_size' => (int) env('PROBE_SHARD_MIN_SIZE', 200),
    'probe_shard_max_size' => (int) env('PROBE_SHARD_MAX_SIZE', 2000),
    'probe_max_attempts' => (int) env('PROBE_MAX_ATTEMPTS', 3),
    'probe_max_mx_hosts' => (int) env('PROBE_MAX_MX_HOSTS', 2),
    'probe_routing_enabled' => (bool) env('PROBE_ROUTING_ENABLED', true),
    'probe_rotation_retry_enabled' => (bool) env('PROBE_ROTATION_RETRY_ENABLED', true),
    'probe_autoprotect_enabled' => (bool) env('PROBE_AUTOPROTECT_ENABLED', false),
    'smtp_ai_suggestion_enabled' => (bool) env('SMTP_AI_SUGGESTION_ENABLED', false),
    'smtp_ai_unknown_rate_threshold' => (float) env('SMTP_AI_UNKNOWN_RATE_THRESHOLD', 0.20),
    'smtp_ai_min_samples' => (int) env('SMTP_AI_MIN_SAMPLES', 500),
    'smtp_ai_min_truth_samples' => (int) env('SMTP_AI_MIN_TRUTH_SAMPLES', 50),
    'smtp_ai_precision_floor' => (float) env('SMTP_AI_PRECISION_FLOOR', 0.85),
    'shadow_sync_alerts_enabled' => (bool) env('ENGINE_SHADOW_SYNC_ALERTS_ENABLED', true),
    'shadow_sync_alert_cooldown_seconds' => (int) env('ENGINE_SHADOW_SYNC_ALERT_COOLDOWN_SECONDS', 1800),
    'shadow_sync_alert_cache_prefix' => env('ENGINE_SHADOW_SYNC_ALERT_CACHE_PREFIX', 'engine:shadow-sync:alerts'),
    'shadow_sync_alert_email' => env('ENGINE_SHADOW_SYNC_ALERT_EMAIL', env('QUEUE_HEALTH_ALERT_EMAIL')),
    'shadow_sync_alert_slack_webhook_url' => env('ENGINE_SHADOW_SYNC_ALERT_SLACK_WEBHOOK_URL', env('QUEUE_HEALTH_SLACK_WEBHOOK_URL')),
    'smtp_decision_trace_retention_days' => (int) env('ENGINE_SMTP_DECISION_TRACE_RETENTION_DAYS', 30),
    'smtp_policy_shadow_run_retention_days' => (int) env('ENGINE_SMTP_POLICY_SHADOW_RUN_RETENTION_DAYS', 60),
    'probe_preferred_pools' => env('PROBE_PREFERRED_POOLS', ''),
    'probe_provider_preferred_pools' => env('PROBE_PROVIDER_PREFERRED_POOLS', ''),
    'probe_routing_weights' => [
        'affinity' => (int) env('PROBE_ROUTING_WEIGHT_AFFINITY', 40),
        'anti_affinity' => (int) env('PROBE_ROUTING_WEIGHT_ANTI_AFFINITY', 60),
        'preferred_pool' => (int) env('PROBE_ROUTING_WEIGHT_PREFERRED_POOL', 30),
        'retry_bonus' => (int) env('PROBE_ROUTING_WEIGHT_RETRY_BONUS', 10),
    ],
    'reputation_window_hours' => (int) env('ENGINE_REPUTATION_WINDOW_HOURS', 24),
    'reputation_min_samples' => (int) env('ENGINE_REPUTATION_MIN_SAMPLES', 100),
    'reputation_tempfail_warn_rate' => (float) env('ENGINE_REPUTATION_TEMPFAIL_WARN_RATE', 0.2),
    'reputation_tempfail_critical_rate' => (float) env('ENGINE_REPUTATION_TEMPFAIL_CRITICAL_RATE', 0.4),
    'show_single_checks_in_admin' => (bool) env('ENGINE_SHOW_SINGLE_CHECKS_IN_ADMIN', false),
    'monitor_enabled' => (bool) env('ENGINE_MONITOR_ENABLED', false),
    'monitor_interval_minutes' => (int) env('ENGINE_MONITOR_INTERVAL_MINUTES', 60),
    'monitor_rbl_list' => env('ENGINE_MONITOR_RBL_LIST', ''),
    'monitor_dns_mode' => env('ENGINE_MONITOR_DNS_MODE', 'system'),
    'monitor_dns_server_ip' => env('ENGINE_MONITOR_DNS_SERVER_IP'),
    'monitor_dns_server_port' => env('ENGINE_MONITOR_DNS_SERVER_PORT') !== null
        ? (int) env('ENGINE_MONITOR_DNS_SERVER_PORT')
        : 53,
    'metrics_source' => env('ENGINE_METRICS_SOURCE', 'container'),
    'metrics_sample_interval_seconds' => (int) env('ENGINE_METRICS_SAMPLE_INTERVAL_SECONDS', 60),
    'provider_policies' => [],
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
