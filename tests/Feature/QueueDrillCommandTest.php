<?php

namespace Tests\Feature;

use App\Models\QueueIncident;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class QueueDrillCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_drill_creates_synthetic_incident_in_non_production(): void
    {
        config([
            'queue_health.alerts.enabled' => false,
        ]);

        $exitCode = Artisan::call('ops:queue-drill', [
            '--scenario' => 'critical_incident',
            '--json' => true,
        ]);

        $output = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame('critical_incident', $output['scenario']);
        $this->assertSame('critical', data_get($output, 'report.status'));

        $this->assertDatabaseHas('queue_incidents', [
            'issue_key' => 'drill:critical_incident',
            'status' => 'detected',
        ]);

        $this->assertSame(1, QueueIncident::query()->whereNull('resolved_at')->count());
    }
}
