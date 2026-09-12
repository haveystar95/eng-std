<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// THE SCHEDULE. Run by the `scheduler` compose service (`php artisan schedule:work`); nothing here
// fires while that service is down.
//
// Plan notifications (PLAN-UI-3): event-date facts and the daily reminder. Every 15 minutes because
// the reminder window is a quarter hour wide and the usual visit time is rounded to a quarter —
// exactly one tick lands in each window. Idempotent, so an overlap would be harmless; it is still
// kept from overlapping so a slow tick never races the next one over the same plans.
Schedule::command('plan:notify-tick')->everyFifteenMinutes()->withoutOverlapping();
