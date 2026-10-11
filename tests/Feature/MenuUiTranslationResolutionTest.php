<?php

declare(strict_types=1);

use BAGArt\TelegramBot\Modules\TgWebUiRegistry;
use BAGArt\TelegramBotAntispam\Web\AntispamWebUi;
use BAGArt\TelegramBotMenu\Menu\MenuCatalogRefiner;
use BAGArt\TelegramBotMenu\Menu\RefineFinding;
use BAGArt\TelegramBotMafia\Web\MafiaUi;
use BAGArt\TelegramBotNettools\Web\NettoolsWebUi;
use BAGArt\TelegramBotStt\Web\SttWebUi;
use BAGArt\TelegramBotSummarizer\Web\SummarizerWebUi;
use BAGArt\TelegramBotTts\Web\TtsWebUi;

/**
 * B1 catalog-key convention sweep (fix-impact §B1 test 1): every registered
 * webUi() manifest key resolves in every declared locale — zero
 * TRANSLATION_KEY_UNRESOLVED / TRANSLATION_KEY_MISSING_LOCALE findings across
 * all 6 registrants. Host-level by necessity: the menu package cannot see the
 * module packages (dependency direction module → menu), so its suite only
 * exercises fixtures.
 */
it('resolves every registered webUi manifest key in every declared locale', function () {
    $registrants = [
        'antispam' => AntispamWebUi::class,
        'mafia' => MafiaUi::class,
        'nettools' => NettoolsWebUi::class,
        'stt' => SttWebUi::class,
        'summarizer' => SummarizerWebUi::class,
        'tts' => TtsWebUi::class,
    ];

    $registered = [];
    foreach (app(TgWebUiRegistry::class)->all() as $pair) {
        $registered[$pair['module']] = $pair['class'];
    }

    foreach ($registrants as $moduleId => $uiClass) {
        expect($registered[$moduleId] ?? null)->toBe($uiClass, "webUi registrant '$moduleId' is not registered at boot");
    }

    expect(config('menu.i18n.locales'))->toContain('ru', 'en', 'fr', 'es', 'zh');

    $catalog = app(MenuCatalogRefiner::class)->catalog();

    foreach (array_keys($registrants) as $moduleId) {
        expect($catalog->manifest($moduleId))
            ->not->toBeNull("manifest of '$moduleId' was dropped during refinement");
    }

    $resolutionCodes = [
        RefineFinding::TRANSLATION_KEY_UNRESOLVED,
        RefineFinding::TRANSLATION_KEY_MISSING_LOCALE,
    ];

    $findings = array_values(array_filter(
        $catalog->findings()->all(),
        fn (RefineFinding $finding): bool => in_array($finding->code, $resolutionCodes, true),
    ));

    expect($findings)->toBe([]);
});

it('compiles real labels for the swept module hubs in every declared locale', function () {
    $catalog = app(MenuCatalogRefiner::class)->catalog();
    $sweptModules = ['antispam', 'nettools', 'summarizer', 'tts'];

    foreach ((array) config('menu.i18n.locales') as $locale) {
        $localized = $catalog->localized($locale);

        foreach ($sweptModules as $moduleId) {
            $title = $localized[$moduleId]['title'] ?? null;

            expect($title)
                ->toBeString()
                ->and($title)->not->toBe("$moduleId.title", "module '$moduleId' title fell back to the key id in '$locale'");
        }
    }

    expect($catalog->localized('ru')['antispam']['title'])->toBe('Анти-спам')
        ->and($catalog->localized('en')['tts']['title'])->toBe('Text → Voice');
});
