<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\EngineServerUpsertRequest;
use App\Models\EngineServer;
use App\Models\EngineServerCommand;
use App\Models\EngineServerProvisioningBundle;
use App\Models\VerifierDomain;
use App\Support\AdminAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EngineServerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $requestId = $this->requestId($request);
        $servers = EngineServer::query()
            ->with([
                'verifierDomain:id,domain',
                'workerPool:id,slug,name',
                'latestProvisioningBundle',
                'latestCommand',
            ])
            ->orderBy('name')
            ->get();

        $domains = VerifierDomain::query()
            ->where('is_active', true)
            ->orderBy('domain')
            ->get(['id', 'domain']);

        return response()->json([
            'data' => [
                'servers' => $servers->map(fn (EngineServer $server): array => $this->serializeServer($server))->values(),
                'verifier_domains' => $domains->map(fn (VerifierDomain $domain): array => [
                    'id' => $domain->id,
                    'domain' => $domain->domain,
                ])->values(),
            ],
        ], 200, [
            'X-Request-Id' => $requestId,
        ]);
    }

    public function store(EngineServerUpsertRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $server = EngineServer::query()->create($payload);
        $server->load(['verifierDomain:id,domain', 'latestProvisioningBundle', 'latestCommand']);
        $server->loadMissing('workerPool:id,slug,name');

        AdminAuditLogger::log('engine_server_created', $server, [
            'source' => 'go_control_plane_internal_api',
            'triggered_by' => $this->triggeredBy($request),
        ]);

        $requestId = $this->requestId($request);

        return response()->json([
            'data' => $this->serializeServer($server),
        ], 201, [
            'X-Request-Id' => $requestId,
        ]);
    }

    public function update(EngineServerUpsertRequest $request, EngineServer $engineServer): JsonResponse
    {
        $payload = $request->validated();
        $engineServer->fill($payload)->save();
        $engineServer->load(['verifierDomain:id,domain', 'workerPool:id,slug,name', 'latestProvisioningBundle', 'latestCommand']);

        AdminAuditLogger::log('engine_server_updated', $engineServer, [
            'source' => 'go_control_plane_internal_api',
            'triggered_by' => $this->triggeredBy($request),
        ]);

        $requestId = $this->requestId($request);

        return response()->json([
            'data' => $this->serializeServer($engineServer),
        ], 200, [
            'X-Request-Id' => $requestId,
        ]);
    }

    public function destroy(Request $request, EngineServer $engineServer): JsonResponse
    {
        $requestId = $this->requestId($request);
        $engineServer->load('latestCommand');

        /** @var EngineServerCommand|null $latestCommand */
        $latestCommand = $engineServer->latestCommand;
        $processState = $this->resolveProcessState($engineServer, $latestCommand);
        $heartbeatState = $this->resolveHeartbeatState($engineServer);
        $activeProcess = in_array($processState, ['running', 'starting', 'stopping'], true);

        if ($activeProcess || $heartbeatState === 'healthy') {
            return response()->json([
                'error_code' => 'server_delete_blocked',
                'message' => 'Server is active. Stop the process and wait for heartbeat to become stale/none before deleting.',
                'request_id' => $requestId,
                'details' => [
                    'process_state' => $processState,
                    'heartbeat_state' => $heartbeatState,
                ],
            ], 409, [
                'X-Request-Id' => $requestId,
            ]);
        }

        AdminAuditLogger::log('engine_server_deleted', $engineServer, [
            'source' => 'go_control_plane_internal_api',
            'triggered_by' => $this->triggeredBy($request),
            'process_state' => $processState,
            'heartbeat_state' => $heartbeatState,
        ]);

        $engineServerID = $engineServer->id;
        $engineServerName = $engineServer->name;
        $engineServer->delete();

        return response()->json([
            'data' => [
                'id' => $engineServerID,
                'name' => $engineServerName,
                'deleted' => true,
            ],
        ], 200, [
            'X-Request-Id' => $requestId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeServer(EngineServer $server): array
    {
        /** @var EngineServerProvisioningBundle|null $latestBundle */
        $latestBundle = $server->latestProvisioningBundle;
        /** @var EngineServerCommand|null $latestCommand */
        $latestCommand = $server->latestCommand;
        $latestBundleData = null;
        if ($latestBundle) {
            $latestBundleData = [
                'bundle_uuid' => $latestBundle->bundle_uuid,
                'expires_at' => $latestBundle->expires_at?->toISOString(),
                'is_expired' => $latestBundle->isExpired(),
                'created_at' => $latestBundle->created_at?->toISOString(),
            ];
        }

        $processState = $this->resolveProcessState($server, $latestCommand);
        $heartbeatState = $this->resolveHeartbeatState($server);
        $lastTransitionAt = $latestCommand?->finished_at?->toISOString() ?? $latestCommand?->updated_at?->toISOString();

        return [
            'id' => $server->id,
            'name' => $server->name,
            'ip_address' => $server->ip_address,
            'environment' => $server->environment,
            'region' => $server->region,
            'last_heartbeat_at' => $server->last_heartbeat_at?->toISOString(),
            'status' => $server->isOnline() ? 'online' : 'offline',
            'is_active' => (bool) $server->is_active,
            'drain_mode' => (bool) $server->drain_mode,
            'max_concurrency' => $server->max_concurrency,
            'helo_name' => $server->helo_name,
            'mail_from_address' => $server->mail_from_address,
            'identity_domain' => $server->verifierDomain?->domain ?? $server->identity_domain,
            'verifier_domain_id' => $server->verifier_domain_id,
            'verifier_domain' => $server->verifierDomain?->domain,
            'worker_pool_id' => $server->worker_pool_id,
            'worker_pool_slug' => $server->workerPool?->slug,
            'worker_pool_name' => $server->workerPool?->name,
            'notes' => $server->notes,
            'process_control_mode' => $server->process_control_mode,
            'agent_enabled' => (bool) $server->agent_enabled,
            'agent_base_url' => $server->agent_base_url,
            'agent_timeout_seconds' => $server->agent_timeout_seconds,
            'agent_verify_tls' => (bool) $server->agent_verify_tls,
            'agent_service_name' => $server->agent_service_name,
            'last_agent_seen_at' => $server->last_agent_seen_at?->toISOString(),
            'last_agent_error' => $server->last_agent_error,
            'latest_provisioning_bundle' => $latestBundleData,
            'process_state' => $processState,
            'heartbeat_state' => $heartbeatState,
            'last_transition_at' => $lastTransitionAt,
            'last_command_status' => $latestCommand?->status,
            'last_command_request_id' => $latestCommand?->request_id,
        ];
    }

    private function resolveProcessState(EngineServer $server, ?EngineServerCommand $latestCommand): string
    {
        $serviceState = strtolower(trim((string) data_get($server->last_agent_status, 'service_state', '')));
        $processState = match ($serviceState) {
            'active', 'running' => 'running',
            'inactive', 'failed', 'dead' => 'stopped',
            'activating' => 'starting',
            'deactivating' => 'stopping',
            default => 'unknown',
        };

        if ($latestCommand && $latestCommand->status === 'pending') {
            return match ($latestCommand->action) {
                'start', 'restart' => 'starting',
                'stop' => 'stopping',
                default => $processState,
            };
        }

        return $processState;
    }

    private function resolveHeartbeatState(EngineServer $server): string
    {
        if (! $server->last_heartbeat_at) {
            return 'none';
        }

        if ($server->isOnline()) {
            return 'healthy';
        }

        return 'stale';
    }

    private function triggeredBy(Request $request): string
    {
        $triggeredBy = trim((string) $request->header('X-Triggered-By', 'go-control-plane'));

        return $triggeredBy !== '' ? $triggeredBy : 'go-control-plane';
    }

    private function requestId(Request $request): string
    {
        $existing = trim((string) $request->header('X-Request-Id', ''));
        if ($existing !== '') {
            return $existing;
        }

        return (string) Str::uuid();
    }
}
