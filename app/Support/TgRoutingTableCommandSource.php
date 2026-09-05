<?php

namespace App\Support;

use BAGArt\TelegramBotMenu\Contracts\BotCommandSourceContract;
use BAGArt\TelegramBotMenu\Support\DeclaredBotCommand;
use BAGArt\TelegramModuleEngine\Routing\RouteEntry;
use BAGArt\TelegramModuleEngine\Routing\RouteResolver;
use BAGArt\TelegramModuleEngine\Tenancy\BotContext;

/**
 * §19 host wiring for `tg:menu:sync` (menu_integration.md M-1): adapts the
 * engine's per-bot routing table — already enablement-filtered, disabled
 * modules never appear — into the menu module's pure command source. Route
 * descriptions are declared in config/tg_modules.php `description` payload
 * keys.
 */
final readonly class TgRoutingTableCommandSource implements BotCommandSourceContract
{
    public function __construct(
        private RouteResolver $routes,
    ) {
    }

    public function commandEntries(string $botId): array
    {
        $table = $this->routes->resolve(BotContext::forBot($botId));

        return array_values(array_map(
            static fn (RouteEntry $entry): DeclaredBotCommand => new DeclaredBotCommand(
                command: $entry->entryKey,
                moduleId: $entry->moduleId,
                description: is_array($entry->payload)
                    && is_string($entry->payload['description'] ?? null)
                    && $entry->payload['description'] !== ''
                    ? $entry->payload['description']
                    : null,
            ),
            $table->entries,
        ));
    }
}
