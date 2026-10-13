<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * FR-OPS-004: every request carries a correlation ID so an error report can be
 * traced without logging payloads or tokens.
 */
class AssignCorrelationId
{
    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = $request->headers->get('X-Correlation-Id');

        if (! is_string($correlationId) || ! Str::isUuid($correlationId)) {
            $correlationId = (string) Str::uuid();
        }

        Context::add('correlation_id', $correlationId);

        $response = $next($request);
        $response->headers->set('X-Correlation-Id', $correlationId);

        return $response;
    }
}
