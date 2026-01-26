<?php

namespace App\Http\Controllers\Api\Monitor;

use App\Models\EngineServer;
use Illuminate\Http\JsonResponse;

class MonitorServersController
{
    public function __invoke(): JsonResponse
    {
        $servers = EngineServer::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(static fn (EngineServer $server): array => [
                'id' => $server->id,
                'name' => $server->name,
                'ip_address' => $server->ip_address,
                'environment' => $server->environment,
                'region' => $server->region,
                'is_active' => $server->is_active,
                'drain_mode' => $server->drain_mode,
                'last_heartbeat_at' => $server->last_heartbeat_at?->toISOString(),
            ])
            ->values();

        return response()->json([
            'data' => [
                'servers' => $servers,
            ],
        ]);
    }
}
