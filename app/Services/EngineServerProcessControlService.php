<?php

namespace App\Services;

use App\Models\EngineServer;
use App\Models\EngineServerCommand;
use App\Support\AdminAuditLogger;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class EngineServerProcessControlService
{
    public function createAndExecute(
        EngineServer $engineServer,
        string $action,
        ?string $reason,
        ?string $idempotencyKey,
        ?string $requestId,
        string $source,
        ?int $requestedByUserId
    ): EngineServerCommand {
        $normalizedAction = strtolower(trim($action));
        $normalizedReason = $this->normalizeNullable($reason);
        $normalizedIdempotencyKey = $this->normalizeNullable($idempotencyKey);
        $normalizedRequestId = $this->normalizeNullable($requestId);

        if ($normalizedIdempotencyKey !== null) {
            $existing = EngineServerCommand::query()
                ->where('engine_server_id', $engineServer->id)
                ->where('idempotency_key', $normalizedIdempotencyKey)
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        $command = EngineServerCommand::query()->create([
            'engine_server_id' => $engineServer->id,
            'action' => $normalizedAction,
            'status' => 'pending',
            'requested_by_user_id' => $requestedByUserId,
            'source' => $source,
            'request_id' => $normalizedRequestId,
            'idempotency_key' => $normalizedIdempotencyKey,
            'reason' => $normalizedReason,
            'started_at' => now(),
        ]);

        return $this->execute($engineServer->fresh(), $command);
    }

    public function execute(EngineServer $engineServer, EngineServerCommand $command): EngineServerCommand
    {
        if (! $engineServer->supportsAgentProcessControl()) {
            return $this->markFailed($engineServer, $command, 'Agent process control is disabled for this server.');
        }

        $agentBaseUrl = $this->normalizeNullable($engineServer->agent_base_url);
        if ($agentBaseUrl === null) {
            return $this->markFailed($engineServer, $command, 'Agent base URL is not configured.');
        }

        $agentToken = trim((string) config('engine_servers.process_control.agent_token', ''));
        $agentHmacSecret = trim((string) config('engine_servers.process_control.agent_hmac_secret', ''));
        if ($agentToken === '' || $agentHmacSecret === '') {
            return $this->markFailed($engineServer, $command, 'Agent credentials are not configured.');
        }

        $serviceName = trim((string) $engineServer->agent_service_name);
        if ($serviceName === '') {
            $serviceName = 'vev-worker.service';
        }

        $endpointPath = '/v1/commands';
        $payload = [
            'command_id' => $command->id,
            'action' => $command->action,
            'service' => $serviceName,
            'timeout_seconds' => $this->timeoutSeconds($engineServer),
        ];
        if ($command->reason !== null) {
            $payload['reason'] = $command->reason;
        }

        $encodedPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if (! is_string($encodedPayload) || $encodedPayload === '') {
            return $this->markFailed($engineServer, $command, 'Unable to encode agent command payload.');
        }

        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $signature = hash_hmac(
            'sha256',
            implode("\n", [
                'POST',
                $endpointPath,
                $timestamp,
                $nonce,
                hash('sha256', $encodedPayload),
            ]),
            $agentHmacSecret
        );

        try {
            $request = $this->agentClient($engineServer, $agentToken)
                ->withHeaders([
                    'X-Timestamp' => $timestamp,
                    'X-Nonce' => $nonce,
                    'X-Signature' => $signature,
                    'X-Request-Id' => (string) ($command->request_id ?: Str::uuid()),
                ]);

            $response = $request->post(rtrim($agentBaseUrl, '/').$endpointPath, $payload);
            $decoded = $response->json();
            $agentReportedStatus = is_array($decoded)
                ? strtolower(trim((string) data_get($decoded, 'status', '')))
                : '';

            $command->agent_response = is_array($decoded) ? $decoded : null;
            $command->agent_command_id = is_array($decoded)
                ? $this->normalizeNullable((string) data_get($decoded, 'agent_command_id', ''))
                : null;

            if ($response->successful() && $agentReportedStatus !== 'failed') {
                $command->status = 'success';
                $command->error_message = null;
                $engineServer->last_agent_seen_at = now();
                $engineServer->last_agent_error = null;
                $engineServer->last_agent_status = is_array($decoded) ? $decoded : null;
            } else {
                $command->status = 'failed';
                $command->error_message = $this->errorMessageFromResponse($response->status(), $decoded);
                $engineServer->last_agent_error = $command->error_message;
                $engineServer->last_agent_status = is_array($decoded) ? $decoded : null;
            }
        } catch (Throwable $throwable) {
            $command->status = 'failed';
            $command->error_message = 'Agent request failed: '.$throwable->getMessage();
            $engineServer->last_agent_error = $command->error_message;
        }

        $command->finished_at = now();
        $command->save();
        $engineServer->save();

        AdminAuditLogger::log('engine_server_command_executed', $engineServer, [
            'source' => $command->source,
            'action' => $command->action,
            'command_id' => $command->id,
            'command_status' => $command->status,
            'request_id' => $command->request_id,
        ]);

        return $command;
    }

    private function markFailed(EngineServer $engineServer, EngineServerCommand $command, string $message): EngineServerCommand
    {
        $command->status = 'failed';
        $command->error_message = $message;
        $command->finished_at = now();
        $command->save();

        $engineServer->last_agent_error = $message;
        $engineServer->save();

        AdminAuditLogger::log('engine_server_command_executed', $engineServer, [
            'source' => $command->source,
            'action' => $command->action,
            'command_id' => $command->id,
            'command_status' => 'failed',
            'request_id' => $command->request_id,
            'error_message' => $message,
        ]);

        return $command;
    }

    private function agentClient(EngineServer $engineServer, string $agentToken): PendingRequest
    {
        $client = Http::acceptJson()
            ->withToken($agentToken)
            ->timeout($this->timeoutSeconds($engineServer));

        if (! $engineServer->agent_verify_tls) {
            $client = $client->withoutVerifying();
        }

        return $client;
    }

    private function timeoutSeconds(EngineServer $engineServer): int
    {
        $defaultTimeout = max(2, (int) config('engine_servers.process_control.default_timeout_seconds', 8));
        $timeout = $engineServer->agent_timeout_seconds > 0 ? $engineServer->agent_timeout_seconds : $defaultTimeout;

        return max(2, min(30, $timeout));
    }

    /**
     * @param  array<string, mixed>|null  $decoded
     */
    private function errorMessageFromResponse(int $status, ?array $decoded): string
    {
        $message = $this->normalizeNullable((string) data_get($decoded ?? [], 'message', ''));
        if ($message !== null) {
            return $message;
        }

        return sprintf('Agent command request failed with status %d.', $status);
    }

    private function normalizeNullable(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
