<?php

declare(strict_types=1);

namespace App\Config;

/**
 * Local Telegram module registry.
 *
 * Each entry must return ['provider' => TgModuleContract::class].
 * Composer-installed modules are listed separately in 'modules_providers'
 * (config/telegram.php); both sources are booted by
 * TelegramBotServiceProvider::bootModules().
 *
 * The registry is a literal map on purpose: dynamic include/require of
 * variable paths is forbidden by the security baseline (03 §27). Register a
 * new local module with one explicit line here, mirroring its provider in
 * bootstrap/providers.php.
 */
final class TgModulesDiscovery
{
    /**
     * @return array<string, mixed> module name → its config.php return value
     */
    public static function discover(): array
    {
        return [
            'Example' => require base_path('modules'.DIRECTORY_SEPARATOR.'Example'.DIRECTORY_SEPARATOR.'config.php'),
        ];
    }
}
