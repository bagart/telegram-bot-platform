<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// --- Telegram bot scheduled daemons ---
// Daemons spawn as detached processes via the named command below; per-daemon
// enablement, cron expressions and options live in config/telegram.php.

app()->booted(function (): void {
    $schedule = app(Schedule::class);

    $daemons = [
        'poll' => 'tg:poll-daemon',
        'queue_processor' => 'tg:queue-processor-daemon',
        'outbound' => 'tg:outbound-daemon',
    ];

    foreach ($daemons as $daemon => $event) {
        $expression = config("telegram.schedule.$daemon.expression", '* * * * *');
        if (!is_string($expression)) {
            continue;
        }

        $schedule->command('tg:spawn-daemon', ['daemon' => $daemon])
            ->cron($expression)
            ->name($event)
            ->withoutOverlapping();
    }

    // Module tasks arrive through telegram.modules_schedule (each module
    // registers its own commands from its service provider).
    (new \App\Console\ModuleTaskScheduler($schedule))->register();
});
