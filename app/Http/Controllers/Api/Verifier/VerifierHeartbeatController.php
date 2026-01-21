<?php

namespace App\Http\Controllers\Api\Verifier;

use App\Http\Requests\Verifier\EngineHeartbeatRequest;
use App\Models\EngineServer;
use App\Support\AdminAuditLogger;
use Illuminate\Http\JsonResponse;

class VerifierHeartbeatController
{
    public function __invoke(EngineHeartbeatRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $serverData = $payload['server'];

        $update = [
            'name' => $serverData['name'],
            'last_heartbeat_at' => now(),
        ];

        if (array_key_exists('environment', $serverData)) {
            $update['environment'] = $serverData['environment'];
        }

        if (array_key_exists('region', $serverData)) {
            $update['region'] = $serverData['region'];
        }

        $server = EngineServer::query()->updateOrCreate(
            ['ip_address' => $serverData['ip_address']],
            $update
        );

        AdminAuditLogger::log('engine_heartbeat', $server, [
            'ip_address' => $server->ip_address,
            'name' => $server->name,
            'environment' => $server->environment,
            'region' => $server->region,
        ]);

        $thresholdMinutes = max(1, (int) config('verifier.engine_heartbeat_minutes', 5));

        $server->loadMissing('verifierDomain');
        $identityDomain = $server->verifierDomain?->domain ?? $server->identity_domain;

        return response()->json([
            'data' => [
                'server_id' => $server->id,
                'status' => $server->isOnline() ? 'online' : 'offline',
                'heartbeat_threshold_minutes' => $thresholdMinutes,
                'identity' => [
                    'helo_name' => $server->helo_name,
                    'mail_from_address' => $server->mail_from_address,
                    'identity_domain' => $identityDomain,
                ],
            ],
        ]);
    }
}
