<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\ProvisioningCredentialUpdateRequest;
use App\Models\EngineSetting;
use App\Support\AdminAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProvisioningCredentialController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $requestId = $this->requestId($request);
        $settings = $this->settingsRecord();

        return response()->json([
            'data' => $this->serialize($settings),
        ], 200, [
            'X-Request-Id' => $requestId,
        ]);
    }

    public function update(ProvisioningCredentialUpdateRequest $request): JsonResponse
    {
        $requestId = $this->requestId($request);
        $payload = $request->validated();
        $settings = $this->settingsRecord();

        $newUsername = trim((string) ($payload['ghcr_username'] ?? ''));
        $newToken = trim((string) ($payload['ghcr_token'] ?? ''));
        $clearToken = $request->boolean('clear_ghcr_token');
        $maskedPlaceholder = $this->isMaskedPlaceholder($newToken);
        $tokenWasSet = trim((string) ($settings->provisioning_ghcr_token ?? '')) !== '';

        $tokenRotated = false;
        $settings->provisioning_ghcr_username = $newUsername;
        if ($clearToken) {
            $settings->provisioning_ghcr_token = null;
        } elseif ($newToken !== '' && ! $maskedPlaceholder) {
            $settings->provisioning_ghcr_token = $newToken;
            $tokenRotated = true;
        }
        $settings->save();

        $tokenIsSet = trim((string) ($settings->provisioning_ghcr_token ?? '')) !== '';

        AdminAuditLogger::log('engine_provisioning_credentials_updated', $settings, [
            'source' => 'go_control_plane_internal_api',
            'triggered_by' => $this->triggeredBy($request),
            'token_rotated' => $tokenRotated,
            'token_cleared' => $clearToken,
            'token_present' => $tokenIsSet,
            'token_present_before' => $tokenWasSet,
        ]);

        return response()->json([
            'data' => $this->serialize($settings),
        ], 200, [
            'X-Request-Id' => $requestId,
        ]);
    }

    public function reveal(Request $request): JsonResponse
    {
        $requestId = $this->requestId($request);
        $settings = $this->settingsRecord();
        $token = trim((string) ($settings->provisioning_ghcr_token ?? ''));

        if ($token === '') {
            return response()->json([
                'error_code' => 'ghcr_token_missing',
                'message' => 'GHCR token is not configured.',
                'request_id' => $requestId,
            ], 404, [
                'X-Request-Id' => $requestId,
            ]);
        }

        AdminAuditLogger::log('engine_provisioning_credentials_revealed', $settings, [
            'source' => 'go_control_plane_internal_api',
            'triggered_by' => $this->triggeredBy($request),
        ]);

        return response()->json([
            'data' => [
                'ghcr_token' => $token,
            ],
        ], 200, [
            'X-Request-Id' => $requestId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(EngineSetting $settings): array
    {
        $token = trim((string) ($settings->provisioning_ghcr_token ?? ''));

        return [
            'ghcr_username' => trim((string) ($settings->provisioning_ghcr_username ?? '')),
            'ghcr_token_configured' => $token !== '',
            'ghcr_token_masked' => $this->maskToken($token),
            'updated_at' => $settings->updated_at?->toISOString(),
        ];
    }

    private function maskToken(string $token): string
    {
        if ($token === '') {
            return '';
        }

        return '******';
    }

    private function isMaskedPlaceholder(string $value): bool
    {
        $trimmed = trim($value);
        if (strlen($trimmed) < 6) {
            return false;
        }

        return str_replace('*', '', $trimmed) === '';
    }

    private function settingsRecord(): EngineSetting
    {
        $settings = EngineSetting::query()->first();
        if ($settings) {
            return $settings;
        }

        return EngineSetting::query()->create([
            'engine_paused' => false,
            'enhanced_mode_enabled' => false,
        ]);
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
