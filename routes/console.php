<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Illuminate\Support\Facades\Schedule::command('app:sync-purchase-orders')->hourly();
Illuminate\Support\Facades\Schedule::command('stock:monthly-check')->everyMinute()->timezone('Asia/Bangkok');
//Schedule::command('stock:check-expiration')->dailyAt('01:00');

// ✅ ลบ notifications เก่าอัตโนมัติ ทุกวันตอนตี 2
Schedule::command('notifications:prune --days=30 --read-days=7')
    ->dailyAt('02:00')
    ->timezone('Asia/Bangkok')
    ->description('ลบ notifications เก่า');
