<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule missing checkout detection to run daily at 2:00 AM
Schedule::command('violations:detect-missing-checkouts')->dailyAt('02:00');
