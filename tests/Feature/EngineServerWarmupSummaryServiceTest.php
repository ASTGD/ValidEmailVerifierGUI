<?php

namespace Tests\Feature;

use App\Models\EngineServer;
use App\Models\EngineServerReputationSample;
use App\Models\EngineSetting;
use App\Services\EngineServerWarmupSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EngineServerWarmupSummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_counts_warming_and_critical_servers(): void
    {
        EngineSetting::query()->updateOrCreate([], [
            'reputation_window_hours' => 24,
            'reputation_min_samples' => 100,
            'reputation_tempfail_warn_rate' => 0.2,
            'reputation_tempfail_critical_rate' => 0.4,
        ]);

        $healthyServer = EngineServer::create([
            'name' => 'healthy',
            'ip_address' => '10.0.0.1',
        ]);

        $criticalServer = EngineServer::create([
            'name' => 'critical',
            'ip_address' => '10.0.0.2',
        ]);

        $warmingServer = EngineServer::create([
            'name' => 'warming',
            'ip_address' => '10.0.0.3',
        ]);

        EngineServerReputationSample::create([
            'engine_server_id' => $healthyServer->id,
            'verification_job_chunk_id' => (string) Str::uuid(),
            'total_count' => 200,
            'tempfail_count' => 10,
            'recorded_at' => now(),
        ]);

        EngineServerReputationSample::create([
            'engine_server_id' => $criticalServer->id,
            'verification_job_chunk_id' => (string) Str::uuid(),
            'total_count' => 200,
            'tempfail_count' => 120,
            'recorded_at' => now(),
        ]);

        EngineServerReputationSample::create([
            'engine_server_id' => $warmingServer->id,
            'verification_job_chunk_id' => (string) Str::uuid(),
            'total_count' => 50,
            'tempfail_count' => 5,
            'recorded_at' => now(),
        ]);

        $summary = app(EngineServerWarmupSummaryService::class)->summary();

        $this->assertSame(1, $summary['healthy']);
        $this->assertSame(1, $summary['critical']);
        $this->assertSame(1, $summary['warming']);
    }
}
