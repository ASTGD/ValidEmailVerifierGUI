<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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
        $expectedToken = trim((string) config('services.go_control_plane.internal_api_token', ''));
        if ($expectedToken === '') {
            return new JsonResponse([
                'error' => 'internal token is not configured',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $providedToken = trim((string) $request->header('X-Internal-Token', ''));
        if ($providedToken === '') {
            $authorizationHeader = trim((string) $request->header('Authorization', ''));
            if (str_starts_with($authorizationHeader, 'Bearer ')) {
                $providedToken = trim(substr($authorizationHeader, 7));
            }
        }

        if ($providedToken === '' || ! hash_equals($expectedToken, $providedToken)) {
            return new JsonResponse([
                'error' => 'unauthorized',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
