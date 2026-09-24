<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Expired mobile-app tokens (atompay_personal_access_tokens) are dead weight.
Schedule::command('sanctum:prune-expired --hours=24')->daily();

// Limit decisions, KYC outcomes and instalment reminders -> inbox + push.
// Idempotent (dedupe keys), so a missed or doubled run is harmless.
Schedule::command('atompay:notify')->everyTenMinutes()->withoutOverlapping();
