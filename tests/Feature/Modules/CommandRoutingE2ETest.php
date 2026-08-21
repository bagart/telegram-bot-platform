<?php

declare(strict_types=1);

use BAGArt\TelegramBot\Configs\TgBotConfig;
use BAGArt\TelegramBot\Configs\TgServiceConfig;
use BAGArt\TelegramBot\Modules\TgCommandRegistry;
use BAGArt\TelegramBot\Processing\RegisteredUpdateProcessorSelector;
use BAGArt\TelegramBot\TgApi\Types\DTO\ChatTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\MessageTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\UpdateTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\Enum\ChatPropTypeEnum;
use Modules\Example\ExampleMessageProcessor;
use Modules\Example\ExamplePingCommandProcessor;

beforeEach(function () {
    config('telegram.modules'); // forces the module config scan
    ExampleMessageProcessor::$receivedTexts = [];
    ExamplePingCommandProcessor::$invokedIn = [];
    ExamplePingCommandProcessor::$replied = [];
});

function commandTestUpdate(string $text): UpdateTypeDTO
{
    return new UpdateTypeDTO(
        updateId: 1,
        message: new MessageTypeDTO(
            messageId: 10,
            date: time(),
            chat: new ChatTypeDTO(id: '100', type: ChatPropTypeEnum::GROUP),
            text: $text,
        ),
    );
}

function commandTestSelector(): RegisteredUpdateProcessorSelector
{
    $factory = app(BAGArt\TelegramBot\TgBotSetupFactory::class);
    $botSetup = $factory->create(serviceConfig: new TgServiceConfig());

    return new RegisteredUpdateProcessorSelector(
        serviceConfig: new TgServiceConfig(),
        botSetup: $botSetup,
    );
}

it('Example module registers the /example_ping command', function () {
    expect(app(TgCommandRegistry::class)->processorOf('example_ping'))
        ->toBe(ExamplePingCommandProcessor::class);
});

it('routes /example_ping exclusively to the command processor (webhook shape, e2e)', function () {
    $selector = commandTestSelector();
    $botConfig = new TgBotConfig(token: 'test:token', botId: 'test_bot');
    $update = commandTestUpdate('/example_ping');

    $ran = [];
    foreach ($selector->selectProcessors($update, $botConfig) as $processors) {
        foreach ($processors as $processor) {
            $ran[] = $processor::class;
            $processor->process($update->message, $botConfig, 'message');
        }
    }

    // the command intercepted the update: no regular message processors ran
    expect($ran)->toBe([ExamplePingCommandProcessor::class]);
    expect(ExamplePingCommandProcessor::$invokedIn)->toBe(['100']);
    expect(ExampleMessageProcessor::$receivedTexts)->toBe([]);
});

it('a non-command message still reaches regular processors', function () {
    $selector = commandTestSelector();
    $botConfig = new TgBotConfig(token: 'test:token', botId: 'test_bot');
    $update = commandTestUpdate('hello module');

    foreach ($selector->selectProcessors($update, $botConfig) as $processors) {
        foreach ($processors as $processor) {
            $processor->process($update->message, $botConfig, 'message');
        }
    }

    expect(ExampleMessageProcessor::$receivedTexts)->toBe(['hello module']);
    expect(ExamplePingCommandProcessor::$invokedIn)->toBe([]);
});

it('an unknown command falls back to the regular message flow', function () {
    $selector = commandTestSelector();
    $botConfig = new TgBotConfig(token: 'test:token', botId: 'test_bot');
    $update = commandTestUpdate('/unknown_command');

    foreach ($selector->selectProcessors($update, $botConfig) as $processors) {
        foreach ($processors as $processor) {
            $processor->process($update->message, $botConfig, 'message');
        }
    }

    expect(ExamplePingCommandProcessor::$invokedIn)->toBe([]);
    // regular processors still observed the message
    expect(ExampleMessageProcessor::$receivedTexts)->toBe(['/unknown_command']);
});

it('answers /example_ping with the module_settings-configured reply text', function () {
    \BAGArt\TelegramBotManagement\Models\TgBot::create(['bot_id' => 'test_bot', 'token' => 'test:token']);
    \BAGArt\TelegramBotManagement\Models\TgModuleEnablement::factory()
        ->forChat('test_bot', 100)
        ->create([
            'module_id' => 'example',
            'module_settings' => ['ping_reply' => 'pong-custom'],
        ]);

    $selector = commandTestSelector();
    $botConfig = new TgBotConfig(token: 'test:token', botId: 'test_bot');
    $update = commandTestUpdate('/example_ping');

    foreach ($selector->selectProcessors($update, $botConfig) as $processors) {
        foreach ($processors as $processor) {
            $processor->process($update->message, $botConfig, 'message');
        }
    }

    expect(ExamplePingCommandProcessor::$invokedIn)->toBe(['100']);
    expect(ExamplePingCommandProcessor::$replied)->toBe(['pong-custom']);
});

it('does not execute the command when the module is disabled for the chat', function () {
    \BAGArt\TelegramBotManagement\Models\TgBot::create(['bot_id' => 'test_bot', 'token' => 'test:token']);
    $botConfig = new TgBotConfig(token: 'test:token', botId: 'test_bot');

    \BAGArt\TelegramBotManagement\Models\TgModuleEnablement::factory()
        ->forChat('test_bot', 100)
        ->enabled(false)
        ->create(['module_id' => 'example']);

    $factory = app(BAGArt\TelegramBot\TgBotSetupFactory::class);
    $botSetup = $factory->create(serviceConfig: new TgServiceConfig());
    $selector = new RegisteredUpdateProcessorSelector(
        serviceConfig: new TgServiceConfig(),
        botSetup: $botSetup,
        moduleEnablement: app(\BAGArt\TelegramBot\Contracts\Modules\ModuleEnablementContract::class),
    );

    $update = commandTestUpdate('/example_ping');

    foreach ($selector->selectProcessors($update, $botConfig) as $processors) {
        foreach ($processors as $processor) {
            $processor->process($update->message, $botConfig, 'message');
        }
    }

    expect(ExamplePingCommandProcessor::$invokedIn)->toBe([]);
    expect(ExampleMessageProcessor::$receivedTexts)->toBe([]);
});
