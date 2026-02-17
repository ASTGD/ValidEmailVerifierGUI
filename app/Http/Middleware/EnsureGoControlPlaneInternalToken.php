<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class EnsureGoControlPlaneInternalToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->requestId($request);
        $expectedToken = trim((string) config('services.go_control_plane.internal_api_token', ''));
        if ($expectedToken === '') {
            return $this->errorResponse(
                'internal_token_missing',
                'Internal token is not configured.',
                Response::HTTP_SERVICE_UNAVAILABLE,
                $requestId
            );
        }

        $providedToken = trim((string) $request->header('X-Internal-Token', ''));
        if ($providedToken === '') {
            $authorizationHeader = trim((string) $request->header('Authorization', ''));
            if (str_starts_with($authorizationHeader, 'Bearer ')) {
                $providedToken = trim(substr($authorizationHeader, 7));
            }
        }

        if ($providedToken === '' || ! hash_equals($expectedToken, $providedToken)) {
            return $this->errorResponse(
                'unauthorized',
                'Unauthorized.',
                Response::HTTP_UNAUTHORIZED,
                $requestId
            );
        }

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }

    private function requestId(Request $request): string
    {
        $existing = trim((string) $request->headers->get('X-Request-Id', ''));
        if ($existing !== '') {
            return $existing;
        }

        return (string) Str::uuid();
    }

    private function errorResponse(string $errorCode, string $message, int $status, string $requestId): JsonResponse
    {
        return new JsonResponse([
            'error_code' => $errorCode,
            'message' => $message,
            'request_id' => $requestId,
        ], $status, [
            'X-Request-Id' => $requestId,
        ]);
    }
}
