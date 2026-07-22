# Glossary

> Decodes the overloaded and idiosyncratic terms used across this codebase. When a term is ambiguous, this is the authoritative disambiguation.

## ASK — overloaded acronym (read carefully)

The prefix **ASK** appears in three related but distinct senses. Context determines which.

| Sense | Meaning | Namespace / examples |
|---|---|---|
| **Async Kernel** (umbrella) | The whole homegrown Fiber-based cooperative scheduler library | `BAGArt\AsyncKernel`, `misc/BAGArt/php-async-kernel-lib/` |
| **ASK-Socket** | A specific custom HTTP transport (persistent sockets) — *not* the kernel itself | `tasks/socket.md`, `HttpTransportContract` with `transport=ask-socket` |
| **Contract prefix** | Marker for kernel-level interfaces | `ASKDaemonContract`, `ASKTickableContract`, `ASKWarmableContract`, `ASKShutdownAware`, `ASKInterruptException` |

When a doc says "the ASK does X", it almost always means the **Async Kernel**. When it says "ASK-Socket", it means the **transport**. The `ASK*Contract` names are interface names, not the kernel itself.

> Historical note: older `AGENTS.md` prose referenced `ASKShutdownToCompleteContract`. **That contract does not exist.** The real name is `ASKShutdownAware`.

## Tg — Telegram prefix

Prefix for all Telegram-domain classes and columns. **Not** an acronym; short for "Telegram".

- `TgBot` (model), `TgBotOwner`, `TgBotModule` — DB models
- `TgWebhookController`, `TgWebhookRequestParser` — HTTP layer
- `TgSender`, `TgSenderContract` — sends outbound messages
- `TgOutboundDaemon`, `TgOutboundStats` — daemon + metrics
- `TgApi*` — the auto-generated Telegram Bot API DTOs
- `tg_bots`, `tg_bot_owners`, `tg_bot_modules` — DB tables
- `tg-` / `tg_outbound:` — Redis key prefixes

## Outbound

The entire send pipeline: an outgoing Telegram API call becomes an `OutboundTask`, flows through middleware, and is sent by the executor. Contrast with **Inbound** (incoming webhook updates), which is not prefixed "Outbound".

- `OutboundTask`, `OutboundTaskState`, `OutboundEnvelope`, `DeadLetterEntry` — the DTOs
- `OutboundPipeline`, `OutboundMiddleware` — the chain
- `OutboundQueueContract`, `OutboundCacheContract`, `OutboundCircuitBreakerContract` — contracts
- `OutboundRetryException`, `OutboundSkipException`, `OutboundBusinessErrorException` — control flow

## DLQ — Dead Letter Queue

Where tasks go when they can't succeed (business errors, hopeless tasks, exhausted retry budget). Channel name: `tg-dlq:{botId}`.

- `DeadLetterEntry` — the DLQ record DTO (`MAX_REDELIVERIES = 3`)
- `AtomicDlqQueueContract` — capability interface for atomic DLQ ops
- `tgbm:outbound-dlq --list|--retry|--purge` — CLI management

## Fiber scheduler

The cooperative multitasking primitive. PHP `Fiber` objects are enqueued into an `ASKSchedulerContract` and resumed by the kernel's tick loop. Not ReactPHP, not Amp — homegrown.

- `ASKFiberScheduler` — the scheduler implementation
- `WithASKTickableContract::tickable()` — returns `[tickables, scheduler]`; the scheduler is **mandatory**

## Tickable / Warmable / ShutdownAware — daemon capability contracts

| Contract | Hook | When the kernel calls it |
|---|---|---|
| `ASKTickableContract` | `tick(int $systemPressure)` | Every loop iteration |
| `ASKWarmableContract` | `warm()` | Once, at `addDaemon()` time (lazy connection priming) |
| `ASKShutdownAware` | `prepareShutdown()`, `shutdownPriority()`, `shutdownTimeout()` | During the `STOPPING`/`DRAINING` phases |

A daemon typically implements several. `TgOutboundDaemon` implements `ASKDaemonContract`, `ASKTickableContract`, `WithASKTickableContract` (and would implement `ASKShutdownAware` for graceful drain).

## Lease / visibility timeout

When a worker `pop()`s a task, the task is hidden from other workers for `visibilityTimeoutSec` (the "lease"). If the worker crashes before `ack()`, the lease expires and the task reappears — this is crash recovery. `LeaseRenewer` extends the lease for long-running tasks.

- `OutboundQueueContract::pop(int $visibilityTimeoutSec = 60)`
- `LeaseRenewableQueueContract` — capability for renewal
- `LeaseRenewer` — the daemon component that tracks/renews

## orderingKey

A per-chat strict-ordering key on `OutboundTask` (`chat_id:session_id` form). When non-null and the queue implements `OutboundOrderingQueueContract`, tasks with the same key are delivered in push order. `null` means broadcast (no ordering).

## Poison pill

A task that throws a non-business `Throwable` every time it runs. Without protection it would loop infinitely. Handled by: `RetryBudgetMiddleware` caps retries → moves to DLQ via `OutboundSkipException`. In `process()`, the `catch (Throwable)` branch releases with delay 0 and logs `task_md5` + 256-char preview (never the full payload).

## Circuit breaker

Per-bot failure isolation. `OutboundCircuitBreakerContract` / `OutboundCircuitBreaker`. After N consecutive failures for a bot, `allowsRequest($botId)` returns false → further pops for that bot are released with delay instead of processed. Other bots keep running.

## incrementWithTtl

The atomic counter primitive on `OutboundCacheContract`. Redis Lua `INCR + EXPIRE NX` in a single round-trip. TTL is set **only on key creation** (`EXPIRE NX`), never reset. Used for metrics and circuit-breaker counters.

- `RedisOutboundCache` — atomic, production-safe
- `KernelCacheAdapter` — serial get-check-set fallback, **not** safe for multi-worker

## Shutdown phases

`BAGArt\AsyncKernel\Enum\ShutdownPhase`:

```
RUNNING → STOPPING → DRAINING → FORCING → STOPPED
```

- `STOPPING`: `prepareShutdown()` called; daemons stop accepting new tasks.
- `DRAINING`: daemons drain by `shutdownPriority()` (higher = earlier; OutboundDaemon=100, MetricsDaemon=0).
- `FORCING`: a daemon's `shutdownTimeout()` elapsed without `shutdown() === true` → force-cut.

## Boost / Boost MCP

**Laravel Boost** (`laravel/boost` v2) — a dev tooling package that generates per-agent guidelines (the `<laravel-boost-guidelines>` block in AGENTS.md/CLAUDE.md/GEMINI.md) and ships an MCP server (`boost:mcp`) with tools: `search-docs`, `database-query`, `database-schema`, `get-absolute-url`, `browser-logs`. Not to be confused with C++ Boost.

## Daemon-in-command (vs singleton)

Architecture rule: daemons are constructed explicitly with `new TgOutboundDaemon(...)` inside CLI commands / standalone scripts, **never** registered as container singletons. The factory method `TgBotSetupFactory::createOutboundDaemonParts()` returns the shared components; the caller assembles the daemon. This is the primary extensibility point.

## Strict contracts (no duck-typing)

Project rule: across library boundaries, no `method_exists` / `instanceof`-into-concrete-class. If a caller needs a method, it must be declared in an interface. Capability discovery (e.g. "does this queue support DLQ?") uses dedicated capability interfaces (`AtomicDlqQueueContract`, `LeaseRenewableQueueContract`) checked via `instanceof` — that is allowed because the capability is itself a declared contract.

## Tokens-in-DB

Bot tokens and webhook secrets live in the `tg_bots` table (`token`, `secret_token` columns), **not** in `.env`. This enables multi-tenancy: one deployment serves N bots. Webhooks resolve the token from the DB at request time.

## Module registries

Module components are declared through `TgModuleRegistrar` (processor / validationRule / outboundMiddleware / command) and land in shared singleton registries of class-strings built lazily: `TypeDTOProcessorRegistry`, `MessageValidationRuleRegistry`, `OutboundMiddlewareRegistry` (pipeline position: after rate-limit, before executor), `TgCommandRegistry` (a matching `/command` intercepts the update exclusively in `RegisteredUpdateProcessorSelector`).

## Enablement & module settings

`tg_module_enablements` rows resolve through the inheritance chain chat → bot → platform (bot_id NULL) → `descriptor()->defaultEnabled`. The same chain and cache apply to `module_settings` (json column) via `ModuleSettingsContract::settingsFor()`; fail policy on storage errors is per-descriptor `failClosed` (default fail-open).
