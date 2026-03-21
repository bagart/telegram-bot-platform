<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// --- Telegram bot scheduled daemons ---

app()->booted(function (): void {
    $schedule = app(Schedule::class);

    $tgLibPath = base_path('misc/BAGArt/telegram-bot-lib');

    $schedule->call(function () use ($tgLibPath) {
        $poll = config('telegram.schedule.poll');
        if (empty($poll['enabled'])) {
            return;
        }

        $tokenArg = $poll['token'] ? ' --token='.escapeshellarg($poll['token']) : '';
        $extraOpts = $poll['options'] ? ' '.$poll['options'] : '';

        exec(
            PHP_BINARY.' '.escapeshellarg($tgLibPath.'/commands/poller-daemon.php').$tokenArg.$extraOpts.' > /dev/null 2>&1 &'
        );
    })->cron(config('telegram.schedule.poll.expression', '* * * * *'))
      ->name('tg:poll-daemon')
      ->withoutOverlapping();

    $schedule->call(function () use ($tgLibPath) {
        $qp = config('telegram.schedule.queue_processor');
        if (empty($qp['enabled'])) {
            return;
        }

        $opts = ' --redis-host='.escapeshellarg($qp['redis_host'])
            .' --redis-port='.escapeshellarg((string) $qp['redis_port'])
            .' --request-queue='.escapeshellarg($qp['request_queue']);
        $extraOpts = $qp['options'] ? ' '.$qp['options'] : '';

        exec(
            PHP_BINARY.' '.escapeshellarg($tgLibPath.'/commands/processor-daemon.php').$opts.$extraOpts.' > /dev/null 2>&1 &'
        );
    })->cron(config('telegram.schedule.queue_processor.expression', '* * * * *'))
      ->name('tg:queue-processor-daemon')
      ->withoutOverlapping();

    $schedule->call(function () use ($tgLibPath) {
        $ob = config('telegram.schedule.outbound');
        if (empty($ob['enabled'])) {
            return;
        }

        $opts = ' --redis-host='.escapeshellarg($ob['redis_host'])
            .' --redis-port='.escapeshellarg((string) $ob['redis_port'])
            .' --request-queue='.escapeshellarg($ob['request_queue']);
        $extraOpts = $ob['options'] ? ' '.$ob['options'] : '';

        exec(
            PHP_BINARY.' '.escapeshellarg($tgLibPath.'/commands/outbound-daemon.php').$opts.$extraOpts.' > /dev/null 2>&1 &'
        );
    })->cron(config('telegram.schedule.outbound.expression', '* * * * *'))
      ->name('tg:outbound-daemon')
      ->withoutOverlapping();
});
