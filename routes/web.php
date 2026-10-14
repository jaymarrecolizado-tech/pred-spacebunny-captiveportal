<?php

use App\Http\Controllers\Portal\OmadaAdapterController;
use App\Http\Controllers\Portal\PortalSessionController;
use App\Http\Controllers\PublicSurveyController;
use Illuminate\Support\Facades\Route;

/*
| Public, unauthenticated routes. These must stay usable on a slow phone with a
| captive portal in the way, so there is no JavaScript requirement and no login.
*/

Route::view('/', 'public.landing')->name('landing');

Route::get('/feedback-unavailable', [PublicSurveyController::class, 'unavailable'])
    ->name('site.unavailable');

Route::prefix('f/{siteCode}')->name('site.')->group(function (): void {
    Route::get('/', [PublicSurveyController::class, 'show'])->name('survey');
    Route::post('/', [PublicSurveyController::class, 'store'])
        ->middleware('throttle:public-submit')
        ->name('survey.store');
    Route::get('/thanks', [PublicSurveyController::class, 'thanks'])->name('thanks');
    Route::get('/qr', [PublicSurveyController::class, 'poster'])->name('qr');
});

/*
| Browser side of a validated captive portal session (Spec 11.2). The signed
| token in the query string is the authorisation: no account, no cookie, and no
| way to point one site's token at another site.
*/
Route::prefix('portal/session/{session}')->name('portal.')->group(function (): void {
    Route::get('/', [PortalSessionController::class, 'show'])->name('session');
    Route::post('/', [PortalSessionController::class, 'store'])
        ->middleware('throttle:public-submit')
        ->name('session.store');
    Route::get('/thanks', [PortalSessionController::class, 'thanks'])->name('thanks');
});

Route::view('portal/unavailable', 'portal.unavailable')->name('portal.unavailable');

/*
| Omada External Portal Server adapter (receive side). Omada appends its
| unsigned handoff parameters to this URL; the site is bound via the path.
*/
Route::get('portal/omada/{siteCode}', [OmadaAdapterController::class, 'show'])->name('portal.omada');
Route::post('portal/omada/{siteCode}', [OmadaAdapterController::class, 'store'])
    ->middleware('throttle:public-submit')
    ->name('portal.omada.store');
Route::get('portal/omada/{siteCode}/thanks', [OmadaAdapterController::class, 'thanks'])->name('portal.omada.thanks');
