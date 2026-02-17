<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Models\EngineServer;
use App\Models\EngineServerProvisioningBundle;
use App\Services\EngineWorkerProvisioningService;
use App\Support\AdminAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class EngineServerProvisioningBundleController extends Controller
{
    public function store(Request $request, EngineServer $engineServer, EngineWorkerProvisioningService $service): JsonResponse
    {
        $requestId = $this->requestId($request);
        $bundle = $service->createBundle($engineServer, null);

        AdminAuditLogger::log('engine_worker_bundle_generated', $engineServer, [
            'source' => 'go_control_plane_internal_api',
            'triggered_by' => $this->triggeredBy($request),
            'bundle_id' => $bundle->id,
            'expires_at' => $bundle->expires_at?->toDateTimeString(),
        ]);

        return response()->json([
            'data' => $this->serializeBundle($bundle),
        ], 201, [
            'X-Request-Id' => $requestId,
        ]);
    }

    public function showLatest(Request $request, EngineServer $engineServer): JsonResponse
    {
        $requestId = $this->requestId($request);
        $bundle = $engineServer->provisioningBundles()->latest()->first();
        if (! $bundle) {
            return response()->json([
                'error_code' => 'bundle_not_found',
                'message' => 'No provisioning bundle found.',
                'request_id' => $requestId,
            ], 404, [
                'X-Request-Id' => $requestId,
            ]);
        }

        return response()->json([
            'data' => $this->serializeBundle($bundle),
        ], 200, [
            'X-Request-Id' => $requestId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeBundle(EngineServerProvisioningBundle $bundle): array
    {
        $urls = $this->buildDownloadUrls($bundle);

        return [
            'bundle_uuid' => $bundle->bundle_uuid,
            'engine_server_id' => $bundle->engine_server_id,
            'expires_at' => $bundle->expires_at?->toISOString(),
            'is_expired' => $bundle->isExpired(),
            'download_urls' => $urls,
            'install_command_template' => isset($urls['install'])
                ? sprintf(
                    'curl -fsSL "%s" | bash -s -- --ghcr-user "<ghcr-username>" --ghcr-token "<ghcr-token>"',
                    $urls['install']
                )
                : null,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function buildDownloadUrls(EngineServerProvisioningBundle $bundle): array
    {
        if ($bundle->isExpired()) {
            return [];
        }

        $expiresAt = $bundle->expires_at ?? now();
        $appUrl = trim((string) config('app.url'), '/');
        $scheme = $appUrl !== '' ? parse_url($appUrl, PHP_URL_SCHEME) : null;

        if ($appUrl !== '') {
            URL::forceRootUrl($appUrl);
        }
        if (is_string($scheme) && $scheme !== '') {
            URL::forceScheme($scheme);
        }

        try {
            return [
                'install' => URL::temporarySignedRoute('provisioning-bundles.download', $expiresAt, [
                    'bundle' => $bundle->bundle_uuid,
                    'file' => 'install.sh',
                ]),
                'env' => URL::temporarySignedRoute('provisioning-bundles.download', $expiresAt, [
                    'bundle' => $bundle->bundle_uuid,
                    'file' => 'worker.env',
                ]),
            ];
        } finally {
            if ($appUrl !== '') {
                URL::forceRootUrl(null);
            }
            if (is_string($scheme) && $scheme !== '') {
                URL::forceScheme(null);
            }
        }
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
