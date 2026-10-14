<?php

use App\Http\Controllers\Simulator\GatewayController;
use Illuminate\Support\Facades\Route;

/*
| Captive portal gateway simulator. Reachable only when
| config('portal.simulator_enabled') is true, which defaults to local only.
|
| Access is enforced inside the controller's guard() rather than by the `auth`
| middleware: that middleware redirects to a route named "login", which does
| not exist in this application (Filament registers its own), so it would fail
| with a 500 instead of a clean refusal.
*/
Route::prefix('simulator')
    ->name('simulator.')
    ->group(function (): void {
        Route::get('/', [GatewayController::class, 'index'])->name('index');
        Route::post('connect', [GatewayController::class, 'connect'])->name('connect');
        Route::post('status', [GatewayController::class, 'status'])->name('status');
        Route::post('tamper', [GatewayController::class, 'tamper'])->name('tamper');
    });
