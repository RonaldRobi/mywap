<?php

use App\Console\Commands\ProcessAgeTransitions;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Default inspire quote command (Breeze default)
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ─── MyMarhalah Scheduled Jobs ────────────────────────────────────────────────

/**
 * Age Transition Engine
 *
 * Runs every night at midnight (Malaysia Standard Time, UTC+8).
 * The command uses chunkById so it safely processes unlimited members.
 * withoutOverlapping() prevents a second instance from starting if the
 * previous run is still processing a very large member base.
 *
 * Production crontab entry to add to server:
 *   * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
 */
Schedule::command(ProcessAgeTransitions::class)
    ->dailyAt('00:00')
    ->timezone('Asia/Kuala_Lumpur')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/age-transitions.log'));

/**
 * Annual Fee Engine
 *
 * Every 1 January (Malaysia time):
 *  1. fees:generate              — create unpaid fee records for the new year.
 *  2. fees:notify-start-of-year  — send an in-app invoice/reminder to each member
 *                                  with an unpaid fee, linking to the payment page.
 */
Schedule::command('fees:generate')
    ->yearlyOn(1, 1, '00:30')
    ->timezone('Asia/Kuala_Lumpur')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/fees-annual.log'));

Schedule::command('fees:notify-start-of-year')
    ->yearlyOn(1, 1, '01:00')
    ->timezone('Asia/Kuala_Lumpur')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/fees-annual.log'));

