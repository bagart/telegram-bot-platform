<?php

declare(strict_types=1);

namespace Modules\Example;

use BAGArt\TelegramBot\Modules\TgModuleCapability;
use BAGArt\TelegramBot\Modules\TgModuleContract;
use BAGArt\TelegramBot\Modules\TgModuleDescriptor;
use BAGArt\TelegramBot\Modules\TgModuleRegistrar;
use BAGArt\TelegramBot\TgApi\Types\DTO\MessageTypeDTO;

/**
 * Demo local plugin: proves a module can be installed into modules/
 * and register processors without any core edits.
 */
class ExampleModule implements TgModuleContract
{
    public static function descriptor(): TgModuleDescriptor
    {
        return new TgModuleDescriptor(
            id: 'example',
            name: 'Example',
            version: '1.0.0',
            capabilities: [
                TgModuleCapability::Processor,
                TgModuleCapability::Rule,
                TgModuleCapability::Middleware,
                TgModuleCapability::Command,
            ],
            defaultEnabled: true,
        );
    }

    public static function register(TgModuleRegistrar $registrar): void
    {
        $registrar->processor(
            MessageTypeDTO::class,
            ExampleMessageProcessor::class,
        );

        $registrar->validationRule(
            ExampleValidationRule::class,
            weight: 30,
        );

        $registrar->outboundMiddleware(
            ExampleOutboundMiddleware::class,
        );

        $registrar->command(
            ExamplePingCommandProcessor::NAME,
            ExamplePingCommandProcessor::class,
        );
    }
}
