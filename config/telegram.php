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
];

// Local module discovery: each folder modules/<Name>/config.php must return
// ['provider' => TgModuleContract::class]. Composer-installed modules are
// listed in 'modules_providers' below. Both sources are booted by
// TelegramBotServiceProvider::bootModules().
$configTelegram['modules'] = (function (): array {
    $configTelegramModules = [];
    $configTelegramModulesPath = base_path().DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR;
    $paths = is_dir($configTelegramModulesPath) ? scandir($configTelegramModulesPath) : [];

    foreach ($paths as $moduleName) {
        if (in_array($moduleName, ['.', '..'])) {
            continue;
        }
        if (! is_dir($configTelegramModulesPath.$moduleName)) {
            continue;
        }
        $curConfigFile = $configTelegramModulesPath.$moduleName.DIRECTORY_SEPARATOR.'config.php';
        // is_file instead of is_readable: is_readable is unreliable on Windows drives
        if (! is_file($curConfigFile)) {
            continue;
        }
        $curConfig = include $curConfigFile;
        if (! $curConfig) {
            continue;
        }
        $configTelegramModules[$moduleName] = $curConfig;
    }

    return $configTelegramModules;
})();

// Composer-installed modules: list of TgModuleContract class-strings,
// published by packages into the app config.
$configTelegram['modules_providers'] = [];

$remap = ['log_channel'];
foreach ($configTelegram['modules'] as $name => $configTelegramModule) {
    foreach ($remap as $remapKey) {
        if (! array_key_exists($remapKey, $configTelegramModule)) {
            $configTelegram['modules'][$name][$remapKey] = $configTelegram[$remapKey] ?? null;
        }
    }
}

return $configTelegram;
