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
];

$configTelegram['modules'] = (function (): array {
    $configTelegramModules = [];
    $configTelegramModulesPath = base_path().DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR.'TelegramBots'.DIRECTORY_SEPARATOR;
    $paths = scandir($configTelegramModulesPath);

    foreach ($paths as $moduleName) {
        if (in_array($moduleName, ['.', '..'])) {
            continue;
        }
        if (! is_dir($configTelegramModulesPath.$moduleName)) {
            continue;
        }
        $curConfigFile = $configTelegramModulesPath.$moduleName.DIRECTORY_SEPARATOR.'config.php';
        if (! is_readable($curConfigFile)) {
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

$remap = ['log_channel'];
foreach ($configTelegram['modules'] as $name => $configTelegramModule) {
    foreach ($remap as $remapKey) {
        if (! array_key_exists($remapKey, $configTelegramModule)) {
            $configTelegram['modules'][$name][$remapKey] = $configTelegram[$remapKey] ?? null;
        }
    }
}

return $configTelegram;
