<?php

use BAGArt\TelegramBot\Http\Laravel;
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
    // POST /tg/ — token resolved from secret header, IP + secret validation
    Route::post('/', [Laravel\TgWebhookController::class, 'post'])
        ->middleware([
            Laravel\Middlewares\TgIpValidatorMiddleware::class,
            Laravel\Middlewares\TgSecretValidatorMiddleware::class,
        ]);

    // POST /tg/webhook/{bot_id} — token resolved from DB by bot_id, IP + secret validation
    Route::post('/webhook/{bot_id}', [Laravel\TgWebhookController::class, 'postByBotId'])
        ->middleware([
            Laravel\Middlewares\TgIpValidatorMiddleware::class,
            Laravel\Middlewares\TgSecretValidatorMiddleware::class,
            Laravel\Middlewares\TgBotIdResolverMiddleware::class,
        ]);
});
