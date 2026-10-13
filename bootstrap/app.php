<?php

use App\Http\Middleware\AssignCorrelationId;
use App\Services\Portal\PortalContractException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('web')
                ->group(base_path('routes/simulator.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // FR-OPS-004: correlation ID for web and API alike.
        $middleware->append(AssignCorrelationId::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Spec 11.4: one JSON envelope for the public API, correlation ID but no
        // stack trace or configuration detail.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            [$status, $code, $message] = match (true) {
                // A refusal the contract defines by name, so a gateway can react
                // to a specific failure rather than parsing prose (Spec 9.5).
                $e instanceof PortalContractException => [$e->status, $e->reason, $e->getMessage()],
                $e instanceof ValidationException => [422, 'validation_failed', 'The submitted data is invalid.'],
                $e instanceof NotFoundHttpException => [404, 'not_found', 'The requested resource was not found.'],
                $e instanceof TooManyRequestsHttpException => [429, 'rate_limited', 'Too many requests. Please wait and try again.'],
                $e instanceof HttpExceptionInterface => [$e->getStatusCode(), 'request_failed', 'The request could not be completed.'],
                default => [500, 'server_error', 'An unexpected error occurred.'],
            };

            return response()->json([
                'error' => array_filter([
                    'code' => $code,
                    'message' => $message,
                    'fields' => $e instanceof ValidationException ? $e->errors() : null,
                    'correlation_id' => Context::get('correlation_id'),
                ]),
            ], $status);
        });
    })->create();
