<?php

use App\Http\Controllers\HealthController;
use BAGArt\TelegramBot\Http\Laravel;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

// Health endpoints (06-runtime-operations.md §39): /health/live, /health/ready, /health.
Route::get('/health/live', [HealthController::class, 'live'])->name('health.live');
Route::get('/health/ready', [HealthController::class, 'ready'])->name('health.ready');
Route::get('/health', [HealthController::class, 'health'])->name('health.diagnostics');
Route::get('/health/metrics', [HealthController::class, 'metrics'])->name('health.metrics');

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

    // POST /tg/tg_webhook/{bot_id} — token resolved from DB by bot_id, IP + secret validation
    Route::post('/tg_webhook/{bot_id}', [Laravel\TgWebhookController::class, 'postByBotId'])
        ->middleware([
            Laravel\Middlewares\TgIpValidatorMiddleware::class,
            Laravel\Middlewares\TgSecretValidatorMiddleware::class,
            Laravel\Middlewares\TgBotIdResolverMiddleware::class,
        ]);
});
