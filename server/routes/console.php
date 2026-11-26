<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduler heartbeat for automated health diagnostics
Schedule::call(function () {
    Cache::put('scheduler_heartbeat', now()->toIso8601String(), 300);
})->everyMinute();

// Institutional automated operational schedules
Schedule::command('dms:check-due-dates')->everyMinute()->withoutOverlapping();
Schedule::command('dms:cleanup-import-uploads')->dailyAt('02:30')->withoutOverlapping();
Schedule::command('dms:backup --encrypt')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('dms:backup-cleanup')->dailyAt('03:00')->withoutOverlapping();

// PKI key-at-rest encryption migration (run once after deploy; safe to re-run)
// php artisan pki:encrypt-keys-at-rest


