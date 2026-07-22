# Webhooks, Routes & Middlewares

## Routes — actual definitions

Defined in the **host app's** `routes/web.php`, under `Route::prefix('tg')`:

```php
use BAGArt\TelegramBot\Http\Laravel\TgWebhookController;
use BAGArt\TelegramBot\Http\Laravel\Middlewares\TgIpValidatorMiddleware;
use BAGArt\TelegramBot\Http\Laravel\Middlewares\TgSecretValidatorMiddleware;
use BAGArt\TelegramBot\Http\Laravel\Middlewares\TgBotIdResolverMiddleware;

Route::prefix('tg')->group(function () {
    // POST /tg/ — token resolved from secret header, IP + secret validation
    Route::post('/', [TgWebhookController::class, 'post'])
        ->middleware([
            TgIpValidatorMiddleware::class,
            TgSecretValidatorMiddleware::class,
        ]);

    // POST /tg/tg_webhook/{bot_id} — token resolved from DB by bot_id
    Route::post('/tg_webhook/{bot_id}', [TgWebhookController::class, 'postByBotId'])
        ->middleware([
            TgIpValidatorMiddleware::class,
            TgSecretValidatorMiddleware::class,
            TgBotIdResolverMiddleware::class,
        ]);
});
```

**Routes are loaded from the main app, NOT from library service providers.** `telegram-bot-management-lib/routes/web.php` is essentially empty. If you add a webhook route, add it to the host app's `routes/web.php`.

> ⚠️ The older `AGENTS.md` "Webhook Endpoints" block listed `TgWebhookExample` and `POST /tg/{token}` / `POST /tg/example/{token}`. Those classes/routes do not exist. Trust the definitions above.

## Controller: `TgWebhookController`

`telegram-bot-lib/src/Http/Laravel/TgWebhookController.php` (extends Laravel `Controller`). Two actions:

- `post(TgWebhookWithAutoSecretRequest $request, TgWebhookRequestParser $parser, TgBotConfig $config)` — token resolved from the secret header.
- `postByBotId(Request $request, ..., TgBotConfig $config)` — token resolved from DB via the `{bot_id}` route param; secret from the `X-Telegram-Bot-Api-Secret-Token` header.

Both set `dispatcher = SyncProcessingDispatcher::TYPE` and call `$parser->parse($data, $secret, $config, $botConfig)`. The parser (`Http/Pure/TgWebhookRequestParser.php`) is framework-free — the Laravel controller is a thin wrapper.

## Form request: `TgWebhookWithAutoSecretRequest`

Reads the secret from the header. `botId()` = `explode(':', $secret)[0]` — the bot ID is encoded as the prefix of the secret token. If you change the secret format, update both the encoder and this parser.

## Middlewares

All in `telegram-bot-lib/src/Http/Laravel/Middlewares/`:

### `TgIpValidatorMiddleware`
Delegates to `Http/Pure/Validators/TelegramIpValidator`. Hardcoded Telegram CIDR allowlist:
- `149.154.160.0/20`
- `91.108.4.0/22`

Returns `403` if the request IP is outside these ranges. This blocks webhook spoofing. **Both webhook routes require it.**

### `TgSecretValidatorMiddleware`
Validates the `X-Telegram-Bot-Api-Secret-Token` header:
- `401` if missing.
- `403` if invalid.
- Derives `botId` via `TgBotsSecretServiceContract::botId()`.
- Binds `TgBotConfig` into the container for downstream use.

### `TgBotIdResolverMiddleware`
For the `/tg/webhook/{bot_id}` route — resolves the token from the DB by `{bot_id}` (via `TgDbTokenResolver implements BotTokenResolverContract`) and binds `TgBotConfig`.

### Symfony variant
`Http/Symfony/Middleware/TgIpValidatorListener.php` exists for non-Laravel Symfony setups. Prefer the Laravel middleware in Laravel contexts.

## The IP allowlist is a security boundary

If Telegram announces new IP ranges (they occasionally do), update the CIDR list in `TelegramIpValidator`. Do **not** remove the middleware "for testing" — disable it in test config instead.

## Webhook registration with Telegram

To point a Telegram bot at this platform, set its webhook to:

```
https://<host>/tg/webhook/<bot_id>
```

…and set the webhook's `secret_token` to match the `secret_token` column in `tg_bots`. Telegram will then send updates with the `X-Telegram-Bot-Api-Secret-Token` header, which `TgSecretValidatorMiddleware` checks.

For the auto-secret variant (`POST /tg/`), the secret encodes the `bot_id` as its prefix — see `TgWebhookWithAutoSecretRequest::botId()`.
