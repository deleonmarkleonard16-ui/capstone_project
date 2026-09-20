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

Artisan::command('user:create {email=admin@psu-scc.test} {password=password} {role=admin}', function (string $email, string $password, string $role) {
    $user = \App\Models\User::updateOrCreate(
        ['email' => $email],
        [
            'name' => ucfirst($role).' User',
            'password' => \Illuminate\Support\Facades\Hash::make($password),
            'role' => $role,
            'is_active' => true,
        ]
    );
    $this->info("User [{$user->email}] with role [{$user->role}] is ready (active: yes).");
})->purpose('Create or reset an administrator or staff account');

