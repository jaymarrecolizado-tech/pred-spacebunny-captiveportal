<?php

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
