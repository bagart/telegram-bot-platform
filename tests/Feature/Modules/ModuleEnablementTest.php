<?php

declare(strict_types=1);

use BAGArt\TelegramBot\Configs\TgBotConfig;
use BAGArt\TelegramBot\Configs\TgServiceConfig;
use BAGArt\TelegramBot\Contracts\Modules\ModuleEnablementContract;
use BAGArt\TelegramBot\Modules\TgModuleRegistry;
use BAGArt\TelegramBot\Processing\RegisteredUpdateProcessorSelector;
use BAGArt\TelegramBot\TgApi\Types\DTO\ChatTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\MessageTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\Enum\ChatPropTypeEnum;
use BAGArt\TelegramBotManagement\Models\TgBot;
use BAGArt\TelegramBotManagement\Models\TgModuleEnablement;
use Illuminate\Support\Facades\DB;
use Modules\Example\ExampleMessageProcessor;

beforeEach(function () {
    config('telegram.modules');
    ExampleMessageProcessor::$receivedTexts = [];
    \Illuminate\Support\Facades\Cache::flush();
    TgBot::create(['bot_id' => 'bot_a', 'token' => 'a:token']);
    TgBot::create(['bot_id' => 'bot_b', 'token' => 'b:token']);
});

function enablementSelector(): RegisteredUpdateProcessorSelector
{
    $factory = app(BAGArt\TelegramBot\TgBotSetupFactory::class);

    return new RegisteredUpdateProcessorSelector(
        serviceConfig: new TgServiceConfig(),
        botSetup: $factory->create(serviceConfig: new TgServiceConfig()),
        moduleEnablement: app(ModuleEnablementContract::class),
    );
}

function enablementMessage(int $chatId): MessageTypeDTO
{
    return new MessageTypeDTO(
        messageId: 10,
        date: time(),
        chat: new ChatTypeDTO(id: (string)$chatId, type: ChatPropTypeEnum::GROUP),
        text: 'spam?',
    );
}

function exampleProcessorSelected(RegisteredUpdateProcessorSelector $selector, string $botId, int $chatId): bool
{
    $botConfig = new TgBotConfig(token: 'x:token', botId: $botId);

    foreach ($selector->selectProcessors(
        new BAGArt\TelegramBot\TgApi\Types\DTO\UpdateTypeDTO(updateId: 1, message: enablementMessage($chatId)),
        $botConfig,
    ) as $processors) {
        foreach ($processors as $processor) {
            if ($processor instanceof ExampleMessageProcessor) {
                return true;
            }
        }
    }

    return false;
}

it('executes the module processor when enabled (default from descriptor)', function () {
    expect(exampleProcessorSelected(enablementSelector(), 'bot_a', 100))->toBeTrue();
});

it('does not execute the module processor when disabled for the chat (AC-2)', function () {
    TgModuleEnablement::factory()->forChat('bot_a', 100)->enabled(false)->create();

    expect(exampleProcessorSelected(enablementSelector(), 'bot_a', 100))->toBeFalse();
    // other chat unaffected
    expect(exampleProcessorSelected(enablementSelector(), 'bot_a', 200))->toBeTrue();
});

it('bot enabled / chat disabled — chat loses in that chat (AC-3)', function () {
    TgModuleEnablement::factory()->forBot('bot_a')->enabled(true)->create();
    TgModuleEnablement::factory()->forChat('bot_a', 100)->enabled(false)->create();

    expect(exampleProcessorSelected(enablementSelector(), 'bot_a', 100))->toBeFalse();
    expect(exampleProcessorSelected(enablementSelector(), 'bot_a', 200))->toBeTrue();
});

it('bot disabled / chat enabled — chat override wins (AC-4)', function () {
    TgModuleEnablement::factory()->forBot('bot_a')->enabled(false)->create();
    TgModuleEnablement::factory()->forChat('bot_a', 100)->enabled(true)->create();

    expect(exampleProcessorSelected(enablementSelector(), 'bot_a', 100))->toBeTrue();
    expect(exampleProcessorSelected(enablementSelector(), 'bot_a', 200))->toBeFalse();
});

it('two bots have independent module state (AC-5)', function () {
    TgModuleEnablement::factory()->forChat('bot_a', 100)->enabled(false)->create();

    expect(exampleProcessorSelected(enablementSelector(), 'bot_a', 100))->toBeFalse();
    expect(exampleProcessorSelected(enablementSelector(), 'bot_b', 100))->toBeTrue();
});

it('platform row overrides descriptor default', function () {
    TgModuleEnablement::factory()->platform()->enabled(false)->create();

    expect(exampleProcessorSelected(enablementSelector(), 'bot_a', 100))->toBeFalse();
});

it('caches decisions: repeated isEnabled does not re-query (NFR-5)', function () {
    TgModuleEnablement::factory()->forChat('bot_a', 100)->enabled(false)->create();

    $enablement = app(ModuleEnablementContract::class);
    DB::enableQueryLog();

    expect($enablement->isEnabled('example', 'bot_a', 100))->toBeFalse();
    $queriesAfterFirst = count(DB::getQueryLog());

    expect($enablement->isEnabled('example', 'bot_a', 100))->toBeFalse();
    expect(count(DB::getQueryLog()))->toBe($queriesAfterFirst);
});

it('tg:module:disable writes a row and refreshes the cache', function () {
    $enablement = app(ModuleEnablementContract::class);
    expect($enablement->isEnabled('example', 'bot_a', 100))->toBeTrue();

    $this->artisan('tg:module:disable', ['module' => 'example', '--bot' => 'bot_a', '--chat' => '100'])
        ->assertSuccessful();

    expect($enablement->isEnabled('example', 'bot_a', 100))->toBeFalse();

    $this->artisan('tg:module:enable', ['module' => 'example', '--bot' => 'bot_a', '--chat' => '100'])
        ->assertSuccessful();

    expect($enablement->isEnabled('example', 'bot_a', 100))->toBeTrue();
});

it('tg:module:enable rejects undiscovered modules (R-9 guard)', function () {
    $this->artisan('tg:module:enable', ['module' => 'no-such-module', '--bot' => 'bot_a'])
        ->assertFailed();
});

it('keeps discovered module registry in sync', function () {
    expect(app(TgModuleRegistry::class)->has('example'))->toBeTrue();
});
