<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('tickets:no-show')->everyMinute()->withoutOverlapping();
Schedule::command('tickets:day:close')->everyMinute()->withoutOverlapping();
Schedule::command('tickets:purge')->dailyAt('03:00')->withoutOverlapping();
