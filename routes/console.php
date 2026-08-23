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
        if (! is_string($expression)) {
            continue;
        }

        $schedule->command('tg:spawn-daemon', ['daemon' => $daemon])
            ->cron($expression)
            ->name($event)
            ->withoutOverlapping();
    }

    $schedule->command('summarizer:digests')
        ->cron(config('telegram.schedule.poll.expression', '* * * * *'))
        ->name('summarizer:digests')
        ->withoutOverlapping()
        ->when(fn (): bool => (bool) config('telegram.schedule_summarizer_enabled', true));

    $schedule->command('stt:prune')
        ->daily()
        ->withoutOverlapping()
        ->when(fn (): bool => (bool) config('telegram.schedule_stt_prune_enabled', true));

    $schedule->command('tts:prune')
        ->daily()
        ->withoutOverlapping()
        ->when(fn (): bool => (bool) config('tts.schedule_prune_enabled', true));
});
