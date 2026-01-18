<?php

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
    'enhanced_mode_enabled' => env('ENGINE_ENHANCED_MODE_ENABLED', false),
    'feedback_imports_prefix' => env('ENGINE_FEEDBACK_IMPORTS_PREFIX', 'feedback/imports'),
];
