<?php

namespace App\Contracts;

interface EmailVerificationCacheStore
{
    /**
     * @param  array<int, string>  $emails
     * @return array<string, array<string, mixed>|bool>
     */
    public function lookupMany(array $emails): array;
}
