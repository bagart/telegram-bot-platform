<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;

// Module packages are NOT listed here: the Telegram Module Engine registers
// each platform-enabled module's Laravel provider from config/tg_modules.php
// in dependency order (bootstrap takeover, phase 3). Only non-module libs
// (basic-lib, management, proxy-operations) stay explicit.
return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    //custom
    BAGArt\TelegramBot\TelegramBotServiceProvider::class,
    BAGArt\TelegramModuleEngine\TelegramModuleEngineServiceProvider::class,
    BAGArt\TelegramBotBasic\TelegramBotBasicServiceProvider::class,
    BAGArt\TelegramBotManagement\TelegramBotManagementServiceProvider::class,
    BAGArt\ProxyOperations\ProxyOperationsServiceProvider::class,
    BAGArt\TelegramBotAudit\Laravel\AuditServiceProvider::class,
    BAGArt\TelegramBotAccess\Laravel\AccessControlServiceProvider::class,
];
