# Service Providers & Dependency Injection

Three library providers, registered in the host's `bootstrap/providers.php`:

```
BAGArt\TelegramBot\TelegramBotServiceProvider         (telegram-bot-lib)
BAGArt\TelegramBotBasic\TelegramBotBasicServiceProvider (telegram-bot-basic-lib)
BAGArt\TelegramBotManagement\TelegramBotManagementServiceProvider (telegram-bot-management-lib)
```

Plus `AppServiceProvider`, `FortifyServiceProvider`.

## TelegramBotServiceProvider — the DI hub

`register()` wires many singletons. Key outbound bindings (via `registerOutbound()`):

| Binding | Resolved to |
|---|---|
| `BotTokenResolverContract` | `TgDbTokenResolver` |
| `TgOutboundStats` | (singleton) |
| `TgSenderContract` | (singleton, via factory) |
| `OutboundQueueContract` | (singleton, via factory) |

Plus the API chain (`TgApiDTORegistryContract`, `TgRateLimiterContract`, `TgRetryPolicyContract`, `TgCircuitBreakerContract`, `TgBotApiTransportContract`, `TgBotApiClientContract`, `TgBotsSecretServiceContract` → `AutoSecretByTokenService`, etc.), wrappers (`ASKLogWrapper` from Laravel `Logger`, `ASKCacheWrapper` from `CacheManager`), socket pool (`AskHttpSocketClient` + `PoolWarmer`, config-gated), and the transport selector (`HttpTransportContract` via `HttpTransportRegistry`, throws `TgTechnicalException` if unset).

## The daemon is NEVER a singleton

This is load-bearing:

> `TelegramBotServiceProvider` registers only contracts (`TgSenderContract`, `OutboundQueueContract`, `TgOutboundStats`) as singletons — **never the daemon itself.** The daemon is always built in the CLI command.

The daemon (`TgOutboundDaemon`) is a long-lived process entrypoint, not an injectable service. Registering it as a singleton would cause exactly the kind of hidden global state the architecture avoids. Build it explicitly with `new TgOutboundDaemon(...)` in the command — see `outbound-pipeline-development/rules/factory-and-daemon.md`.

## Laravel-facing DI rule

> Classes in `Http/Laravel/` (controllers, middlewares, form requests) must NOT receive `TgBotSetupFactory` via auto-binding. Only final/concrete classes.

`TgBotSetupFactory` is an **internal detail** of `TelegramBotServiceProvider` — it's used exclusively inside the provider to construct the singletons. Laravel classes receive the *results* (the registered singletons: `TgSenderContract`, `TgBotConfig`, etc.) via constructor or method injection, never the factory itself.

If a Laravel class needs something not yet registered, **add a singleton binding** in the provider rather than injecting the factory.

## TelegramBotManagementServiceProvider

- `mergeConfigFrom(config/tg-outbound-daemon.php)` — publishes config.
- Registers the 8 `tgbm:*` commands (see the skill's main page).
- `loadMigrationsFrom(__DIR__.'/../database/migrations')`.
- `loadRoutesFrom(...)` — note: the lib's `routes/web.php` is effectively empty; real routes live in the host app (see `webhooks-and-routes.md`).

## TelegramBotBasicServiceProvider

Registers CLI commands (`WebhookCommand`, `TgWhoamiCommand`, `TgChattingCommand`, demos).

## The Laravel adapter convention

When the library's internal driver differs from Laravel's, create a `Framework/Laravel/Laravel*Adapter` in the library. Existing examples: `ASKCacheWrapper` (wraps `CacheManager`), `ASKLogWrapper` (wraps `Logger`). Some adapters currently live in `Http/Laravel/` or `Outbound/Adapters/` — prefer `Framework/Laravel/` for new framework-binding adapters; see `outbound-pipeline-development/rules/adapters.md`.

## Cache/locker/queue drivers via config

Inside the framework, read drivers via `config()`:

```php
$transport = config('tg-outbound-daemon.daemon.transport'); // guzzle | curl-multi | ask-socket
```

Do not hardcode driver choices in library code — route them through config so deployments can swap without code changes.
