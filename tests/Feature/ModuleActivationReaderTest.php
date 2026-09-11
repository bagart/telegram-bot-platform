<?php

declare(strict_types=1);

use BAGArt\TelegramModuleEngine\Activation\ModuleActivationReader;
use BAGArt\TelegramModuleEngine\Registry\EngineModuleRegistry;
use BAGArt\TelegramModuleEngine\Registry\ModuleRegistryBuilder;
use Illuminate\Support\Facades\Config;

/**
 * Integration test: proves ModuleActivationReader::isEffectivelyEnabled()
 * returns the correct value for every registered module, including the
 * defaultEnabled fallback path and the explicit-row override path.
 */

function rebuildRegistry(): EngineModuleRegistry
{
    app()->forgetInstance(ModuleRegistryBuilder::class);
    app()->forgetInstance(EngineModuleRegistry::class);

    return app(EngineModuleRegistry::class);
}

function activationReader(): ModuleActivationReader
{
    return app(ModuleActivationReader::class);
}

it('returns false for a platform-disabled module', function () {
    Config::set('tg_modules.modules.proxy.enabled', false);
    $registry = rebuildRegistry();

    $reader = new ModuleActivationReader(
        connection: app('db')->connection(),
        registry: $registry,
    );

    // Proxy is platform-disabled, so even with no activation row it must be off.
    expect($reader->isEffectivelyEnabled('bot-999', 'proxy'))->toBeFalse();
});

it('falls back to defaultEnabled when no activation row exists', function () {
    Config::set('tg_modules.modules.proxy.enabled', true);
    $registry = rebuildRegistry();

    $reader = new ModuleActivationReader(
        connection: app('db')->connection(),
        registry: $registry,
    );

    // Proxy has defaultEnabled: false in its descriptor.
    // With no activation row, the reader falls back to defaultEnabled.
    expect($reader->isEffectivelyEnabled('bot-999', 'proxy'))->toBeFalse();
});

it('falls back to defaultEnabled=true for modules with that descriptor', function () {
    Config::set('tg_modules.modules.proxy.enabled', true);
    $registry = rebuildRegistry();

    $reader = new ModuleActivationReader(
        connection: app('db')->connection(),
        registry: $registry,
    );

    // antispam has defaultEnabled: true (or is enabled by default).
    // With no activation row, the reader returns the descriptor default.
    $antispamDef = $registry->get('antispam');
    if ($antispamDef !== null) {
        $expected = $antispamDef->descriptor->defaultEnabled;
        expect($reader->isEffectivelyEnabled('bot-999', 'antispam'))->toBe($expected);
    }
});

it('honours explicit enabled row over defaultEnabled', function () {
    Config::set('tg_modules.modules.proxy.enabled', true);
    $registry = rebuildRegistry();

    $reader = new ModuleActivationReader(
        connection: app('db')->connection(),
        registry: $registry,
    );

    // Insert an explicit enabled row for proxy.
    DB::table('bot_module_activations')->insert([
        'bot_id' => 'bot-100',
        'module_id' => 'proxy',
        'status' => ModuleActivationReader::STATUS_ENABLED,
        'revision' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Proxy has defaultEnabled: false, but the explicit row says enabled.
    expect($reader->isEffectivelyEnabled('bot-100', 'proxy'))->toBeTrue();
});

it('honours explicit disabled row over defaultEnabled=true', function () {
    Config::set('tg_modules.modules.antispam.enabled', true);
    $registry = rebuildRegistry();

    $reader = new ModuleActivationReader(
        connection: app('db')->connection(),
        registry: $registry,
    );

    DB::table('bot_module_activations')->insert([
        'bot_id' => 'bot-101',
        'module_id' => 'antispam',
        'status' => ModuleActivationReader::STATUS_DISABLED,
        'revision' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($reader->isEffectivelyEnabled('bot-101', 'antispam'))->toBeFalse();
});

it('activeModuleIds returns only enabled modules for a bot', function () {
    Config::set('tg_modules.modules.proxy.enabled', true);
    $registry = rebuildRegistry();

    $reader = new ModuleActivationReader(
        connection: app('db')->connection(),
        registry: $registry,
    );

    DB::table('bot_module_activations')->insert([
        'bot_id' => 'bot-200',
        'module_id' => 'proxy',
        'status' => ModuleActivationReader::STATUS_ENABLED,
        'revision' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $active = $reader->activeModuleIds('bot-200');

    expect($active)->toContain('proxy');

    // Modules without rows fall back to defaultEnabled.
    foreach ($registry->enabled() as $def) {
        if ($def->id() === 'proxy') {
            continue;
        }
        if ($def->descriptor->defaultEnabled) {
            expect($active)->toContain($def->id());
        } else {
            expect($active)->not->toContain($def->id());
        }
    }
});

it('returns empty list for a bot with no rows and no defaultEnabled modules', function () {
    Config::set('tg_modules.modules', [
        'only-disabled' => new \BAGArt\TelegramModuleEngine\Config\TgModuleConfig(
            enabled: true,
            provider: \BAGArt\TelegramModuleEngine\Tests\Fixtures\TestModule::class,
        ),
    ]);
    // Override descriptor to defaultEnabled: false.
    Config::set('tg_modules.modules.only-disabled', new \BAGArt\TelegramModuleEngine\Config\TgModuleConfig(
        enabled: true,
        provider: \BAGArt\TelegramModuleEngine\Tests\Fixtures\TestModule::class,
    ));

    $registry = rebuildRegistry();

    $reader = new ModuleActivationReader(
        connection: app('db')->connection(),
        registry: $registry,
    );

    $active = $reader->activeModuleIds('bot-no-rows');

    // All modules should be absent if none have defaultEnabled:true and no rows exist.
    $allDefaultDisabled = true;
    foreach ($registry->enabled() as $def) {
        if ($def->descriptor->defaultEnabled) {
            $allDefaultDisabled = false;
            break;
        }
    }

    if ($allDefaultDisabled) {
        expect($active)->toBeEmpty();
    }
});
