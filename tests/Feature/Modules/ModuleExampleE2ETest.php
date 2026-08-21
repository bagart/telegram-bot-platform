<?php

declare(strict_types=1);

use BAGArt\TelegramBot\Configs\TgBotConfig;
use BAGArt\TelegramBot\Configs\TgServiceConfig;
use BAGArt\TelegramBot\Modules\TgModuleRegistry;
use BAGArt\TelegramBot\Processing\RegisteredUpdateProcessorSelector;
use BAGArt\TelegramBot\TgApi\Types\DTO\ChatTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\MessageTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\UpdateTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\Enum\ChatPropTypeEnum;
use Modules\Example\ExampleMessageProcessor;

beforeEach(function () {
    config('telegram.modules'); // forces the module config scan (require_once of module sources)
    ExampleMessageProcessor::$receivedTexts = [];
});

function exampleUpdate(string $text): UpdateTypeDTO
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

it('discovers the local Example module on boot', function () {
    $registry = app(TgModuleRegistry::class);

    expect($registry->has('example'))->toBeTrue();
    expect($registry->get('example')->name)->toBe('Example');
});

it('delivers a webhook-shaped update to the module processor (AC-1)', function () {
    $factory = app(BAGArt\TelegramBot\TgBotSetupFactory::class);
    $botSetup = $factory->create(serviceConfig: new TgServiceConfig());

    $selector = new RegisteredUpdateProcessorSelector(
        serviceConfig: new TgServiceConfig(),
        botSetup: $botSetup,
    );

    $botConfig = new TgBotConfig(token: 'test:token', botId: 'test_bot');

    foreach ($selector->selectProcessors(exampleUpdate('hello module'), $botConfig) as $processors) {
        foreach ($processors as $processor) {
            /** @var BAGArt\TelegramBot\Contracts\Processing\Processors\TgTypeDTOProcessorContract $processor */
            $processor->process(exampleUpdate('hello module')->message, $botConfig, 'message');
        }
    }

    expect(ExampleMessageProcessor::$receivedTexts)->toBe(['hello module']);
});

it('shows the module in tg:modules:list', function () {
    $this->artisan('tg:modules:list')->assertSuccessful();
});
