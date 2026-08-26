<?php

use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in(
        'Feature',
        dirname(__DIR__).'/misc/BAGArt/telegram-bot-antispam-module/tests/Feature',
        dirname(__DIR__).'/misc/BAGArt/telegram-bot-menu-module/tests/Feature',
        dirname(__DIR__).'/misc/BAGArt/telegram-bot-summarizer-module/tests/Feature',
        dirname(__DIR__).'/misc/BAGArt/telegram-bot-stt-module/tests/Feature',
        dirname(__DIR__).'/misc/BAGArt/telegram-bot-tts-module/tests/Feature',
        dirname(__DIR__).'/misc/BAGArt/telegram-bot-proxy-module/tests/Feature',
        dirname(__DIR__).'/misc/BAGArt/telegram-bot-lib/tests/Feature',
        dirname(__DIR__).'/misc/BAGArt/telegram-bot-lib/tests/Arch',
        dirname(__DIR__).'/misc/BAGArt/telegram-bot-management/tests/Commands',
        dirname(__DIR__).'/misc/BAGArt/telegram-bot-management/tests/Mcp',
        dirname(__DIR__).'/misc/BAGArt/telegram-bot-management/tests/Services',
        dirname(__DIR__).'/misc/BAGArt/telegram-bot-management/tests/Models',
        dirname(__DIR__).'/misc/BAGArt/telegram-bot-management/tests/Registries',
    );
