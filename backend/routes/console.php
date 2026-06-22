<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Generate due PM work orders daily at 06:00 (plant shift handover time)

// Send escalation reminders for overdue pending approval steps
Schedule::command('approval:send-escalation-reminders')->hourly();
