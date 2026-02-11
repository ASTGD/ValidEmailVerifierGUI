<?php

$providers = [
    'log' => [
        'enabled' => (bool) env('SEED_SEND_PROVIDER_LOG_ENABLED', true),
        'webhook_secret' => (string) env('SEED_SEND_PROVIDER_LOG_WEBHOOK_SECRET', ''),
    ],
];

return [
    'enabled' => (bool) env('SEED_SEND_ENABLED', false),
    'consent' => [
        'required' => (bool) env('SEED_SEND_CONSENT_REQUIRED', true),
        'text_version' => (string) env('SEED_SEND_CONSENT_TEXT_VERSION', 'v1'),
    ],
    'credits' => [
        'per_recipient' => max(1, (int) env('SEED_SEND_CREDITS_PER_RECIPIENT', 1)),
        'enforce' => (bool) env('SEED_SEND_CREDITS_ENFORCE', false),
    ],
    'target_scope' => [
        'default' => (string) env('SEED_SEND_TARGET_SCOPE', 'full_list'),
        'allowed' => ['full_list'],
    ],
    'provider' => [
        'default' => (string) env('SEED_SEND_PROVIDER', 'log'),
        'providers' => $providers,
    ],
    'webhooks' => [
        'required' => (bool) env('SEED_SEND_WEBHOOK_REQUIRED', true),
        'signature_header' => (string) env('SEED_SEND_WEBHOOK_SIGNATURE_HEADER', 'X-Seed-Signature'),
    ],
    'dispatch' => [
        'batch_size' => max(1, (int) env('SEED_SEND_DISPATCH_BATCH_SIZE', 25)),
        'delay_seconds' => max(1, (int) env('SEED_SEND_DISPATCH_DELAY_SECONDS', 5)),
    ],
    'reconcile' => [
        'delay_minutes' => max(1, (int) env('SEED_SEND_RECONCILE_DELAY_MINUTES', 30)),
        'max_pending_age_minutes' => max(1, (int) env('SEED_SEND_RECONCILE_MAX_PENDING_AGE_MINUTES', 120)),
    ],
    'recipient_limits' => [
        'max_per_campaign' => max(1, (int) env('SEED_SEND_MAX_RECIPIENTS_PER_CAMPAIGN', 100000)),
    ],
];
