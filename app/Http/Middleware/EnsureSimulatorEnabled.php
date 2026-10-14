<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses the gateway simulator before anything else can run.
 *
 * This runs ahead of the web middleware group, so a POST is refused with a
 * clean 404 rather than stopping at CSRF validation.
 */
class EnsureSimulatorEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('portal.simulator_enabled'), 404);

        return $next($request);
    }
}
