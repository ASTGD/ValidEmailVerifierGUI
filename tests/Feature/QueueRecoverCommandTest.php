<?php

namespace Tests\Feature;

use App\Models\QueueRecoveryAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class QueueRecoverCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_recover_dry_run_respects_lane_and_max_caps(): void
    {
        config([
            'queue_recovery.enabled' => true,
            'queue_recovery.allow_strategies' => ['requeue_failed'],
            'queue_recovery.allow_lanes' => ['default', 'parse'],
            'queue_recovery.max_replay_per_run' => 5,
        ]);

        $this->seedFailedJob('database', 'parse', 'App\\Jobs\\ParseAndChunkJob');
        $this->seedFailedJob('database', 'parse', 'App\\Jobs\\ParseAndChunkJob');
        $this->seedFailedJob('database', 'default', 'App\\Jobs\\PrepareVerificationJob');

        $exitCode = Artisan::call('ops:queue-recover', [
            '--strategy' => 'requeue_failed',
            '--lane' => 'parse',
            '--max' => 1,
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('failed_jobs', 3);

        $action = QueueRecoveryAction::query()->latest('id')->first();
        $this->assertNotNull($action);
        $this->assertSame('dry_run', $action->status);
        $this->assertSame('parse', $action->lane);
        $this->assertSame(1, $action->target_count);
    }

    public function test_queue_recover_lane_uses_configured_queue_mapping(): void
    {
        config([
            'queue_recovery.enabled' => true,
            'queue_recovery.allow_strategies' => ['requeue_failed'],
            'queue_recovery.allow_lanes' => ['default', 'parse'],
            'queue_recovery.max_replay_per_run' => 5,
            'queue_health.lanes.parse.queue' => 'mx_parse_custom',
        ]);

        $this->seedFailedJob('database', 'mx_parse_custom', 'App\\Jobs\\ParseAndChunkJob');

        $exitCode = Artisan::call('ops:queue-recover', [
            '--strategy' => 'requeue_failed',
            '--lane' => 'parse',
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $exitCode);

        $action = QueueRecoveryAction::query()->latest('id')->first();
        $this->assertNotNull($action);
        $this->assertSame('parse', $action->lane);
        $this->assertSame(1, $action->target_count);
        $this->assertSame('mx_parse_custom', data_get($action->meta, 'lane_queue'));
    }

    public function test_queue_recover_fails_when_lane_mapping_is_missing(): void
    {
        config([
            'queue_recovery.enabled' => true,
            'queue_recovery.allow_strategies' => ['requeue_failed'],
            'queue_recovery.allow_lanes' => ['parse'],
            'queue_health.lanes.parse.queue' => '',
        ]);

        $exitCode = Artisan::call('ops:queue-recover', [
            '--strategy' => 'requeue_failed',
            '--lane' => 'parse',
            '--dry-run' => true,
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('has no queue mapping', Artisan::output());
    }

    public function test_queue_recover_requeues_and_deletes_failed_job_within_window(): void
    {
        config([
            'queue_recovery.enabled' => true,
            'queue_recovery.allow_strategies' => ['requeue_failed'],
            'queue_recovery.allow_lanes' => ['default', 'parse'],
            'queue_recovery.max_replay_per_run' => 10,
            'queue.default' => 'database',
            'queue.connections.database.driver' => 'database',
            'queue.connections.database.table' => 'jobs',
            'queue.connections.database.queue' => 'default',
        ]);

        $this->seedFailedJob('database', 'parse', 'App\\Jobs\\ParseAndChunkJob', now()->subHour());

        $exitCode = Artisan::call('ops:queue-recover', [
            '--strategy' => 'requeue_failed',
            '--lane' => 'parse',
            '--hours' => 2,
            '--max' => 10,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('failed_jobs', 0);

        $action = QueueRecoveryAction::query()->latest('id')->first();
        $this->assertNotNull($action);
        $this->assertSame('success', $action->status);
        $this->assertSame(1, $action->target_count);
        $this->assertSame(1, $action->processed_count);
        $this->assertSame(0, $action->failed_count);

        $this->assertGreaterThan(0, DB::table('jobs')->count());
    }

    private function seedFailedJob(string $connection, string $queue, string $displayName, $failedAt = null): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => $connection,
            'queue' => $queue,
            'payload' => json_encode([
                'uuid' => (string) Str::uuid(),
                'displayName' => $displayName,
                'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
                'data' => [
                    'commandName' => $displayName,
                    'command' => 'serialized',
                ],
            ], JSON_THROW_ON_ERROR),
            'exception' => 'synthetic failure',
            'failed_at' => $failedAt ?: now(),
        ]);
    }
}
