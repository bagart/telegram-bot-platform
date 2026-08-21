<?php

declare(strict_types=1);

use BAGArt\TelegramBot\Modules\TgModuleDescriptor;
use BAGArt\TelegramBot\Modules\TgModuleRegistry;
use BAGArt\TelegramBotManagement\Models\TgBot;
use BAGArt\TelegramBotManagement\Models\TgModuleEnablement;

beforeEach(function () {
    config('telegram.modules');
    TgBot::create(['bot_id' => 'bot_a', 'token' => 'a:token']);
});

it('reports a healthy module configuration', function () {
    TgModuleEnablement::factory()->forChat('bot_a', 100)->enabled(false)->create();

    $this->artisan('tg:modules:doctor')->assertSuccessful();
});

it('fails and reports enablement rows referencing undiscovered modules (R-9)', function () {
    TgModuleEnablement::factory()->forChat('bot_a', 100)->create(['module_id' => 'ghost']);
    TgModuleEnablement::factory()->platform()->create(['module_id' => 'removed-module']);

    $this->artisan('tg:modules:doctor')
        ->expectsOutputToContain('ghost')
        ->expectsOutputToContain('removed-module')
        ->assertFailed();
});

it('includes discovered module ids in the check', function () {
    expect(app(TgModuleRegistry::class)->has('example'))->toBeTrue();

    $this->artisan('tg:modules:doctor')->assertSuccessful();
});

it('accepts satisfied versioned requirements (Q7)', function () {
    app(TgModuleRegistry::class)->add(new TgModuleDescriptor(
        id: 'dep_consumer',
        name: 'DepConsumer',
        version: '1.0.0',
        requiresModules: ['example@>=0.1'],
    ));

    $this->artisan('tg:modules:doctor')->assertSuccessful();
});

it('fails on a missing requirement (Q7)', function () {
    app(TgModuleRegistry::class)->add(new TgModuleDescriptor(
        id: 'dep_consumer',
        name: 'DepConsumer',
        version: '1.0.0',
        requiresModules: ['not_discovered'],
    ));

    $this->artisan('tg:modules:doctor')
        ->expectsOutputToContain("Module 'dep_consumer' requires 'not_discovered' which is not discovered")
        ->assertFailed();
});

it('fails on an unsatisfied version constraint (Q7)', function () {
    app(TgModuleRegistry::class)->add(new TgModuleDescriptor(
        id: 'dep_consumer',
        name: 'DepConsumer',
        version: '1.0.0',
        requiresModules: ['example@>=99.0'],
    ));

    $this->artisan('tg:modules:doctor')
        ->expectsOutputToContain("Module 'dep_consumer' requires 'example@>=99.0' but version")
        ->assertFailed();
});

it('fails when conflicting modules are discovered together (Q7)', function () {
    app(TgModuleRegistry::class)->add(new TgModuleDescriptor(
        id: 'conflicting',
        name: 'Conflicting',
        version: '1.0.0',
        conflictsWith: ['example'],
    ));

    $this->artisan('tg:modules:doctor')
        ->expectsOutputToContain("Module 'conflicting' conflicts with 'example' which is also discovered")
        ->assertFailed();
});
