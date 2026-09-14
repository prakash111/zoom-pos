<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('notifications:dispatch-scheduled')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('notifications:dispatch-vertical')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('license:check-status')
    ->daily()
    ->withoutOverlapping()
    ->runInBackground();

// Poll on quarter-hour boundaries so 10:00 and 18:00 are honored in each
// tenant's own timezone (including UTC offsets of 30 or 45 minutes). The
// command itself filters frequency/day and database dispatch keys prevent
// duplicate reminders when multiple scheduler workers overlap.
Schedule::command('app:dispatch-automated-reminders --scheduled')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// This host uses the database queue but has no resident queue worker. Drain
// only the notifications queue after each scheduler tick and exit when empty.
Schedule::command('queue:work --queue=notifications --stop-when-empty --tries=3 --timeout=120')
    ->everyMinute()
    ->withoutOverlapping(10)
    ->onOneServer();
