<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\EnginePoolUpsertRequest;
use App\Models\EngineWorkerPool;
use App\Support\AdminAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EnginePoolController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $requestId = $this->requestId($request);
        $pools = EngineWorkerPool::query()
            ->withCount('servers')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $pools->map(fn (EngineWorkerPool $pool): array => $this->serializePool($pool))->values(),
        ], 200, [
            'X-Request-Id' => $requestId,
        ]);
    }

    public function store(EnginePoolUpsertRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $requestId = $this->requestId($request);

        $pool = DB::transaction(function () use ($payload): EngineWorkerPool {
            $isDefault = (bool) data_get($payload, 'is_default', false);
            $isActive = (bool) data_get($payload, 'is_active', true);
            if ($isDefault) {
                $isActive = true;
            }
            if ($isDefault) {
                EngineWorkerPool::query()->update(['is_default' => false]);
            }

            $pool = EngineWorkerPool::query()->create([
                'slug' => $payload['slug'],
                'name' => $payload['name'],
                'description' => $payload['description'] ?? null,
                'is_active' => $isActive,
                'is_default' => $isDefault || ! EngineWorkerPool::query()->where('is_default', true)->exists(),
                'provider_profiles' => $payload['provider_profiles'],
            ]);

            return $pool->loadCount('servers');
        });

        AdminAuditLogger::log('engine_pool_created', $pool, [
            'source' => 'go_control_plane_internal_api',
            'triggered_by' => $this->triggeredBy($request),
        ]);

        return response()->json([
            'data' => $this->serializePool($pool),
        ], 201, [
            'X-Request-Id' => $requestId,
        ]);
    }

    public function update(EnginePoolUpsertRequest $request, EngineWorkerPool $enginePool): JsonResponse
    {
        $payload = $request->validated();
        $requestId = $this->requestId($request);
        $isDefault = (bool) data_get($payload, 'is_default', false);
        $isActive = (bool) data_get($payload, 'is_active', true);
        if ($isDefault) {
            $isActive = true;
        }
        if (($enginePool->is_default || $isDefault) && ! $isActive) {
            return response()->json([
                'error_code' => 'pool_default_inactive',
                'message' => 'Default pool cannot be deactivated.',
                'request_id' => $requestId,
            ], 409, [
                'X-Request-Id' => $requestId,
            ]);
        }

        DB::transaction(function () use ($payload, $enginePool, $isDefault, $isActive): void {
            if ($isDefault) {
                EngineWorkerPool::query()
                    ->whereKeyNot($enginePool->id)
                    ->update(['is_default' => false]);
            }

            $enginePool->fill([
                'slug' => $payload['slug'],
                'name' => $payload['name'],
                'description' => $payload['description'] ?? null,
                'is_active' => $isActive,
                'is_default' => $isDefault || $enginePool->is_default,
                'provider_profiles' => $payload['provider_profiles'],
            ])->save();
        });

        $enginePool->refresh()->loadCount('servers');

        AdminAuditLogger::log('engine_pool_updated', $enginePool, [
            'source' => 'go_control_plane_internal_api',
            'triggered_by' => $this->triggeredBy($request),
        ]);

        return response()->json([
            'data' => $this->serializePool($enginePool),
        ], 200, [
            'X-Request-Id' => $requestId,
        ]);
    }

    public function archive(Request $request, EngineWorkerPool $enginePool): JsonResponse
    {
        $requestId = $this->requestId($request);

        if ($enginePool->is_default) {
            return response()->json([
                'error_code' => 'pool_archive_blocked',
                'message' => 'Default pool cannot be archived.',
                'request_id' => $requestId,
            ], 409, [
                'X-Request-Id' => $requestId,
            ]);
        }

        if ($enginePool->servers()->exists()) {
            return response()->json([
                'error_code' => 'pool_archive_blocked',
                'message' => 'Pool is still assigned to one or more servers.',
                'request_id' => $requestId,
            ], 409, [
                'X-Request-Id' => $requestId,
            ]);
        }

        $enginePool->update([
            'is_active' => false,
            'is_default' => false,
        ]);
        $enginePool->refresh()->loadCount('servers');

        AdminAuditLogger::log('engine_pool_archived', $enginePool, [
            'source' => 'go_control_plane_internal_api',
            'triggered_by' => $this->triggeredBy($request),
        ]);

        return response()->json([
            'data' => $this->serializePool($enginePool),
        ], 200, [
            'X-Request-Id' => $requestId,
        ]);
    }

    public function setDefault(Request $request, EngineWorkerPool $enginePool): JsonResponse
    {
        $requestId = $this->requestId($request);

        if (! $enginePool->is_active) {
            return response()->json([
                'error_code' => 'pool_inactive',
                'message' => 'Inactive pool cannot be set as default.',
                'request_id' => $requestId,
            ], 409, [
                'X-Request-Id' => $requestId,
            ]);
        }

        DB::transaction(function () use ($enginePool): void {
            EngineWorkerPool::query()->update(['is_default' => false]);
            $enginePool->update(['is_default' => true]);
        });

        $enginePool->refresh()->loadCount('servers');

        AdminAuditLogger::log('engine_pool_default_set', $enginePool, [
            'source' => 'go_control_plane_internal_api',
            'triggered_by' => $this->triggeredBy($request),
        ]);

        return response()->json([
            'data' => $this->serializePool($enginePool),
        ], 200, [
            'X-Request-Id' => $requestId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePool(EngineWorkerPool $pool): array
    {
        return [
            'id' => $pool->id,
            'slug' => $pool->slug,
            'name' => $pool->name,
            'description' => $pool->description,
            'is_active' => (bool) $pool->is_active,
            'is_default' => (bool) $pool->is_default,
            'provider_profiles' => $pool->normalizedProviderProfiles(),
            'linked_servers_count' => (int) ($pool->servers_count ?? 0),
            'created_at' => $pool->created_at?->toISOString(),
            'updated_at' => $pool->updated_at?->toISOString(),
        ];
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
