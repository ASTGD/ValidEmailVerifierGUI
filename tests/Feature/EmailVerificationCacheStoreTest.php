<?php

namespace Tests\Feature;

use App\Models\EmailVerificationOutcome;
use App\Services\EmailVerificationCache\DatabaseEmailVerificationCacheStore;
use App\Support\EmailHashing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EmailVerificationCacheStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_cache_store_returns_latest_outcome(): void
    {
        $email = 'Cached@Example.com';
        $normalized = EmailHashing::normalizeEmail($email);

        EmailVerificationOutcome::create([
            'email_hash' => EmailHashing::hashEmail($normalized),
            'email_normalized' => $normalized,
            'outcome' => 'invalid',
            'reason_code' => 'bounce_hard',
            'observed_at' => Carbon::now()->subDays(2),
        ]);

        EmailVerificationOutcome::create([
            'email_hash' => EmailHashing::hashEmail($normalized),
            'email_normalized' => $normalized,
            'outcome' => 'valid',
            'reason_code' => 'delivered',
            'observed_at' => Carbon::now()->subDay(),
        ]);

        config(['engine.cache_freshness_days' => 30]);

        $store = new DatabaseEmailVerificationCacheStore();
        $hits = $store->lookupMany([$email]);

        $this->assertArrayHasKey($normalized, $hits);
        $this->assertSame('valid', $hits[$normalized]['outcome']);
        $this->assertSame('delivered', $hits[$normalized]['reason_code']);
    }

    public function test_database_cache_store_ignores_stale_outcomes(): void
    {
        $email = 'stale@example.com';
        $normalized = EmailHashing::normalizeEmail($email);

        EmailVerificationOutcome::create([
            'email_hash' => EmailHashing::hashEmail($normalized),
            'email_normalized' => $normalized,
            'outcome' => 'risky',
            'reason_code' => 'bounce_soft',
            'observed_at' => Carbon::now()->subDays(10),
        ]);

        config(['engine.cache_freshness_days' => 3]);

        $store = new DatabaseEmailVerificationCacheStore();
        $hits = $store->lookupMany([$email]);

        $this->assertSame([], $hits);
    }
}
