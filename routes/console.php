<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Illuminate\Support\Facades\Schedule::command('app:sync-purchase-orders')->hourly();
Illuminate\Support\Facades\Schedule::command('stock:monthly-check')->everyMinute()->timezone('Asia/Bangkok');
//Schedule::command('stock:check-expiration')->dailyAt('01:00');