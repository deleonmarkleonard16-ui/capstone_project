<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('guidance:expire', function () {
    $this->info(app(\App\Services\GuidanceAssessmentSessionService::class)->expireDue().' overdue assessment(s) advanced or finalized.');
})->purpose('Advance expired guidance sections and finalize completed batteries');
Schedule::command('guidance:expire')->everyMinute()->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
