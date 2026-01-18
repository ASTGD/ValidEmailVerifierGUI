<?php

namespace App\Support;

class EmailHashing
{
    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    public static function hashEmail(string $email): string
    {
        return hash('sha256', $email);
    }
}
