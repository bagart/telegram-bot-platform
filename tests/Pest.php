<?php

use Tests\TestCase;

// Proxy test helpers (toolId()/toolManifest()...) live in its package Pest.php,
// which Pest only auto-loads when running the package suite on its own.
require_once dirname(__DIR__).'/misc/BAGArt/tgbot-module-proxy/tests/Pest.php';

pest()->extend(TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in(
        'Feature',
        dirname(__DIR__).'/misc/BAGArt/tgbot-module-antispam/tests/Feature',
        dirname(__DIR__).'/misc/BAGArt/telegram-platform-menu/tests/Feature',
        dirname(__DIR__).'/misc/BAGArt/tgbot-module-summarizer/tests/Feature',
        dirname(__DIR__).'/misc/BAGArt/tgbot-module-stt/tests/Feature',
        dirname(__DIR__).'/misc/BAGArt/tgbot-module-tts/tests/Feature',
        dirname(__DIR__).'/misc/BAGArt/tgbot-module-proxy/tests/Feature',
        dirname(__DIR__).'/misc/BAGArt/telegram-bot-lib/tests/Feature',
        dirname(__DIR__).'/misc/BAGArt/telegram-bot-lib/tests/Arch',
        dirname(__DIR__).'/misc/BAGArt/telegram-platform-management/tests/Commands',
        dirname(__DIR__).'/misc/BAGArt/telegram-platform-management/tests/Mcp',
        dirname(__DIR__).'/misc/BAGArt/telegram-platform-management/tests/Services',
        dirname(__DIR__).'/misc/BAGArt/telegram-platform-management/tests/Models',
        dirname(__DIR__).'/misc/BAGArt/telegram-platform-management/tests/Registries',
        dirname(__DIR__).'/misc/BAGArt/telegram-platform-management/tests/Http',
        dirname(__DIR__).'/misc/BAGArt/telegram-platform-access/tests/Feature',
        dirname(__DIR__).'/misc/BAGArt/telegram-platform-audit/tests/Feature',
        dirname(__DIR__).'/misc/BAGArt/telegram-platform-audit/tests/Unit',
    );

pest()->beforeEach(function () {
    \BAGArt\TelegramBotMenu\Tests\Support\LegacyEnablementSchema::ensure();
})->in(
    dirname(__DIR__).'/misc/BAGArt/telegram-platform-menu/tests/Feature',
    dirname(__DIR__).'/misc/BAGArt/telegram-platform-management/tests/Commands',
    dirname(__DIR__).'/misc/BAGArt/telegram-platform-management/tests/Services',
    dirname(__DIR__).'/misc/BAGArt/telegram-platform-management/tests/Models',
    dirname(__DIR__).'/misc/BAGArt/telegram-platform-management/tests/Http',
    // Admin fixtures still write the legacy enablement rows the module
    // engine dropped from the migration chain (2026_09_20).
    dirname(__DIR__).'/misc/BAGArt/tgbot-module-antispam/tests/Feature',
    // Lib module tests pin the chat-scoped legacy enablement semantics
    // (TgModuleEnablement model / TgModuleEnablementService).
    dirname(__DIR__).'/misc/BAGArt/telegram-bot-lib/tests/Feature/Modules',
);

// The lib module tests exercise the chat-scoped 3-tier legacy chain
// (chat → bot → platform) as their subject; pin the legacy driver and
// bindings for them — same pattern as the management tests pinning
// tg_modules.enablement_driver per test. Engine-mode contract coverage
// lives in tests/Feature/ModuleSettingsEngineModeTest.
pest()->beforeEach(function () {
    config(['tg_modules.enablement_driver' => 'legacy']);
    $legacyService = app(\BAGArt\TelegramBotManagement\Services\TgModuleEnablementService::class);
    app()->instance(\BAGArt\TelegramBot\Contracts\Modules\ModuleEnablementContract::class, $legacyService);
    app()->instance(\BAGArt\TelegramBot\Contracts\Modules\ModuleSettingsContract::class, $legacyService);
})->in(
    dirname(__DIR__).'/misc/BAGArt/telegram-bot-lib/tests/Feature/Modules',
);

// The antispam Feature fixtures seed legacy enablement rows directly
// (AdminHelpers factory; retained decision: docs/sdd/module-integration.md —
// switching activation does not migrate settings), so both contracts are
// pinned to the legacy service — reads and writes must hit the same
// storage the fixtures write. Engine-mode contract coverage lives in
// tests/Feature/ModuleSettingsEngineModeTest. tg_modules.enablement_driver
// stays untouched.
pest()->beforeEach(function () {
    $legacyService = app(\BAGArt\TelegramBotManagement\Services\TgModuleEnablementService::class);
    app()->instance(\BAGArt\TelegramBot\Contracts\Modules\ModuleEnablementContract::class, $legacyService);
    app()->instance(\BAGArt\TelegramBot\Contracts\Modules\ModuleSettingsContract::class, $legacyService);
})->in(
    dirname(__DIR__).'/misc/BAGArt/tgbot-module-antispam/tests/Feature',
);

// The summarizer, stt and tts Feature suites keep their legacy enablement
// hooks with the package (table recreation + contract pinning); Pest only
// auto-loads a package Pest.php when the package suite runs on its own.
require_once dirname(__DIR__).'/misc/BAGArt/tgbot-module-summarizer/tests/Pest.php';
require_once dirname(__DIR__).'/misc/BAGArt/tgbot-module-stt/tests/Pest.php';
require_once dirname(__DIR__).'/misc/BAGArt/tgbot-module-tts/tests/Pest.php';
