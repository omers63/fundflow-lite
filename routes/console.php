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

// 1st of each month at 09:30 — notify active borrowers of upcoming repayment.
Schedule::command('loans:notify')->monthlyOn(1, '09:30');

// 5th of each month at 09:30 — auto-apply loan repayments.
Schedule::command('loans:apply')->monthlyOn(5, '09:30');

// 6th of each month — check for defaults (after repayment deadline).
Schedule::command('loans:check-defaults')->monthlyOn(6, '08:00');

// Daily — auto-settle loans whose conditions are fully met.
Schedule::command('loans:check-settlements')->dailyAt('10:00');

// Financial reconciliation snapshots (ledger integrity, controls, pipeline).
Schedule::command('fund:reconcile --daily')->dailyAt('06:20');
Schedule::command('fund:reconcile --monthly')->monthlyOn(2, '06:30');

// 3rd of each month at 08:00 — generate previous month's statements and email members.
Schedule::command('statements:generate --notify')->monthlyOn(3, '08:00');

// Daily at 06:00 — charge annual subscription fee to members on their join-date anniversary.
Schedule::call(function () {
    app(\App\Services\SubscriptionFeeService::class)->chargeAnniversaryFees(today());
})->dailyAt('06:00')->name('subscription:anniversary-fees')->withoutOverlapping();
