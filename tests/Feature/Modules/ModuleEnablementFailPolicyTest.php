<?php

declare(strict_types=1);

use BAGArt\TelegramBot\Modules\TgModuleDescriptor;
use BAGArt\TelegramBot\Modules\TgModuleRegistry;
use BAGArt\TelegramBotManagement\Services\TgModuleEnablementService;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config('telegram.modules');
});

function failPolicyService(): TgModuleEnablementService
{
    $registry = new TgModuleRegistry();
    $registry->add(new TgModuleDescriptor(
        id: 'open-module',
        name: 'Open',
        version: '1.0.0',
        defaultEnabled: true,
    ));
    $registry->add(new TgModuleDescriptor(
        id: 'closed-module',
        name: 'Closed',
        version: '1.0.0',
        defaultEnabled: true,
        failClosed: true,
    ));

    return new TgModuleEnablementService(
        moduleRegistry: $registry,
        cache: app(BAGArt\AsyncKernel\Wrappers\ASKCacheWrapper::class),
    );
}

it('fails open with the descriptor default on enablement DB error (Q-D2)', function () {
    Schema::drop('tg_module_enablements');

    expect(failPolicyService()->isEnabled('open-module', 'bot_a', 100))->toBeTrue();
});

it('fails closed on enablement DB error when the module declares failClosed (Q-D2)', function () {
    Schema::drop('tg_module_enablements');

    expect(failPolicyService()->isEnabled('closed-module', 'bot_a', 100))->toBeFalse();
});

it('does not cache fail-policy results', function () {
    Schema::drop('tg_module_enablements');
    $service = failPolicyService();

    expect($service->isEnabled('open-module', 'bot_a', 100))->toBeTrue();

    Schema::create('tg_module_enablements', function ($table): void {
        // minimal replacement table so a later successful read is possible
        $table->id();
        $table->string('module_id');
        $table->boolean('is_enabled')->default(true);
    });

    expect($service->isEnabled('open-module', 'bot_a', 100))->toBeTrue();
});
