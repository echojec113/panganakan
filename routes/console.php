<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:send-prenatal-reminders')->dailyAt('08:00');

// Time-based clinical alerts (EDD approaching, past EDD / needs review).
// Runs before the 08:00 reminder sweep; the command is idempotent, so a
// re-run on the same day never duplicates an alert.
Schedule::command('notifications:check-clinical-alerts')->dailyAt('07:00');
