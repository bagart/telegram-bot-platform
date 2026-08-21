<?php

declare(strict_types=1);

use BAGArt\TelegramBot\Contracts\Modules\ModuleSettingsContract;
use BAGArt\TelegramBotManagement\Models\TgBot;
use BAGArt\TelegramBotManagement\Models\TgModuleEnablement;

beforeEach(function () {
    config('telegram.modules');
    TgBot::create(['bot_id' => 'bot_a', 'token' => 'a:token']);
});

it('returns an empty settings map for a module without rows', function () {
    expect(app(ModuleSettingsContract::class)->settingsFor('example', 'bot_a', 100))->toBe([]);
});

it('resolves settings through the inheritance chain: platform → bot → chat', function () {
    TgModuleEnablement::factory()->platform()->create([
        'module_id' => 'example',
        'module_settings' => ['ping_reply' => 'platform-pong', 'shared' => 'platform'],
    ]);
    TgModuleEnablement::factory()->forBot('bot_a')->create([
        'module_id' => 'example',
        'module_settings' => ['ping_reply' => 'bot-pong'],
    ]);
    TgModuleEnablement::factory()->forChat('bot_a', 100)->create([
        'module_id' => 'example',
        'module_settings' => ['shared' => 'chat'],
    ]);

    $settings = app(ModuleSettingsContract::class)->settingsFor('example', 'bot_a', 100);

    // chat wins over bot/platform; bot wins over platform; untouched keys inherit
    expect($settings)->toBe([
        'ping_reply' => 'bot-pong',
        'shared' => 'chat',
    ]);
});

it('keeps enablement rows without settings out of the settings map', function () {
    TgModuleEnablement::factory()->forChat('bot_a', 100)->create([
        'module_id' => 'example',
        'module_settings' => null,
    ]);

    expect(app(ModuleSettingsContract::class)->settingsFor('example', 'bot_a', 100))->toBe([]);
});
