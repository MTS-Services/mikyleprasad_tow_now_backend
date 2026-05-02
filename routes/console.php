<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('currency:sync-rates')
    ->dailyAt('00:00')
    ->when(fn (): bool => (bool) config('currency.fx_sync.enabled'));
