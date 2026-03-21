<?php

use BAGArt\TelegramBot\Http\Laravel;
use BAGArt\TelegramBotBasic\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';

Route::prefix('tg')->middleware([
    Laravel\Middlewares\TgIpValidatorMiddleware::class,
    Laravel\Middlewares\TgSecretValidatorMiddleware::class,
])->group(function () {
    Route::post('/', [Laravel\TgWebhookController::class, 'post']);
});
