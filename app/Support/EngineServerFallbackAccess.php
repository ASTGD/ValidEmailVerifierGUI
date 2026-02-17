<?php

namespace App\Support;

use App\Models\User;

final class EngineServerFallbackAccess
{
    public static function enabled(): bool
    {
        return (bool) config('engine_servers.fallback_ui_enabled', false);
    }

    public static function superAdminOnly(): bool
    {
        return (bool) config('engine_servers.fallback_ui_super_admin_only', true);
    }

    public static function userCanAccess(?User $user): bool
    {
        if (! self::enabled()) {
            return false;
        }

        if (! $user || ! method_exists($user, 'hasRole') || ! $user->hasRole(Roles::ADMIN)) {
            return false;
        }

        if (! self::superAdminOnly()) {
            return true;
        }

        $email = strtolower(trim((string) $user->email));
        if ($email === '') {
            return false;
        }

        $allowlist = array_values(array_filter(array_map(
            static fn ($value): string => strtolower(trim((string) $value)),
            (array) config('engine_servers.fallback_ui_super_admin_emails', [])
        )));

        return in_array($email, $allowlist, true);
    }
}
