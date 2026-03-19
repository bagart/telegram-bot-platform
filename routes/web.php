<?php

use BAGArt\TelegramBotBasic\Http\Controller\TgWebhookExample;
use BAGArt\TelegramBotBasic\Http\Controllers\WebhookController;
use BAGArt\TelegramBotBasic\Http\Middleware\ValidateTelegramWebhook;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';

Route::prefix('tg')->group(function () {
    Route::post('{token}', [WebhookController::class, 'handle']);
    Route::post('example/{token}', [TgWebhookExample::class, 'handle']);
    Route::post('webhook/{bot_uuid}', [TgWebhookExample::class, 'handle'])
        ->middleware(ValidateTelegramWebhook::class);
});
