<?php

namespace App\Http\Controllers\Api\Monitor;

use App\Http\Requests\Monitor\MonitorCheckRequest;
use App\Models\EngineServer;
use App\Models\EngineServerBlacklistEvent;
use App\Models\EngineServerReputationCheck;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class MonitorChecksController
{
    public function __invoke(MonitorCheckRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $server = $this->resolveServer($payload);
        if (! $server) {
            return response()->json([
                'message' => 'Engine server not found.',
            ], 404);
        }

        $checkedAt = Carbon::parse($payload['checked_at']);
        $results = $payload['results'];
        $now = now();

        $insertRows = [];
        $listedCount = 0;

        DB::transaction(function () use ($results, $server, $checkedAt, $now, &$insertRows, &$listedCount): void {
            foreach ($results as $result) {
                $errorMessage = $result['error_message'] ?? null;
                $listed = (bool) ($result['listed'] ?? false);
                $status = $result['status'] ?? ($errorMessage ? 'error' : ($listed ? 'listed' : 'clear'));
                $response = $result['response'] ?? null;

                $insertRows[] = [
                    'engine_server_id' => $server->id,
                    'ip_address' => $server->ip_address,
                    'rbl' => $result['rbl'],
                    'status' => $status,
                    'response' => $response,
                    'error_message' => $errorMessage,
                    'checked_at' => $checkedAt,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if ($errorMessage) {
                    continue;
                }

                if ($listed) {
                    $listedCount++;
                    $event = EngineServerBlacklistEvent::firstOrNew([
                        'engine_server_id' => $server->id,
                        'rbl' => $result['rbl'],
                    ]);

                    if (! $event->exists) {
                        $event->first_seen = $checkedAt;
                        $event->listed_count = 0;
                        $event->severity = $event->severity ?? 'warning';
                    }

                    $event->status = 'active';
                    $event->last_seen = $checkedAt;
                    $event->last_response = $response;
                    $event->listed_count = (int) $event->listed_count + 1;
                    $event->save();
                } else {
                    $event = EngineServerBlacklistEvent::query()
                        ->where('engine_server_id', $server->id)
                        ->where('rbl', $result['rbl'])
                        ->where('status', 'active')
                        ->first();

                    if ($event) {
                        $event->status = 'resolved';
                        $event->save();
                    }
                }
            }

            if ($insertRows !== []) {
                EngineServerReputationCheck::query()->insert($insertRows);
            }
        });

        return response()->json([
            'data' => [
                'checks' => count($insertRows),
                'listed' => $listedCount,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveServer(array $payload): ?EngineServer
    {
        if (! empty($payload['server_id'])) {
            return EngineServer::query()->find($payload['server_id']);
        }

        if (! empty($payload['server_ip'])) {
            return EngineServer::query()
                ->where('ip_address', $payload['server_ip'])
                ->first();
        }

        return null;
    }
}
