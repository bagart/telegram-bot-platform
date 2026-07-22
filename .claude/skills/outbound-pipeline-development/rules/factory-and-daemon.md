# TgBotSetupFactory & Building the Daemon

> ⚠️ The factory method is `createOutboundDaemonParts()`. Older docs called it `createOutboundComponents()` — **that method does not exist**. Calling it will fail.

## What `TgBotSetup` holds

`TgBotSetup` is a readonly aggregate of **contracts and DTOs only** — `logger`, `cache`, `queue`, `tgSender`, `outboundStats`, `processorRegistry`, etc. It does **NOT** contain the daemon. The daemon is built explicitly by the caller.

## Factory methods on `TgBotSetupFactory`

All outbound creators delegate to the private `resolveOutboundDeps(...)` and return a single shared set of connections (same queue, cache, etc.). Pick the granularity you need:

| Method | Returns |
|---|---|
| `createOutboundQueue()` | `OutboundQueueContract` |
| `createOutboundStats()` | `TgOutboundStats` |
| `createOutboundSender()` | `TgSenderContract` |
| `createOutboundPipeline()` | `OutboundPipeline` |
| `createOutboundCircuitBreaker()` | `OutboundCircuitBreaker` |
| `createLeaseRenewer()` | `LeaseRenewer` |
| **`createOutboundDaemonParts()`** | `array{queue, pipeline, circuitBreaker, stats, leaseRenewer}` |

Common signature:

```php
public function createOutboundDaemonParts(
    ?TgServiceConfig $serviceConfig = null,
    ?OutboundWorkerConfig $workerConfig = null,
    ?string $redisDsn = null,
    ?RedisClientContract $redisClient = null,
): array;
```

Note: `createOutboundDaemonParts()` does **not** return the sender or the daemon itself. The daemon needs a `scheduler` (from the async kernel) which the factory doesn't own — the caller supplies it.

## Building the daemon in a CLI command / script

This is the canonical wiring. The daemon is constructed with `new`, never auto-resolved, never a singleton.

```php
$factory = new TgBotSetupFactory(logger: $logger, cache: $cache);
$parts = $factory->createOutboundDaemonParts(
    workerConfig: $workerConfig,
    redisDsn: $redisDsn,
);

$scheduler = new ASKFiberScheduler(...); // from php-async-kernel-lib

$daemon = new TgOutboundDaemon(
    queue:          $parts['queue'],
    pipeline:       $parts['pipeline'],
    circuitBreaker: $parts['circuitBreaker'],
    stats:          $parts['stats'],
    leaseRenewer:   $parts['leaseRenewer'],
    logger:         $logger,
    config:         $workerConfig,
    scheduler:      $scheduler,   // mandatory — see async-kernel daemons.md
    dlqFallback:    null,         // or a closure for non-AtomicDlq brokers
);

$kernel = new AsyncKernel($logger);
$kernel->addDaemon($daemon);      // auto-calls warm() if implemented
$kernel->run();
```

Reference implementations live in `misc/BAGArt/telegram-bot-lib/commands/`:
- `outbound-daemon.php` — minimal daemon
- `all-in-one-daemon.php` — daemon + metrics + DLQ in one kernel
- `outbound-metrics-daemon.php` — metrics-only daemon

And the Artisan command `TgOutboundDaemonCommand` (`tgbm:outbound-daemon --mode=single|multi ...`).

## The scheduler is mandatory

`TgOutboundDaemon::tickable()` returns `[$this->leaseRenewer, $this->scheduler]`. If you omit the scheduler from the constructor, `tick()` will `enqueue()` fibers into nothing — they never run, tasks stall in `$inflight` invisibly, and `shutdown()` never completes. Always pass a real `ASKSchedulerContract`.

## Service-provider registration (Laravel side)

`TelegramBotServiceProvider::registerOutbound()` binds **contracts**, never the daemon:

- `BotTokenResolverContract` → `TgDbTokenResolver`
- `TgOutboundStats` (singleton)
- `TgSenderContract` (singleton)
- `OutboundQueueContract` (singleton)

The daemon class itself is **not** registered. This is deliberate — the daemon is a long-lived process entrypoint, not an injectable service. Laravel-facing classes in `Http/Laravel/` receive only these concrete singletons via constructor/method injection, never `TgBotSetupFactory`.

## Customizing without editing library code

Because the daemon is built in the caller, you can:
- Substitute a different `OutboundQueueContract` (e.g. a custom broker).
- Inject a different middleware chain via `createOutboundPipeline()` parameters.
- Provide a `dlqFallback` closure for brokers without `AtomicDlqQueueContract`.
- Wrap the logger / stats with decorators.

This is the primary extensibility point — prefer it over editing `telegram-bot-lib/src/`.
