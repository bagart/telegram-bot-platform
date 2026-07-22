---
name: async-kernel-development
description: "Apply when working on the BAGArt async kernel (misc/BAGArt/php-async-kernel-lib/) — the Fiber-based cooperative scheduler. Trigger when creating or editing daemons, tickables, producers, the AsyncKernel class, shutdown logic, warmup hooks, or any class under the BAGArt\\AsyncKernel namespace. Also trigger when implementing ASKDaemonContract, ASKTickableContract, WithASKTickableContract, ASKWarmableContract, or ASKShutdownAware, or when wiring a daemon into a CLI command via addDaemon(). Covers: Fiber scheduling, tick loop, backpressure (pressure()/systemPressure), 3-phase graceful shutdown (RUNNING→STOPPING→DRAINING→FORCING→STOPPED), lazy connections, ASKInterruptException propagation. Do NOT use for the outbound pipeline specifics (use outbound-pipeline-development) or for generic Laravel queue/jobs (use laravel-best-practices)."
license: MIT
metadata:
  author: BAGArt
---

# Async Kernel Development

The async kernel (`misc/BAGArt/php-async-kernel-lib/`) is a **homegrown Fiber-based cooperative scheduler** — not ReactPHP, not Amp, not Laravel's queue worker. Every daemon runs inside this loop, so the contracts and lifecycle here are load-bearing for the whole platform.

## When to Apply

Activate this skill whenever you touch `misc/BAGArt/php-async-kernel-lib/`, or any class implementing one of these contracts:

- `BAGArt\AsyncKernel\Contracts\Daemons\ASKDaemonContract`
- `ASKTickableContract`, `WithASKTickableContract`, `WithASKProducerContract`
- `ASKWarmableContract`
- `ASKShutdownAware`

Also activate when a CLI command or standalone script constructs `new AsyncKernel(...)` and calls `addDaemon(...)` / `run()` (e.g. the daemon scripts in `misc/BAGArt/telegram-bot-lib/commands/`).

## Documentation

Use `search-docs` only for surrounding Laravel/PHP details — the async kernel itself is a custom library with no upstream docs. Trust the contracts in `src/Contracts/Daemons/` as the source of truth, not external async-framework conventions.

## Core Principles

1. **Strict contracts only.** No `method_exists`, no `instanceof` duck-typing across library boundaries. If a caller needs a method, declare it in the interface. Every public method must have a real caller.
2. **Lazy connections.** Constructors MUST NOT connect to Redis, TCP sockets, or any external service. Defer to the first actual use, or — the designated hook — implement `ASKWarmableContract::warm()`. `AsyncKernel::addDaemon()` auto-calls `warm()`.
3. **`ASKInterruptException` always bubbles.** It is NOT caught in middleware or pipelines. Pipeline code only catches business exceptions (`OutboundSkipException`, `OutboundBusinessErrorException`, `OutboundRetryException`), never the interrupt.

## Canonical Contract Names (do not confuse with docs)

> ⚠️ `AGENTS.md` historically referenced `ASKShutdownToCompleteContract`. **That contract does not exist.** The real shutdown-completion contract is `ASKShutdownAware`. Trust the names below, not older docs.

| Concern | Contract | Where used |
|---|---|---|
| Daemon lifecycle | `ASKDaemonContract` | `startup()`, `shutdown(ASKShutdownContext)`, `onError()`, `name()` |
| Tickable | `ASKTickableContract` | `tick(int $systemPressure)`, `pressure()`, `isIdle()`, `queueSize()` |
| Tickable wiring | `WithASKTickableContract` | `tickable(): array` returns `[tickables, scheduler?]` |
| Warmup | `ASKWarmableContract` | `warm(): void` |
| Graceful shutdown | `ASKShutdownAware` | `shutdownPriority()`, `shutdownTimeout()`, `prepareShutdown()` |

## Rule Files — Read Before Proceeding

| If the task touches… | Read this first |
|---|---|
| Implementing/wiring a daemon or tickable | `rules/daemons.md` |
| Shutdown ordering, drain phases, `AsyncKernel::doShutdown` | `rules/shutdown.md` |
| Which contract to implement / strict-contract enforcement | `rules/contracts.md` |

## Quick Reference — Daemon Wiring

<!-- Adding a daemon to a CLI command -->
```php
$kernel = new AsyncKernel($logger);
$parts = $factory->createOutboundDaemonParts(...);
$daemon = new TgOutboundDaemon(
    queue: $parts['queue'],
    pipeline: $parts['pipeline'],
    circuitBreaker: $parts['circuitBreaker'],
    stats: $parts['stats'],
    leaseRenewer: $parts['leaseRenewer'],
    logger: $logger,
    config: $workerConfig,
    scheduler: $scheduler, // mandatory — fibers never run without it
);
$kernel->addDaemon($daemon); // auto-calls warm() if implemented
$kernel->run();
```

The daemon is **always** built explicitly in the command — never registered as a singleton, never auto-resolved. See `rules/daemons.md`.

## Common Pitfalls

- **Forgetting the scheduler.** `WithASKTickableContract::tickable()` MUST return the scheduler. `tick()` enqueues fibers into the scheduler; without it the scheduler never ticks, fibers never run, and tasks stall invisibly. See `TgOutboundDaemon::tickable()`.
- **Connecting in a constructor.** Breaks the lazy rule. Move it into `warm()` or a first-use guard. `AsyncKernel::addDaemon()` will not warm a daemon that doesn't implement `ASKWarmableContract`.
- **Catching `ASKInterruptException`** in a pipeline or middleware. It must propagate to the kernel. Only catch the three business exceptions.
- **Implementing `ASKShutdownAware` with wrong priority.** `shutdownPriority()` is higher = earlier. OutboundDaemon=100 (first), MetricsDaemon=0 (last). Reversing this loses in-flight metrics or drops tasks.
- **Renaming `ASKShutdownAware` to `ASKShutdownToCompleteContract`** "to match docs". The doc is wrong; the code is right.
