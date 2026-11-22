<?php

use App\Services\Outreach\OutreachService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('payments:generate', function (OutreachService $outreach) {
    $this->info($outreach->generateMaintenancePayments().' bakım ödemesi oluşturuldu.');
})->purpose('Aktif satışlar için bu ayın bakım ödemelerini oluşturur');

// Paylaşımlı hosting: cron her dakika `php artisan schedule:run` çalıştırır.
// Kuyruk işçisi her dakika en fazla ~55 sn çalışıp kapanır (kalıcı daemon gerekmez).
Schedule::command('queue:work --stop-when-empty --max-time=55 --tries=3 --timeout=50')
    ->everyMinute()
    ->withoutOverlapping(5);

Schedule::command('payments:generate')->dailyAt('03:10');

// Cron'un çalıştığını panelden ve `leadgen:check` ile görebilmek için kalp atışı
Schedule::call(fn () => Cache::put('scheduler.heartbeat', now(), now()->addDay()))
    ->everyMinute()
    ->name('scheduler-heartbeat');
