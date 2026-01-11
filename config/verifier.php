<?php

return [
    'brand_name' => env('VERIFIER_BRAND_NAME', 'Valid Email Verifier'),
    'storage_disk' => env('VERIFIER_STORAGE_DISK'),
    'require_active_subscription' => (bool) env('VERIFIER_REQUIRE_SUBSCRIPTION', false),
    'retention_days' => (int) env('VERIFIER_RETENTION_DAYS', 30),
    'api_rate_limit_per_minute' => (int) env('VERIFIER_API_RATE_PER_MINUTE', 120),
    'portal_upload_max_attempts' => (int) env('VERIFIER_PORTAL_UPLOAD_MAX_ATTEMPTS', 10),
    'portal_upload_decay_seconds' => (int) env('VERIFIER_PORTAL_UPLOAD_DECAY_SECONDS', 60),
    'engine_heartbeat_minutes' => (int) env('VERIFIER_ENGINE_HEARTBEAT_MINUTES', 5),
];
