<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('sessions:expire-log', function () {
    $count = app(\App\Services\AuthenticationSessionService::class)->expireDue();
    $this->info($count.' sesi kedaluwarsa dicatat.');
})->purpose('Catat logout otomatis pada waktu sesi berakhir, termasuk browser yang sudah ditutup.');

Schedule::command('sessions:expire-log')->everyMinute()->withoutOverlapping();
