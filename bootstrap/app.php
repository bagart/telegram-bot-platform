<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
//    ->withCommands([
//        __DIR__.'/../misc/BAGArt/telegram-bot-lib/src/Commands',
//        __DIR__.'/../misc/BAGArt/telegram-bot-lib-basic/src/Commands',
//        __DIR__.'/../misc/BAGArt/telegram-platform-management/src/Commands',
//    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Telegram delivers webhooks without CSRF tokens; the secret-token
        // and IP middlewares are the authentication layer for tg/*.
        $middleware->validateCsrfTokens(except: ['tg/*', 'tgapp/*']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    // No inline exception configuration here: withExceptions() is still
    // required (it registers the ExceptionHandler singleton), but the §13.4
    // rate-limit envelope rendering lives in the menu module provider.
    ->withExceptions()
    ->create();