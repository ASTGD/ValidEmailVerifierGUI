<?php

return [
    'lease_seconds' => (int) env('ENGINE_LEASE_SECONDS', env('VERIFIER_ENGINE_CLAIM_LEASE_SECONDS', 600)),
    'max_attempts' => (int) env('ENGINE_MAX_ATTEMPTS', 3),
    'chunk_size_default' => (int) env('ENGINE_CHUNK_SIZE', env('VERIFIER_CHUNK_SIZE', 5000)),
    'cache_batch_size' => (int) env('ENGINE_CACHE_BATCH_SIZE', env('VERIFIER_CACHE_BATCH_SIZE', 100)),
    'signed_url_expiry_seconds' => (int) env('ENGINE_SIGNED_URL_EXPIRY_SECONDS', env('VERIFIER_SIGNED_URL_EXPIRY_SECONDS', 300)),
    'chunk_inputs_prefix' => env('ENGINE_CHUNK_INPUT_PREFIX', 'chunks'),
    'chunk_outputs_prefix' => env('ENGINE_CHUNK_OUTPUT_PREFIX', 'results/chunks'),
];
