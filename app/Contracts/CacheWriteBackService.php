<?php

namespace App\Contracts;

use App\Models\VerificationJob;

interface CacheWriteBackService
{
    /**
     * @param  array{disk: string, keys: array<string, string>}  $result
     * @return array{status: string, attempted: int, written: int}
     */
    public function writeBack(VerificationJob $job, array $result): array;
}
