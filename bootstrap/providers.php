<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    //custom
    BAGArt\TelegramBot\TelegramBotServiceProvider::class,
    BAGArt\TelegramBotBasic\TelegramBotBasicServiceProvider::class,
    BAGArt\TelegramBotManagement\TelegramBotManagementServiceProvider::class,
    BAGArt\TelegramBotAntispam\TelegramBotAntispamServiceProvider::class,
    BAGArt\TelegramBotSummarizer\TelegramBotSummarizerServiceProvider::class,
    BAGArt\TelegramBotNettools\TelegramBotNettoolsServiceProvider::class,
    BAGArt\TelegramBotStt\TelegramBotSttServiceProvider::class,
    BAGArt\TelegramBotTts\TelegramBotTtsServiceProvider::class,
    BAGArt\TelegramBotMafia\MafiaServiceProvider::class,
    BAGArt\TelegramBotMenu\TelegramBotMenuServiceProvider::class,
    BAGArt\ProxyOperations\ProxyOperationsServiceProvider::class,
];
