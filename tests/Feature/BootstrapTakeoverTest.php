<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * Architectural guard (roadmap phase 3): after the bootstrap takeover the
 * host bootstrap must not name module implementations — modules are wired
 * exclusively through config/tg_modules.php via the engine.
 */
test('bootstrap providers.php contains no module class references', function () {
    $content = File::get(base_path('bootstrap/providers.php'));

    expect($content)->not->toContain('TelegramBotAntispam\\')
        ->and($content)->not->toContain('TelegramBotSummarizer\\')
        ->and($content)->not->toContain('TelegramBotNettools\\')
        ->and($content)->not->toContain('TelegramBotStt\\')
        ->and($content)->not->toContain('TelegramBotTts\\')
        ->and($content)->not->toContain('TelegramBotMafia\\')
        ->and($content)->not->toContain('TelegramBotMenu\\');
});

test('every enabled engine module declares its laravel provider for the bootstrap takeover', function () {
    $modules = (array) config('tg_modules.modules', []);

    expect($modules)->not->toBeEmpty();

    foreach ($modules as $key => $entry) {
        expect($entry)->toBeInstanceOf(BAGArt\TelegramModuleEngine\Config\TgModuleConfig::class)
            ->and($entry->laravelProvider === null || class_exists($entry->laravelProvider))->toBeTrue("module {$key} laravelProvider class missing");
    }
});
