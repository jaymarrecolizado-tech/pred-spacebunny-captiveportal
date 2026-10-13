<?php

use App\Http\Controllers\Api\PublicApiController;
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
