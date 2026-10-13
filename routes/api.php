<?php

use App\Http\Controllers\Api\PublicApiController;
use App\Http\Controllers\Portal\PortalApiController;
use App\Http\Middleware\AuthenticateIntegration;
use Illuminate\Support\Facades\Route;

/*
| Public API, Spec section 11.1. No authentication: these endpoints are reached
| by the public user, not by an administrator.
*/

Route::prefix('v1/public')->name('api.v1.public.')->group(function (): void {
    Route::get('health', [PublicApiController::class, 'health'])->name('health');

    Route::get('sites/{siteCode}/survey', [PublicApiController::class, 'survey'])
        ->middleware('throttle:public-read')
        ->name('survey');

    Route::post('responses', [PublicApiController::class, 'store'])
        ->middleware('throttle:public-submit')
        ->name('responses.store');
});

/*
| Captive portal contract, Spec section 11.2. Server-to-server: the gateway is
| authenticated with its own per-integration credentials, never with a user's.
*/
Route::prefix('v1/integrations/portal')
    ->middleware([AuthenticateIntegration::class, 'throttle:integration'])
    ->name('api.v1.integrations.portal.')
    ->group(function (): void {
        Route::post('init', [PortalApiController::class, 'init'])->name('init');
        Route::post('status', [PortalApiController::class, 'status'])->name('status');
        Route::post('complete', [PortalApiController::class, 'complete'])->name('complete');
    });
