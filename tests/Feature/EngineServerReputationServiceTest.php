<?php

namespace Tests\Feature;

use App\Models\EngineServer;
use App\Models\EngineServerReputationSample;
use App\Services\EngineServerReputationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EngineServerReputationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reputation_summary_flags_critical_rate(): void
    {
        $server = EngineServer::create([
            'name' => 'engine-1',
            'ip_address' => '127.0.0.1',
        ]);

        EngineServerReputationSample::create([
            'engine_server_id' => $server->id,
            'verification_job_chunk_id' => (string) \Illuminate\Support\Str::uuid(),
            'total_count' => 200,
            'tempfail_count' => 120,
            'recorded_at' => now(),
        ]);

        $service = app(EngineServerReputationService::class);
        $summary = $service->summaryFor($server);

        $this->assertSame('critical', $summary['status']);
    }
}
