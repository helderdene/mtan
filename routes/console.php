<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule missing checkout detection to run daily at 2:00 AM
Schedule::command('violations:detect-missing-checkouts')->dailyAt('02:00');

// Schedule daily violation digest to run daily at 8:00 AM
Schedule::command('notifications:send-daily-violation-digest')->dailyAt('08:00');

// Schedule shift rotation advancement to run daily at midnight
Schedule::command('rotations:advance')->dailyAt('00:00');

// Schedule daily reports to run every day at 6:00 AM
Schedule::command('reports:send-scheduled --type=daily')->dailyAt('06:00');

// Schedule weekly reports to run every Monday at 7:00 AM
Schedule::command('reports:send-scheduled --type=weekly')->weeklyOn(1, '07:00');

// Schedule monthly reports to run on the 1st of every month at 8:00 AM
Schedule::command('reports:send-scheduled --type=monthly')->monthlyOn(1, '08:00');

// Schedule cleanup of old report files every day at 3:00 AM
Schedule::command('reports:cleanup')->dailyAt('03:00');

// Schedule pruning of old failed jobs (older than 7 days) weekly on Sunday at 2:00 AM
Schedule::command('queue:prune-failed --hours=168')->weeklyOn(0, '02:00');
