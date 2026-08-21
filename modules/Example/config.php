<?php

declare(strict_types=1);

/**
 * Local module manifest. Scanned by config/telegram.php and booted by
 * TelegramBotServiceProvider::bootModules(). Standalone by design: the
 * module owns its sources and requires no composer autoload changes.
 */

require_once __DIR__.'/src/ExampleMessageProcessor.php';
require_once __DIR__.'/src/ExampleModule.php';
require_once __DIR__.'/src/ExampleOutboundMiddleware.php';
require_once __DIR__.'/src/ExamplePingCommandProcessor.php';
require_once __DIR__.'/src/ExampleValidationRule.php';

return [
    'provider' => \Modules\Example\ExampleModule::class,
];
