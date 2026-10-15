<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Hourly due check performs at most one archive run per Philippine calendar month.
// Request termination and startup also catch up when the free hosting service was asleep.
Schedule::command('incidents:archive --monthly')->hourly()->timezone('Asia/Manila')->withoutOverlapping();

Schedule::command('weather:refresh-daily --force')
    ->everyFifteenMinutes()
    ->timezone('Asia/Manila')
    ->withoutOverlapping();
