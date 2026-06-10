<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('housing:cron')->dailyAt('05:00');
Schedule::command('housing:auto-updation offer')->dailyAt('06:00');
Schedule::command('housing:auto-updation offer-ext')->dailyAt('06:05');
Schedule::command('housing:auto-updation license')->dailyAt('06:30');
Schedule::command('housing:auto-updation license-ext')->dailyAt('06:35');
Schedule::command('housing:auto-updation transfer')->monthlyOn(5, '10:00');
