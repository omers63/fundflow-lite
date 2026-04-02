<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Run daily at 08:00 to mark overdue loan installments and alert delinquent members.
Schedule::command('fund:check-delinquency')->dailyAt('08:00');

// 1st of each month at 09:00 — notify members that the previous month's contribution is due.
Schedule::command('contributions:notify')->monthlyOn(1, '09:00');

// 5th of each month at 09:00 — auto-apply contributions for the previous month.
Schedule::command('contributions:apply')->monthlyOn(5, '09:00');
