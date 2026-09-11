<?php

declare(strict_types=1);

use BAGArt\TelegramModuleEngine\Registry\EngineModuleRegistry;
use BAGArt\TelegramModuleEngine\Registry\ModuleRegistryBuilder;
use Illuminate\Support\Facades\Config;

/**
 * Host shim for module-owned frontend tooling: `modules:pages` iterates the
 * EngineModuleRegistry::pageGenerators() so package.json / CI never name
 * a module command directly.
 */
test('modules:pages delegates to every registered generator command', function () {
    Config::set('tg_modules.modules', []);
    app()->forgetInstance(ModuleRegistryBuilder::class);
    app()->forgetInstance(EngineModuleRegistry::class);

    // Register two fake generator commands via the config that the builder reads.
    $config = Config::get('tg_modules.modules', []);
    $config['fake-a'] = new \BAGArt\TelegramModuleEngine\Config\TgModuleConfig(
        enabled: true,
        provider: \BAGArt\TelegramModuleEngine\Tests\Fixtures\TestModule::class,
        pageGenerators: ['fake:gen-a'],
    );
    $config['fake-b'] = new \BAGArt\TelegramModuleEngine\Config\TgModuleConfig(
        enabled: true,
        provider: \BAGArt\TelegramModuleEngine\Tests\Fixtures\TestModule::class,
        pageGenerators: ['fake:gen-b'],
    );
    Config::set('tg_modules.modules', $config);

    $registry = app(EngineModuleRegistry::class);

    Artisan::command('fake:gen-a {--output= : path}', function (): int {
        config(['test.gen-a-called' => true]);

        return 0;
    });
    Artisan::command('fake:gen-b {--output= : path}', function (): int {
        config(['test.gen-b-called' => true]);

        return 0;
    });

    $this->artisan('modules:pages')->assertExitCode(0);

    expect(config('test.gen-a-called'))->toBeTrue()
        ->and(config('test.gen-b-called'))->toBeTrue();
});

test('modules:pages succeeds without registered generators', function () {
    Config::set('tg_modules.modules', []);
    app()->forgetInstance(ModuleRegistryBuilder::class);
    app()->forgetInstance(EngineModuleRegistry::class);

    $this->artisan('modules:pages')->assertExitCode(0);
});

test('menu module registers its generator into the registry on boot', function () {
    $registry = app(EngineModuleRegistry::class);
    $generators = $registry->pageGenerators();

    expect($generators)->toContain('menu:pages');
});
