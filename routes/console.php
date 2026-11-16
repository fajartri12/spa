<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('reminders:send')->dailyAt('08:00')->withoutOverlapping()->onOneServer();

// Tagihan digital yang melewati masa berlaku ditandai kedaluwarsa agar daftar
// tagihan digital tidak menampilkan VA/QRIS yang sudah tidak dapat dibayar.
Schedule::command('gateway:expire-intents')->hourly()->withoutOverlapping()->onOneServer();

// Retensi data pribadi: bersihkan log lama sesuai kebijakan di model masing-masing.
Schedule::command('model:prune', ['--model' => [\App\Models\ActivityLog::class]])->dailyAt('01:00')->withoutOverlapping();
Schedule::command('model:prune', ['--model' => [\App\Models\NotificationLog::class]])->dailyAt('01:10')->withoutOverlapping();
