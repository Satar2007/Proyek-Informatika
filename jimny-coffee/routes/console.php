<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// JIMNY_INTELLIGENCE_360: scheduled snapshot; scheduler must be running.
\Illuminate\Support\Facades\Schedule::command('jimny:intelligence-refresh')
    ->dailyAt('02:15')->withoutOverlapping(15);
