<?php

declare(strict_types=1);

use BAGArt\TelegramBot\Contracts\Modules\ModuleEnablementContract;
use BAGArt\TelegramBot\Contracts\Modules\ModuleSettingsContract;
use BAGArt\TelegramBotTts\Settings\TtsSettingsService;
use BAGArt\TelegramModuleEngine\Activation\ModuleActivationReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Engine-mode seam for the settings contract (docs/questions/module-suite-engine-pinning.md,
 * Decision): the module suites pin the legacy driver, so this lane — root
 * Feature with enablement_driver='engine' and the prod schema (legacy table
 * dropped) — is the only host coverage of EngineSettingsAdapter and of a real
 * module write path round-tripping through ModuleSettingsContract.
 */

function engineModeSeedTts(string $botId, array $settings = []): void
{
    DB::table('bot_module_activations')->insert([
        'bot_id' => $botId,
        'module_id' => 'tts',
        'status' => ModuleActivationReader::STATUS_ENABLED,
        'revision' => 1,
        'module_settings' => json_encode($settings, JSON_THROW_ON_ERROR),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('runs on the prod schema without the legacy enablement table', function () {
    expect(Schema::hasTable('tg_module_enablements'))->toBeFalse();
});

it('merges chat overrides over bot-level settings under the engine driver', function () {
    engineModeSeedTts('bot-engine-a', [
        'voice' => 'google',
        'rate' => 1.5,
        '777:voice' => 'custom',
        '777:__enabled__' => false,
    ]);

    $settings = app(ModuleSettingsContract::class);

    expect($settings->settingsFor('tts', 'bot-engine-a'))
        ->toHaveKey('voice', 'google')
        ->toHaveKey('rate', 1.5)
        ->not->toHaveKey('__enabled__');

    expect($settings->settingsFor('tts', 'bot-engine-a', 777))
        ->toHaveKey('voice', 'custom')
        ->toHaveKey('rate', 1.5)
        ->not->toHaveKey('enabled')
        ->not->toHaveKey('__enabled__');

    expect($settings->settingsFor('tts', 'bot-engine-a', 999))
        ->toHaveKey('voice', 'google')
        ->toHaveKey('rate', 1.5);
});

it('round-trips a module write path through the contract under the engine driver', function () {
    engineModeSeedTts('bot-engine-b');

    $service = app(TtsSettingsService::class);
    $settings = app(ModuleSettingsContract::class);
    $enablement = app(ModuleEnablementContract::class);

    $enablement->refresh('bot-engine-b', 555);
    expect($service->isEnabled('bot-engine-b', 555))->toBeTrue();

    $service->patch('bot-engine-b', 555, ['voice' => 'nova', 'enabled' => false]);

    expect($settings->settingsFor('tts', 'bot-engine-b', 555))
        ->toHaveKey('voice', 'nova')
        ->not->toHaveKey('enabled');

    $enablement->refresh('bot-engine-b', 555);
    expect($service->isEnabled('bot-engine-b', 555))->toBeFalse();

    $service->patch('bot-engine-b', 555, ['enabled' => true]);
    $enablement->refresh('bot-engine-b', 555);
    expect($service->isEnabled('bot-engine-b', 555))->toBeTrue();

    $service->patch('bot-engine-b', 555, ['voice' => null]);
    expect($settings->settingsFor('tts', 'bot-engine-b', 555))->not->toHaveKey('voice');

    $scopes = $settings->chatsWithSettings('tts', 'bot-engine-b');
    $chatScopes = array_values(array_filter($scopes, fn (array $scope): bool => $scope['chatId'] !== null));

    expect($chatScopes)->toHaveCount(1)
        ->and($chatScopes[0]['chatId'])->toBe(555)
        ->and(end($scopes)['chatId'])->toBeNull();
});
