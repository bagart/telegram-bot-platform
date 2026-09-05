<?php

use Tests\TestCase;

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
    );
