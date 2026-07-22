---
name: outbound-pipeline-development
description: "Apply when working on the outbound message pipeline (misc/BAGArt/telegram-bot-lib/src/Outbound/) — the queue, envelope/task/state DTOs, DLQ, middleware chain, circuit breaker, stats, daemon, and adapters. Trigger when creating or editing OutboundTask, OutboundTaskState, OutboundEnvelope, DeadLetterEntry, TgOutboundDaemon, OutboundPipeline, OutboundMiddleware implementations (ExpiryMiddleware, RateLimitMiddleware, RetryBudgetMiddleware, TelegramOutboundExecutor), TgSender, TgOutboundStats, OutboundCircuitBreaker, LeaseRenewer, or any queue/cache adapter. Also trigger when wiring the daemon via TgBotSetupFactory::createOutboundDaemonParts(), handling OutboundRetryException/OutboundSkipException/OutboundBusinessErrorException, or working with Redis channels tg-outbound / tg-dlq:{botId} / tg_outbound:stats. Covers: control-flow exceptions, Redis state purity, capability interfaces (LeaseRenewableQueueContract, AtomicDlqQueueContract, OutboundOrderingQueueContract, OutboundCacheContract), incrementWithTtl Lua pattern, versioned fromJsonV1() deserialization. Do NOT use for the async kernel lifecycle (use async-kernel-development) or for Eloquent/DB basics (use laravel-best-practices)."
license: MIT
metadata:
  author: BAGArt
---

# Outbound Pipeline Development

The outbound pipeline (`misc/BAGArt/telegram-bot-lib/src/Outbound/`) is the heart of the platform: every outgoing Telegram API call becomes an `OutboundTask`, flows through a middleware chain, and is sent by the executor. Reliability here = reliability of the whole bot.

## When to Apply

Activate for any task touching these files/directories:

- `src/Outbound/` — daemon, pipeline, middleware, envelopes, exceptions, stats, circuit breaker, lease renewer
- `src/Outbound/Adapters/` — Redis / in-memory / Laravel queue + cache adapters
- `src/Outbound/Config/` — `OutboundWorkerConfig`, `OutboundSetup`
- `src/Contracts/Outbound/` — all outbound contracts (`OutboundQueueContract`, capability interfaces)
- `src/TgBotSetupFactory.php` — the `createOutbound*()` factory methods
- `src/Http/Laravel/` and `src/Http/Pure/` — only when the change is about *dispatching into* the outbound queue (e.g. webhook → `TgSender::send`)

## The Three Control-Flow Exceptions (load-bearing)

The pipeline communicates decisions **only** by throwing one of three exceptions. The daemon's `process()` catches exactly these three (plus `Throwable` for poison pills). Get these right or the queue misbehaves.

| Exception | Meaning | Daemon action |
|---|---|---|
| `OutboundRetryException` | Transient failure — retry later. Has `delaySec`, `reason`, `previous`. | `queue->release($envelope, $delay)` + circuit breaker `recordFailure` + stats `recordRetry` |
| `OutboundSkipException` | Hopeless task — discard to DLQ. Has `reason`. | `moveToDlq()` (ack first, then push to DLQ) |
| `OutboundBusinessErrorException` | 4xx business error (400/401/403/404) — DLQ. Has `reason` + `context`. | `moveToDlq()` |

> ⚠️ `ASKInterruptException` is **NOT** in this list. It always bubbles past `process()` to the kernel. Never catch it in middleware or the pipeline.

## Redis State Purity (critical)

**In Redis — only readonly DTOs without behavior, plus metric counters.** Permitted:

- `OutboundTask`, `OutboundTaskState`, `DeadLetterEntry` (as JSON)
- Metric counters via `incrementWithTtl`

**Never store in Redis:**
- Network connections (HTTP client, Guzzle promises)
- Closures / callbacks
- Service objects, middleware instances, executors

If you need to persist behavior, extract it into a `readonly` class and store only its state. Full rules in `rules/queue-and-envelopes.md` and the cross-cutting `highload-stability` skill.

## Rule Files — Read Before Proceeding

| If the task touches… | Read this first |
|---|---|
| Queue contract, `OutboundTask`/`State`/`Envelope`/`DeadLetterEntry`, channel names, `fromJsonV1` | `rules/queue-and-envelopes.md` |
| Middleware order, adding a middleware, capability interfaces | `rules/middleware.md` |
| `TgBotSetupFactory`, `createOutboundDaemonParts()`, building the daemon | `rules/factory-and-daemon.md` |
| `incrementWithTtl`, `RedisOutboundCache` vs `KernelCacheAdapter`, `Framework/Laravel/Laravel*Adapter` | `rules/adapters.md` |

## Quick Reference — Daemon process() decision tree

```
pipeline->execute($envelope)
  │
  ├─ success → queue->ack() + circuitBreaker->recordSuccess() + stats->recordSent()
  │
  ├─ OutboundSkipException    → moveToDlq() [ack + pushToDeadLetter]
  ├─ OutboundBusinessError    → moveToDlq() [ack + pushToDeadLetter]
  │
  ├─ OutboundRetryException   → queue->release(envelope, max(delaySec, backoff))
  │                              + circuitBreaker->recordFailure() + stats->recordRetry()
  │
  └─ Throwable (poison pill)  → queue->release(envelope, 0)
                                 + stats->recordFailed('fatal_worker_error')
                                 + log task_md5 + 256-char preview (NOT full payload)
```

Backoff formula: `max(delaySec, min(defaultRetryDelaySec * attempt², 300))`.

## Common Pitfalls

- **Naming the factory method `createOutboundComponents()`.** The real name is `createOutboundDaemonParts()`. It returns `['queue','pipeline','circuitBreaker','stats','leaseRenewer']` — note **no `sender` and no `daemon`**. Use `createOutboundSender()` separately if you need the sender.
- **Silently dropping tasks.** When neither `AtomicDlqQueueContract` nor a `dlqFallback` is available, the daemon logs the task (md5 + 256-char preview) — it never silently loses it. Preserve this invariant in any new code path.
- **Using `OutboundCacheContract::incrementWithTtl` via the wrong impl.** `KernelCacheAdapter` does a serial get-check-set under a short lock — NOT safe for multi-process Redis. Use `RedisOutboundCache` (Lua `INCR + EXPIRE NX`) in production. See `rules/adapters.md`.
- **Catching `ASKInterruptException`** anywhere in the pipeline. It must reach the kernel.
- **Forgetting `leaseRenewer->track()` / `untrack()`** around fiber execution. The visibility lease must be renewed or another worker will re-pop the in-flight task.
- **Storing a closure in Redis** "just this once". It breaks serialization and the purity rule. Extract to a readonly class.
