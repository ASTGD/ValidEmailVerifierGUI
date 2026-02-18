<?php

$providers = [
    'log' => [
        'enabled' => (bool) env('SEED_SEND_PROVIDER_LOG_ENABLED', true),
        'webhook_secret' => (string) env('SEED_SEND_PROVIDER_LOG_WEBHOOK_SECRET', ''),
    ],
    'sendgrid' => [
        'enabled' => (bool) env('SEED_SEND_PROVIDER_SENDGRID_ENABLED', false),
        'webhook_secret' => (string) env('SEED_SEND_PROVIDER_SENDGRID_WEBHOOK_SECRET', ''),
        'webhook_public_key' => (string) env('SEED_SEND_PROVIDER_SENDGRID_WEBHOOK_PUBLIC_KEY', ''),
        'signature_header' => (string) env('SEED_SEND_PROVIDER_SENDGRID_SIGNATURE_HEADER', 'X-Twilio-Email-Event-Webhook-Signature'),
        'timestamp_header' => (string) env('SEED_SEND_PROVIDER_SENDGRID_TIMESTAMP_HEADER', 'X-Twilio-Email-Event-Webhook-Timestamp'),
        'api_key' => (string) env('SEED_SEND_PROVIDER_SENDGRID_API_KEY', ''),
        'api_endpoint' => (string) env('SEED_SEND_PROVIDER_SENDGRID_API_ENDPOINT', 'https://api.sendgrid.com/v3/mail/send'),
        'from_email' => (string) env('SEED_SEND_PROVIDER_SENDGRID_FROM_EMAIL', ''),
        'from_name' => (string) env('SEED_SEND_PROVIDER_SENDGRID_FROM_NAME', 'Verification Team'),
        'subject' => (string) env('SEED_SEND_PROVIDER_SENDGRID_SUBJECT', 'Mailbox verification seed message'),
        'text_body' => (string) env('SEED_SEND_PROVIDER_SENDGRID_TEXT_BODY', 'Mailbox verification test message. No action required.'),
        'timeout_seconds' => max(1, (int) env('SEED_SEND_PROVIDER_SENDGRID_TIMEOUT_SECONDS', 20)),
        'retry_times' => max(0, (int) env('SEED_SEND_PROVIDER_SENDGRID_RETRY_TIMES', 2)),
        'sandbox_mode' => (bool) env('SEED_SEND_PROVIDER_SENDGRID_SANDBOX_MODE', false),
    ],
];

return [
    'enabled' => (bool) env('SEED_SEND_ENABLED', false),
    'consent' => [
        'required' => (bool) env('SEED_SEND_CONSENT_REQUIRED', true),
        'text_version' => (string) env('SEED_SEND_CONSENT_TEXT_VERSION', 'v1'),
        'text' => (string) env('SEED_SEND_CONSENT_TEXT', 'I consent to SG6 seed-send verification for this completed job.'),
        'expiry_days' => max(1, (int) env('SEED_SEND_CONSENT_EXPIRY_DAYS', 30)),
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
        'timestamp_header' => (string) env('SEED_SEND_WEBHOOK_TIMESTAMP_HEADER', 'X-Seed-Timestamp'),
        'nonce_header' => (string) env('SEED_SEND_WEBHOOK_NONCE_HEADER', 'X-Seed-Nonce'),
        'signature_max_age_seconds' => max(1, (int) env('SEED_SEND_WEBHOOK_SIGNATURE_MAX_AGE_SECONDS', 300)),
        'replay_cache_prefix' => (string) env('SEED_SEND_WEBHOOK_REPLAY_CACHE_PREFIX', 'seed_send:webhook:nonce'),
        'rate_limit_per_minute' => max(1, (int) env('SEED_SEND_WEBHOOK_RATE_LIMIT_PER_MINUTE', 120)),
    ],
    'reports' => [
        'disk' => (string) env('SEED_SEND_REPORT_DISK', (string) env('FILESYSTEM_DISK', 'local')),
        'key_prefix' => (string) env('SEED_SEND_REPORT_PREFIX', 'results/seed-send'),
    ],
    'guardrails' => [
        'auto_pause_enabled' => (bool) env('SEED_SEND_AUTO_PAUSE_ENABLED', true),
        'min_sample_size' => max(1, (int) env('SEED_SEND_GUARDRAILS_MIN_SAMPLE_SIZE', 25)),
        'bounce_rate_pause_percent' => max(1, (int) env('SEED_SEND_GUARDRAILS_BOUNCE_RATE_PAUSE_PERCENT', 20)),
        'defer_rate_pause_percent' => max(1, (int) env('SEED_SEND_GUARDRAILS_DEFER_RATE_PAUSE_PERCENT', 40)),
    ],
    'health' => [
        'max_webhook_lag_seconds' => max(60, (int) env('SEED_SEND_HEALTH_MAX_WEBHOOK_LAG_SECONDS', 300)),
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
