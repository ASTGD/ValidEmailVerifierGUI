<?php

namespace App\Services\EmailVerificationCache;

use App\Contracts\EmailVerificationCacheStore;

class NullCacheStore implements EmailVerificationCacheStore
{
    public function lookupMany(array $emails): array
    {
        return [];
    }
}
