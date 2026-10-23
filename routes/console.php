<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Overlap locks expire quickly so a crashed run cannot silence alerts for the default 24 hours.
Schedule::command('licenses:check-offline')->everyMinute()->withoutOverlapping(5);
Schedule::command('licenses:check-expiring')->hourly()->withoutOverlapping(30);
