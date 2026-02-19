<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\EngineServerCommandRequest;
use App\Models\EngineServer;
use App\Models\EngineServerCommand;
use App\Services\EngineServerProcessControlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EngineServerCommandController extends Controller
{
    public function store(
        EngineServerCommandRequest $request,
        EngineServer $engineServer,
        EngineServerProcessControlService $service
    ): JsonResponse {
        $requestId = $this->requestId($request);
        $payload = $request->validated();

        $command = $service->createAndExecute(
            $engineServer,
            (string) $payload['action'],
            $payload['reason'] ?? null,
            $payload['idempotency_key'] ?? null,
            $requestId,
            $this->triggeredBy($request),
            null
        );

        return response()->json([
            'data' => $this->serializeCommand($command),
        ], 200, [
            'X-Request-Id' => $requestId,
        ]);
    }

    public function show(Request $request, EngineServer $engineServer, EngineServerCommand $engineServerCommand): JsonResponse
    {
        $requestId = $this->requestId($request);

        if ((int) $engineServerCommand->engine_server_id !== (int) $engineServer->id) {
            return response()->json([
                'error_code' => 'command_not_found',
                'message' => 'Command not found for this server.',
                'request_id' => $requestId,
            ], 404, [
                'X-Request-Id' => $requestId,
            ]);
        }

        return response()->json([
            'data' => $this->serializeCommand($engineServerCommand),
        ], 200, [
            'X-Request-Id' => $requestId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCommand(EngineServerCommand $command): array
    {
        return [
            'id' => $command->id,
            'engine_server_id' => $command->engine_server_id,
            'action' => $command->action,
            'status' => $command->status,
            'source' => $command->source,
            'request_id' => $command->request_id,
            'idempotency_key' => $command->idempotency_key,
            'agent_command_id' => $command->agent_command_id,
            'reason' => $command->reason,
            'error_message' => $command->error_message,
            'started_at' => $command->started_at?->toISOString(),
            'finished_at' => $command->finished_at?->toISOString(),
            'created_at' => $command->created_at?->toISOString(),
            'updated_at' => $command->updated_at?->toISOString(),
        ];
    }

    private function requestId(Request $request): string
    {
        $existing = trim((string) $request->header('X-Request-Id', ''));
        if ($existing !== '') {
            return $existing;
        }

        return (string) Str::uuid();
    }

    private function triggeredBy(Request $request): string
    {
        $triggeredBy = trim((string) $request->header('X-Triggered-By', 'go-control-plane'));

        return $triggeredBy !== '' ? $triggeredBy : 'go-control-plane';
    }
}
