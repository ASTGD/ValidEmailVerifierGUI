<?php

namespace Tests\Feature;

use App\Enums\VerificationJobStatus;
use App\Jobs\FinalizeVerificationJob;
use App\Jobs\ImportEmailVerificationOutcomesFromCsv;
use App\Jobs\ParseAndChunkJob;
use App\Jobs\PrepareVerificationJob;
use App\Jobs\WriteBackVerificationCacheJob;
use App\Models\User;
use App\Models\VerificationJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class QueueSegmentationConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_horizon_config_includes_default_queue_coverage(): void
    {
        $defaultQueue = (string) config('queue.connections.redis.queue', env('REDIS_QUEUE', 'default'));
        $defaultWaitKey = sprintf('redis:%s', $defaultQueue);
        $smtpProbeQueue = (string) config('queue.connections.redis_smtp_probe.queue', env('QUEUE_SMTP_PROBE_NAME', 'smtp_probe'));
        $smtpProbeWaitKey = sprintf('redis_smtp_probe:%s', $smtpProbeQueue);
        $cacheWriteBackQueue = (string) config('queue.connections.redis_cache_writeback.queue', env('QUEUE_CACHE_WRITEBACK_NAME', 'cache_writeback'));
        $cacheWriteBackWaitKey = sprintf('redis_cache_writeback:%s', $cacheWriteBackQueue);
        $waits = config('horizon.waits', []);
        $defaults = config('horizon.defaults', []);
        $local = config('horizon.environments.local', []);
        $production = config('horizon.environments.production', []);

        $this->assertSame('redis', config('queue.connections.redis_smtp_probe.driver'));
        $this->assertArrayHasKey($defaultWaitKey, $waits);
        $this->assertArrayHasKey($smtpProbeWaitKey, $waits);
        $this->assertArrayHasKey($cacheWriteBackWaitKey, $waits);
        $this->assertArrayHasKey('supervisor-default', $defaults);
        $this->assertArrayHasKey('supervisor-smtp-probe', $defaults);
        $this->assertArrayHasKey('supervisor-cache-writeback', $defaults);
        $this->assertSame('redis', data_get($defaults, 'supervisor-default.connection'));
        $this->assertSame([$defaultQueue], data_get($defaults, 'supervisor-default.queue'));
        $this->assertSame('redis_smtp_probe', data_get($defaults, 'supervisor-smtp-probe.connection'));
        $this->assertSame([$smtpProbeQueue], data_get($defaults, 'supervisor-smtp-probe.queue'));
        $this->assertSame('redis_cache_writeback', data_get($defaults, 'supervisor-cache-writeback.connection'));
        $this->assertSame([$cacheWriteBackQueue], data_get($defaults, 'supervisor-cache-writeback.queue'));
        $this->assertArrayHasKey('supervisor-default', $local);
        $this->assertArrayHasKey('supervisor-default', $production);
        $this->assertArrayHasKey('supervisor-smtp-probe', $local);
        $this->assertArrayHasKey('supervisor-smtp-probe', $production);
        $this->assertArrayHasKey('supervisor-cache-writeback', $local);
        $this->assertArrayHasKey('supervisor-cache-writeback', $production);
    }

    public function test_jobs_define_expected_queue_lane_settings(): void
    {
        config([
            'queue.connections.redis_prepare.queue' => 'prepare-custom',
            'queue.connections.redis_parse.queue' => 'parse-custom',
            'queue.connections.redis_smtp_probe.queue' => 'smtp-probe-custom',
            'queue.connections.redis_finalize.queue' => 'finalize-custom',
            'queue.connections.redis_import.queue' => 'imports-custom',
            'queue.connections.redis_cache_writeback.queue' => 'cache-writeback-custom',
        ]);

        $prepare = new PrepareVerificationJob('job-1');
        $parse = new ParseAndChunkJob('job-1');
        $finalize = new FinalizeVerificationJob('job-1');
        $import = new ImportEmailVerificationOutcomesFromCsv(1);
        $writeBack = new WriteBackVerificationCacheJob('job-1');

        $this->assertSame('redis_prepare', $prepare->connection);
        $this->assertSame('prepare-custom', $prepare->queue);
        $this->assertSame(120, $prepare->timeout);
        $this->assertSame(3, $prepare->tries);
        $this->assertTrue($prepare->failOnTimeout);
        $this->assertSame([10, 30, 60], $prepare->backoff());

        $this->assertSame('redis_parse', $parse->connection);
        $this->assertSame('parse-custom', $parse->queue);
        $this->assertSame(1800, $parse->timeout);
        $this->assertSame(2, $parse->tries);
        $this->assertTrue($parse->failOnTimeout);
        $this->assertSame([60, 180], $parse->backoff());

        $this->assertSame('smtp-probe-custom', config('queue.connections.redis_smtp_probe.queue'));

        $this->assertSame('redis_finalize', $finalize->connection);
        $this->assertSame('finalize-custom', $finalize->queue);
        $this->assertSame(900, $finalize->timeout);
        $this->assertSame(3, $finalize->tries);
        $this->assertTrue($finalize->failOnTimeout);
        $this->assertSame([30, 120, 300], $finalize->backoff());

        $this->assertSame('redis_import', $import->connection);
        $this->assertSame('imports-custom', $import->queue);
        $this->assertSame(1200, $import->timeout);
        $this->assertSame(2, $import->tries);
        $this->assertTrue($import->failOnTimeout);
        $this->assertSame([120, 300], $import->backoff());

        $this->assertSame('redis_cache_writeback', $writeBack->connection);
        $this->assertSame('cache-writeback-custom', $writeBack->queue);
        $this->assertSame(1800, $writeBack->timeout);
        $this->assertSame(2, $writeBack->tries);
        $this->assertTrue($writeBack->failOnTimeout);
        $this->assertSame([120, 300], $writeBack->backoff());
    }

    public function test_prepare_job_dispatches_parse_job_on_parse_lane(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $job = VerificationJob::create([
            'user_id' => $user->id,
            'status' => VerificationJobStatus::Pending,
            'original_filename' => 'input.csv',
            'input_disk' => 'local',
            'input_key' => 'uploads/test/input.csv',
        ]);

        app()->call([new PrepareVerificationJob($job->id), 'handle']);

        Bus::assertDispatched(ParseAndChunkJob::class, function (ParseAndChunkJob $queuedJob): bool {
            return $queuedJob->connection === 'redis_parse'
                && $queuedJob->queue === (string) config('queue.connections.redis_parse.queue', 'parse');
        });
    }

    public function test_finalize_job_uses_without_overlapping_lock(): void
    {
        $job = new FinalizeVerificationJob('job-abc');
        $middlewares = $job->middleware();

        $this->assertCount(1, $middlewares);
        $this->assertInstanceOf(WithoutOverlapping::class, $middlewares[0]);

        /** @var WithoutOverlapping $middleware */
        $middleware = $middlewares[0];
        $this->assertSame('finalize:job-abc', $middleware->key);
        $this->assertGreaterThan($job->timeout, $middleware->expiresAfter);
        $this->assertSame(30, $middleware->releaseAfter);
    }

    public function test_import_dispatch_uses_import_lane(): void
    {
        Bus::fake();

        ImportEmailVerificationOutcomesFromCsv::dispatch(99);

        Bus::assertDispatched(ImportEmailVerificationOutcomesFromCsv::class, function (ImportEmailVerificationOutcomesFromCsv $job): bool {
            return $job->connection === 'redis_import'
                && $job->queue === (string) config('queue.connections.redis_import.queue', 'imports');
        });
    }

    public function test_writeback_job_uses_without_overlapping_lock(): void
    {
        $job = new WriteBackVerificationCacheJob('job-abc');
        $middlewares = $job->middleware();

        $this->assertCount(1, $middlewares);
        $this->assertInstanceOf(WithoutOverlapping::class, $middlewares[0]);

        /** @var WithoutOverlapping $middleware */
        $middleware = $middlewares[0];
        $this->assertSame('cache-writeback:job-abc', $middleware->key);
        $this->assertGreaterThan($job->timeout, $middleware->expiresAfter);
        $this->assertSame(60, $middleware->releaseAfter);
    }
}
