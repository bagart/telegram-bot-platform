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
];
