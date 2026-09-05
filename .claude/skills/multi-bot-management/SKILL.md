---
name: multi-bot-management
description: "Apply when working on multi-bot management (misc/BAGArt/telegram-platform-management/) — the TgBot/TgBotOwner/TgBotModule models, migrations, tg_webhook routes/controllers/middlewares, DB token resolution, and the tgbm:* Artisan commands. Trigger when creating or editing TgBot, TgBotOwner, TgBotModule, TgDbTokenResolver, TgWebhookController, TgWebhookWithAutoSecretRequest, TgIpValidatorMiddleware, TgSecretValidatorMiddleware, TgBotIdResolverMiddleware, TelegramIpValidator, or any migration in database/migrations/. Also trigger when wiring tg_webhook routes in routes/web.php, registering commands in TelegramBotManagementServiceProvider, or resolving a bot token/secret from the database. Covers: tg_webhook endpoint structure (POST /tg/, POST /tg/tg_webhook/{bot_id}), tokens-in-DB rule, Telegram IP CIDR allowlist, secret-token validation, routes-loaded-from-main-app rule. Do NOT use for outbound queue/pipeline internals (use outbound-pipeline-development) or for async-kernel daemon lifecycle (use async-kernel-development)."
license: MIT
metadata:
  author: BAGArt
---

# Multi-Bot Management

`telegram-platform-management` is the Laravel-facing layer: it owns the DB schema for bots/owners/modules, the webhook HTTP layer, and the `tgbm:*` CLI commands. The actual message processing happens in `telegram-bot-lib`.

## The Tokens-in-DB Rule

> Bot tokens and secret tokens live in the `tg_bots` table (`token`, `secret_token` columns), **NOT in `.env`**. Never introduce an env var for a bot token. Webhooks resolve the token from the DB at request time.

This is what enables multi-tenancy: one deployment serves N bots, each with its own row in `tg_bots`.

## Webhook Endpoints (correct names)

> ⚠️ `AGENTS.md` lists `TgWebhookExample` and `POST /tg/{token}`. **Neither exists.** The real controller is `TgWebhookController` and the real routes are:

| Method | Path | Token source | Middleware |
|---|---|---|---|
| `POST` | `/tg/` | resolved from secret header | `TgIpValidatorMiddleware`, `TgSecretValidatorMiddleware` |
| `POST` | `/tg/webhook/{bot_id}` | resolved from DB by `{bot_id}` | `TgIpValidatorMiddleware`, `TgSecretValidatorMiddleware`, `TgBotIdResolverMiddleware` |

Routes live in the **host app's** `routes/web.php` under `Route::prefix('tg')`. Library service providers do NOT load routes — see `rules/webhooks-and-routes.md`.

## Rule Files — Read Before Proceeding

| If the task touches… | Read this first |
|---|---|
| `TgBot` / `TgBotOwner` / `TgBotModule`, relationships, migrations | `rules/models.md` |
| Routes, controllers, middlewares, IP allowlist, secret validation | `rules/webhooks-and-routes.md` |
| `TelegramBotServiceProvider`, singleton registration, DI rules | `rules/di.md` |

## CLI Commands

Registered in `TelegramBotManagementServiceProvider`:

| Command | Purpose |
|---|---|
| `tgbm:outbound-daemon` | Run the outbound daemon (`--mode=single\|multi`) |
| `tgbm:poller` | Long-poll fallback |
| `tgbm:monitor` | Inspect queue/DLQ/stats |
| `tgbm:outbound-dlq` | DLQ operations (list/retry/purge) |
| `tgbm:outbound-metrics` | Metrics daemon |
| `tgbm:outbound-tool` | Queue maintenance tool |
| `tgbm:migrate` | Run the lib's migrations |
| `tgbm:init` | Initialize bot config |
| `tgbm:mcp` | Start the Telegram Ops MCP server (see below) |

## MCP Server (`tg-ops`)

A custom MCP server exposing operational tools to agents, so they can observe and
operate the platform without shelling out to artisan. Lives in `src/Mcp/` and mirrors
the `laravel/boost` MCP pattern exactly (Server subclass + Tool subclasses).

Registered in `TelegramBotManagementServiceProvider::boot()` via `Mcp::local('tg-ops', ...)`,
gated by `class_exists(\Laravel\Mcp\Facades\Mcp::class)` so the lib doesn't fail if mcp
isn't installed. Launched via `tgbm:mcp` (delegates to `mcp:start tg-ops`). Wired in
`.mcp.json`, `.cursor/mcp.json`, `.vscode/mcp.json`.

| Tool | Reads/mutates | Backed by |
|---|---|---|
| `queue-depth` | read | `OutboundQueueContract::size()` |
| `dlq-list` | read | `AtomicDlqQueueContract::listDeadLetter` + `ChannelDiscoverableQueueContract::getDlqChannels` |
| `outbound-metrics` | read | `TgOutboundStats::getGlobalMetrics/getBotMetrics/getState` |
| `bot-list` | read | `TgBot::all()` (tokens hidden) |
| `daemon-status` | read | Indirect signal only — no PID/heartbeat exists (see note) |
| `dlq-retry` | **destructive** | mirrors `tgbm:outbound-dlq --retry` (atomic extract + restore + re-push) |
| `dlq-purge` | **destructive** | mirrors `tgbm:outbound-dlq --purge` (`PurgeableQueueContract::purgeExpired`) |

**Capability guards:** every DLQ tool checks `instanceof AtomicDlqQueueContract` /
`ChannelDiscoverableQueueContract` / `PurgeableQueueContract` and returns a clear error
if the broker lacks support — same idiom as the `tgbm:*` commands.

**DaemonStatus caveat:** the daemon has no PID file or heartbeat write, so `daemon-status`
cannot authoritatively report process liveness. It infers activity from queue depth +
the last hour's `sent` metric (`likely_up` / `likely_down` / `idle`). An authoritative
signal would require adding a heartbeat in `TgOutboundDaemon::tick()` (documented as a
TODO in `docs/INDEX.md`).

## Common Pitfalls

- **Adding a bot token to `.env`** "for quick testing". Violates the tokens-in-DB rule. Insert a `tg_bots` row instead.
- **Referencing `TgWebhookExample`** or `POST /tg/{token}`. They don't exist. Use `TgWebhookController` and the two real routes.
- **Loading routes from a library provider.** All `tg` routes live in the host app's `routes/web.php`. Library providers must not call `loadRoutesFrom()` for these.
- **Forgetting the IP allowlist middleware.** Webhooks without `TgIpValidatorMiddleware` accept requests from any source — a spoofing risk. Both routes require it.
- **Leaking `token` / `secret_token` in serialization.** `TgBot::$hidden = ['token', 'secret_token']` — preserve this if you add casts or array transformations.
