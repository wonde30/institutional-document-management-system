<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('dms:check-due-dates')->everyMinute()->withoutOverlapping();

// Temporary dataset files of inspected imports that were never executed are
// removed after the configured retention window. This requires the Laravel
// scheduler to be running (`php artisan schedule:run` from cron every minute).
Schedule::command('dms:cleanup-import-uploads')->dailyAt('02:30')->withoutOverlapping();

