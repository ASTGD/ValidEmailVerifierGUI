<?php

$superAdminEmails = array_values(array_filter(array_map(
    static fn ($value): string => strtolower(trim((string) $value)),
    explode(',', (string) env('ENGINE_SERVERS_FALLBACK_UI_SUPER_ADMIN_EMAILS', ''))
)));

return [
    'fallback_ui_enabled' => (bool) env('ENGINE_SERVERS_FALLBACK_UI_ENABLED', false),
    'fallback_ui_super_admin_only' => (bool) env('ENGINE_SERVERS_FALLBACK_UI_SUPER_ADMIN_ONLY', true),
    'fallback_ui_super_admin_emails' => $superAdminEmails,
];
