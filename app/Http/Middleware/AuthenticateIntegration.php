<?php

namespace App\Http\Middleware;

use App\Models\Integration;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-to-server authentication for the portal endpoints (Spec 12.3).
 *
 * Credentials are per-integration and presented in headers. A wrong or revoked
 * credential is answered with the same opaque error everywhere, so the
 * endpoint does not confirm which identifiers exist.
 */
class AuthenticateIntegration
{
    public function handle(Request $request, Closure $next): Response
    {
        $integration = Integration::query()
            ->where('public_identifier', (string) $request->header('X-Integration-Id'))
            ->first();

        if ($integration === null || ! $integration->secretMatches($request->header('X-Integration-Secret'))) {
            return response()->json([
                'error' => [
                    'code' => 'unauthenticated_integration',
                    'message' => 'Integration credentials were not accepted.',
                    'correlation_id' => Context::get('correlation_id'),
                ],
            ], 401);
        }

        if (! $integration->acceptsRequests()) {
            return response()->json([
                'error' => [
                    'code' => 'integration_not_active',
                    'message' => 'This integration is not active.',
                    'correlation_id' => Context::get('correlation_id'),
                ],
            ], 403);
        }

        $request->attributes->set('integration', $integration);

        return $next($request);
    }
}
