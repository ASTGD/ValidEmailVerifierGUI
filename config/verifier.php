<?php

return [
    'storage_disk' => env('VERIFIER_STORAGE_DISK'),
    'require_active_subscription' => (bool) env('VERIFIER_REQUIRE_SUBSCRIPTION', false),
    'retention_days' => (int) env('VERIFIER_RETENTION_DAYS', 30),
];
