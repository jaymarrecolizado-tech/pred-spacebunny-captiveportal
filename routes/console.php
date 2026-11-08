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
