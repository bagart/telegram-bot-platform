<?php

use App\Http\Controllers\HealthController;
use BAGArt\TelegramBot\Http\Laravel;
use BAGArt\TelegramBotMenu\Http\Laravel\TgAppApiController;
use BAGArt\TelegramBotMenu\Http\Laravel\TgAppBootstrapController;
use BAGArt\TelegramBotMenu\Http\Laravel\TgAppSessionController;
use BAGArt\TelegramBotMenu\Http\Laravel\TgMiniAppPageController;
use BAGArt\TelegramBotMenu\Http\Laravel\TgUiContextMiddleware;
use BAGArt\TelegramBotMenu\Http\Laravel\TgWebApiDispatchController;
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

// Telegram Mini App HTTP API (menu RFC §13/§19bis). The route block lives in
// the host; middleware alias and rate-limiter buckets are registered by the
// menu package / host AppServiceProvider. Middleware order mirrors the §27.9
// ladder: maintenance gate (0) → throttle (1) → bearer auth (2).
// Mini App shell page (§19bis): public HTML entry, no API ladder — the SPA
// performs session + bootstrap itself.
Route::get('/tgapp/{botId}', [TgMiniAppPageController::class, 'page'])
    ->name('tgapp.page');

Route::prefix('tgapp/api/v1')->group(function (): void {
    Route::post('/session', [TgAppSessionController::class, 'store'])
        ->middleware(['tgapp.session:gate', 'throttle:tgapp-session']);

    // §27.9 ladder: gate (0) → throttle (1) → bearer auth (2) → UI context.
    $tgappStack = fn (string $bucket): array => [
        'tgapp.session:gate',
        "throttle:{$bucket}",
        'tgapp.session',
        TgUiContextMiddleware::class,
    ];

    Route::get('/bootstrap', [TgAppBootstrapController::class, 'bootstrap'])
        ->middleware($tgappStack('tgapp-read'));

    // Settings read/write (§13.1): admin surface, NOT gated on enablement.
    Route::get('/modules/{moduleId}/settings', [TgAppApiController::class, 'settings'])
        ->middleware($tgappStack('tgapp-read'));
    Route::put('/modules/{moduleId}/settings', [TgAppApiController::class, 'saveSettings'])
        ->middleware($tgappStack('tgapp-write'));

    // Idempotent enablement toggle (§13.4bis): no version check, no conflict path.
    Route::post('/chats/{chatId}/modules/{moduleId}/toggle', [TgAppApiController::class, 'toggle'])
        ->middleware($tgappStack('tgapp-write'));

    // §8.4 module dispatch catch-all; the dispatcher enforces the declared
    // method per §8.4 BEFORE handle() — unmatched methods get the 404 envelope.
    Route::any('/m/{modul eId}/{path?}', TgWebApiDispatchController::class)
        ->where('path', '.*')->middleware($tgappStack('tgapp-api'));

});
