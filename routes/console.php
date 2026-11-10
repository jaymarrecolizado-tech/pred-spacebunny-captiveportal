<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Actual traffic samples. The command checks the controller itself, so a missing
// or broken Omada connection is recorded as an empty run rather than a crash.
Schedule::command('omada:speed-sample')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// A local compressed dump with a 14-day retention window. It is not off-server
// storage; add an external destination once one is approved.
Schedule::command('db:backup')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->onOneServer();
