<?php

namespace App\Services\EmailVerificationCache;

use App\Contracts\EmailVerificationCacheStore;
use App\Models\EmailVerificationOutcome;
use App\Support\EmailHashing;
use Illuminate\Support\Carbon;

class DatabaseEmailVerificationCacheStore implements EmailVerificationCacheStore
{
    public function lookupMany(array $emails): array
    {
        $hashes = [];
        $normalizedByHash = [];
        $normalizedEmails = [];

        foreach ($emails as $email) {
            $normalized = EmailHashing::normalizeEmail((string) $email);
            if ($normalized === '') {
                continue;
            }

            $hash = EmailHashing::hashEmail($normalized);
            $hashes[$hash] = true;
            $normalizedByHash[$hash] = $normalized;
            $normalizedEmails[$normalized] = $hash;
        }

        if ($hashes === []) {
            return [];
        }

        $freshnessDays = (int) config('engine.cache_freshness_days', config('verifier.cache_freshness_days', 30));
        if ($freshnessDays <= 0) {
            return [];
        }

        $cutoff = Carbon::now()->subDays($freshnessDays);

        $rows = EmailVerificationOutcome::query()
            ->whereIn('email_hash', array_keys($hashes))
            ->where('observed_at', '>=', $cutoff)
            ->orderBy('email_hash')
            ->orderByDesc('observed_at')
            ->get(['email_hash', 'outcome', 'reason_code', 'observed_at']);

        $latest = [];
        foreach ($rows as $row) {
            if (! isset($latest[$row->email_hash])) {
                $latest[$row->email_hash] = $row;
            }
        }

        $results = [];
        foreach ($normalizedEmails as $normalized => $hash) {
            if (! isset($latest[$hash])) {
                continue;
            }

            $row = $latest[$hash];
            $results[$normalized] = [
                'outcome' => $row->outcome,
                'status' => $row->outcome,
                'reason_code' => $row->reason_code,
                'observed_at' => $row->observed_at?->toISOString(),
            ];
        }

        return $results;
    }
}
