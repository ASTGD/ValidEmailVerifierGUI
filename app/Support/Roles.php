<?php

namespace App\Support;

final class Roles
{
    public const ADMIN = 'admin';
    public const CUSTOMER = 'customer';
    public const VERIFIER_SERVICE = 'verifier-service';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::ADMIN,
            self::CUSTOMER,
            self::VERIFIER_SERVICE,
        ];
    }
}
