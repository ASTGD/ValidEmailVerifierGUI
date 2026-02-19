<?php

$superAdminEmails = array_values(array_filter(array_map(
    static fn ($value): string => strtolower(trim((string) $value)),
    explode(',', (string) env('ENGINE_SERVERS_FALLBACK_UI_SUPER_ADMIN_EMAILS', ''))
)));

return [
    'fallback_ui_enabled' => (bool) env('ENGINE_SERVERS_FALLBACK_UI_ENABLED', false),
    'fallback_ui_super_admin_only' => (bool) env('ENGINE_SERVERS_FALLBACK_UI_SUPER_ADMIN_ONLY', true),
    'fallback_ui_super_admin_emails' => $superAdminEmails,
    'process_control' => [
        'agent_token' => env('ENGINE_SERVER_AGENT_TOKEN'),
        'agent_hmac_secret' => env('ENGINE_SERVER_AGENT_HMAC_SECRET'),
        'signature_ttl_seconds' => (int) env('ENGINE_SERVER_AGENT_SIGNATURE_TTL_SECONDS', 60),
        'default_timeout_seconds' => (int) env('ENGINE_SERVER_AGENT_TIMEOUT_SECONDS', 8),
    ],
];
