<?php

$configTelegram = [
    /*
    |--------------------------------------------------------------------------
    | Log Channel
    |--------------------------------------------------------------------------
    |
    | The log channel used for Telegram bot activity.
    | Use "null" to disable logging, "stdout" to write to standard output,
    | or any other channel defined in config/logging.php (e.g. "daily").
    |
    */
    'log_channel' => env('TELEGRAM_LOG_CHANNEL'),

    'debug' => false,

    // Opt-in only for local/staging load runs: loopback/private webhook
    // sources skip the Telegram CIDR allowlist. Keep off in production.
    'webhook_allow_local_ips' => (bool) env('TG_WEBHOOK_ALLOW_LOCAL_IPS', false),

    /*
    |--------------------------------------------------------------------------
    | Polling Defaults
    |--------------------------------------------------------------------------
    |
    | Default parameters for long-polling via the telegram:poll command.
    | "timeout" is the server-side long-polling duration in seconds.
    | "limit" controls the maximum number of updates per request (1–100).
    |
    */
    'polling' => [
        'timeout' => (int) env('TELEGRAM_POLLING_TIMEOUT', 30),
        'limit' => (int) env('TELEGRAM_POLLING_LIMIT', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Transport
    |--------------------------------------------------------------------------
    |
    | The HTTP transport used for Telegram Bot API requests.
    | Available transports: guzzle (Guzzle CurlMulti async), curl-multi (async),
    | ask-socket (non-blocking socket, supports keep-alive pooling).
    |
    */
    'transport' => env('TELEGRAM_TRANSPORT', 'guzzle'),

    /*
    |--------------------------------------------------------------------------
    | Poller
    |--------------------------------------------------------------------------
    |
    | Default tg_daemons type for long-polling commands.
    | Available: async, sync, async-scheduler.
    |
    */
    'tg_daemons' => env('TELEGRAM_POLLER', 'async'),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiter
    |--------------------------------------------------------------------------
    |
    | Rate limiter used for Telegram Bot API requests.
    | Available: null (none), "basic" (cache-based), "advanced" (PDO-based).
    |
    */
    'rate_limiter' => env('TELEGRAM_RATE_LIMITER', 'basic'),

    /*
    |--------------------------------------------------------------------------
    | Schedule — Poller
    |--------------------------------------------------------------------------
    |
    | Automatic long-polling schedule via Laravel's task scheduler.
    | Set SCHEDULE_TG_POLL_ENABLED=false to disable.
    |
    | The tg_daemons runs as a daemon via `php commands/tg_daemons-daemon.php`.
    | Expression follows Laravel's cron format (default: every minute).
    |
    */
    'schedule' => [
        'poll' => [
            'enabled' => (bool) env('SCHEDULE_TG_POLL_ENABLED', false),
            'expression' => env('SCHEDULE_TG_POLL_EXPRESSION', '* * * * *'),
            'token' => env('SCHEDULE_TG_POLL_TOKEN'),
            'options' => env('SCHEDULE_TG_POLL_OPTIONS', ''),
        ],

        /*
        |--------------------------------------------------------------------------
        | Schedule — Queue Processor
        |--------------------------------------------------------------------------
        |
        | Redis queue processor daemon for DTO processing jobs.
        | Set SCHEDULE_TG_QUEUE_PROCESSOR_ENABLED=false to disable.
        |
        */
        'queue_processor' => [
            'enabled' => (bool) env('SCHEDULE_TG_QUEUE_PROCESSOR_ENABLED', false),
            'expression' => env('SCHEDULE_TG_QUEUE_PROCESSOR_EXPRESSION', '* * * * *'),
            'redis_host' => env('SCHEDULE_TG_QUEUE_PROCESSOR_REDIS_HOST', '127.0.0.1'),
            'redis_port' => (int) env('SCHEDULE_TG_QUEUE_PROCESSOR_REDIS_PORT', 6379),
            'request_queue' => env('SCHEDULE_TG_QUEUE_PROCESSOR_REQUEST_QUEUE', 'tg-processor-jobs'),
            'options' => env('SCHEDULE_TG_QUEUE_PROCESSOR_OPTIONS', ''),
        ],

        /*
        |--------------------------------------------------------------------------
        | Schedule — Outbound Daemon
        |--------------------------------------------------------------------------
        |
        | Redis queue consumer for outbound Telegram API requests.
        | Set SCHEDULE_TG_OUTBOUND_ENABLED=false to disable.
        |
        */
        'outbound' => [
            'enabled' => (bool) env('SCHEDULE_TG_OUTBOUND_ENABLED', false),
            'expression' => env('SCHEDULE_TG_OUTBOUND_EXPRESSION', '* * * * *'),
            'redis_host' => env('SCHEDULE_TG_OUTBOUND_REDIS_HOST', '127.0.0.1'),
            'redis_port' => (int) env('SCHEDULE_TG_OUTBOUND_REDIS_PORT', 6379),
            'request_queue' => env('SCHEDULE_TG_OUTBOUND_REQUEST_QUEUE', 'tg-outbound-requests'),
            'options' => env('SCHEDULE_TG_OUTBOUND_OPTIONS', ''),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Schedule — Summarizer digests
    |--------------------------------------------------------------------------
    |
    | Cron that scans enabled chats and produces due digests (command provided
    | by bagart/telegram-bot-summarizer-module). Module runtime settings live
    | in the module's own summarizer.php config.
    | Set SCHEDULE_SUMMARIZER_ENABLED=false to disable.
    |
    */
    'schedule_summarizer_enabled' => (bool) env('SCHEDULE_SUMMARIZER_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Schedule — STT prune
    |--------------------------------------------------------------------------
    |
    | Nightly retention sweep for the stt module (command provided by
    | bagart/telegram-bot-stt-module). Module runtime settings live in the
    | module's own stt.php config. Set SCHEDULE_STT_PRUNE_ENABLED=false to disable.
    |
    */
    'schedule_stt_prune_enabled' => (bool) env('SCHEDULE_STT_PRUNE_ENABLED', true),
];

// Local module discovery: each folder modules/<Name>/config.php must return
// ['provider' => TgModuleContract::class]. Composer-installed modules are
// listed in 'modules_providers' below. Both sources are booted by
// TelegramBotServiceProvider::bootModules().
$configTelegram['modules'] = \App\Config\TgModulesDiscovery::discover();

// Composer-installed modules: list of TgModuleContract class-strings.
// Empty by default — module packages self-register their provider class from
// their Laravel service providers (see bootstrap/providers.php).
$configTelegram['modules_providers'] = [];

// Module database seeders: list of seeder class-strings. Empty by default —
// module packages self-register their seeders from their Laravel service
// providers. DatabaseSeeder consumes this list; the host never references
// module seeder classes directly.
$configTelegram['modules_seeders'] = [];

// Module Inertia page directories: list of absolute paths. Empty by default —
// module packages self-register their `resources/js/pages` dir from their
// Laravel service providers. `php artisan tgapp:pages` consumes this list and
// (re)generates resources/js/modules-pages.generated.ts; the host frontend
// never references module page paths directly.
$configTelegram['modules_frontend_pages'] = [];

// Module scheduled tasks: list of ['command', 'expression', 'enabled'] maps.
// Empty by default — module packages self-register their commands from their
// Laravel service providers. routes/console.php consumes this list; the host
// never hardcodes module command names. Per-task user overrides (expression /
// disabled) live in config/schedule-overrides.php.
$configTelegram['modules_schedule'] = [];

$remap = ['log_channel'];
foreach ($configTelegram['modules'] as $name => $configTelegramModule) {
    foreach ($remap as $remapKey) {
        if (! array_key_exists($remapKey, $configTelegramModule)) {
            $configTelegram['modules'][$name][$remapKey] = $configTelegram[$remapKey] ?? null;
        }
    }
}

return $configTelegram;
