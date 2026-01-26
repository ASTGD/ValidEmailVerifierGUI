<?php

namespace Tests\Feature\Monitor;

use App\Models\EngineServer;
use App\Models\EngineServerBlacklistEvent;
use App\Models\EngineServerReputationCheck;
use App\Models\EngineSetting;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MonitorApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_monitor_config_returns_settings(): void
    {
        $rblOne = strtolower(fake()->domainName());
        $rblTwo = strtolower(fake()->domainName());

        $resolverIp = fake()->ipv4();
        $resolverPort = fake()->numberBetween(1024, 65535);

        EngineSetting::query()->update([
            'monitor_enabled' => true,
            'monitor_interval_minutes' => 15,
            'monitor_rbl_list' => $rblOne.', '.$rblTwo,
            'monitor_dns_mode' => 'custom',
            'monitor_dns_server_ip' => $resolverIp,
            'monitor_dns_server_port' => $resolverPort,
        ]);

        $response = $this->withMonitorToken()
            ->getJson('/api/monitor/config');

        $response->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.interval_minutes', 15)
            ->assertJsonPath('data.rbl_list.0', $rblOne)
            ->assertJsonPath('data.rbl_list.1', $rblTwo)
            ->assertJsonPath('data.resolver_mode', 'custom')
            ->assertJsonPath('data.resolver_ip', $resolverIp)
            ->assertJsonPath('data.resolver_port', $resolverPort);
    }

    public function test_monitor_servers_returns_active_servers(): void
    {
        $activeServer = EngineServer::query()->create([
            'name' => fake()->word(),
            'ip_address' => fake()->ipv4(),
            'is_active' => true,
        ]);

        EngineServer::query()->create([
            'name' => fake()->word(),
            'ip_address' => fake()->ipv4(),
            'is_active' => false,
        ]);

        $response = $this->withMonitorToken()
            ->getJson('/api/monitor/servers');

        $response->assertOk()
            ->assertJsonCount(1, 'data.servers')
            ->assertJsonPath('data.servers.0.id', $activeServer->id);
    }

    public function test_monitor_checks_creates_check_and_blacklist_event(): void
    {
        $server = EngineServer::query()->create([
            'name' => fake()->word(),
            'ip_address' => fake()->ipv4(),
            'is_active' => true,
        ]);

        $rbl = strtolower(fake()->domainName());

        $payload = [
            'server_id' => $server->id,
            'server_ip' => $server->ip_address,
            'checked_at' => now()->toISOString(),
            'results' => [
                [
                    'rbl' => $rbl,
                    'listed' => true,
                    'response' => 'listed',
                ],
            ],
        ];

        $response = $this->withMonitorToken()
            ->postJson('/api/monitor/checks', $payload);

        $response->assertOk();

        $this->assertDatabaseCount(EngineServerReputationCheck::class, 1);
        $event = EngineServerBlacklistEvent::query()->first();
        $this->assertNotNull($event);
        $this->assertSame('active', $event->status);

        $payload['results'][0]['listed'] = false;

        $response = $this->withMonitorToken()
            ->postJson('/api/monitor/checks', $payload);

        $response->assertOk();

        $event->refresh();
        $this->assertSame('resolved', $event->status);
    }

    private function withMonitorToken(): self
    {
        $role = Role::firstOrCreate(['name' => Roles::VERIFIER_SERVICE, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        $token = $user->createToken('monitor')->plainTextToken;

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
