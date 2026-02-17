<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\EngineServerUpsertRequest;
use App\Models\EngineServer;
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
                'latestProvisioningBundle',
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
        $server->load(['verifierDomain:id,domain', 'latestProvisioningBundle']);

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
        $engineServer->load(['verifierDomain:id,domain', 'latestProvisioningBundle']);

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

    /**
     * @return array<string, mixed>
     */
    private function serializeServer(EngineServer $server): array
    {
        /** @var EngineServerProvisioningBundle|null $latestBundle */
        $latestBundle = $server->latestProvisioningBundle;
        $latestBundleData = null;
        if ($latestBundle) {
            $latestBundleData = [
                'bundle_uuid' => $latestBundle->bundle_uuid,
                'expires_at' => $latestBundle->expires_at?->toISOString(),
                'is_expired' => $latestBundle->isExpired(),
                'created_at' => $latestBundle->created_at?->toISOString(),
            ];
        }

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
            'notes' => $server->notes,
            'latest_provisioning_bundle' => $latestBundleData,
        ];
    }

    private function triggeredBy(EngineServerUpsertRequest $request): string
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
