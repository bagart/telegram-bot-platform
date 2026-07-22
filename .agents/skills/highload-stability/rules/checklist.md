# Hardening Checklist

Run this against any change to the hot path (daemon, queue, middleware, webhook, adapter). Each item has a pass/fail test.

## 1. Lazy connections

**Rule:** constructors MUST NOT connect to Redis, TCP sockets, or external services. Defer to first use or `ASKWarmableContract::warm()`.

**Test:** Can you `new` the class with no Redis/server running, without throwing? Does `AsyncKernel::addDaemon()` call `warm()` for you?

**Fail sign:** Constructor takes a DSN and immediately connects; tests need a live Redis to construct the object.

## 2. Atomic counters

**Rule:** Counter increments under concurrency MUST use `OutboundCacheContract::incrementWithTtl()` via `RedisOutboundCache` (Lua `INCR + EXPIRE NX`). Never `KernelCacheAdapter` in multi-worker deployments.

**Test:** Grep for `->increment(` and `->get(...); ->put(...)` patterns in the hot path. Each must either be atomic-by-Lua or guarded by a short-TTL lock.

**Fail sign:** `get` → check → `put` without a lock; using the trait `increment()` directly (the contract docblock explicitly warns it's not atomic).

## 3. Graceful shutdown

**Rule:** Long-lived daemons implement `ASKShutdownAware`. `shutdownPriority()` is correct (outbound=100 first, metrics=0 last). `shutdown()` returns `false` until `inflight === []`.

**Test:** Send SIGTERM. Does the daemon drain in-flight work, or does it exit immediately? Does it ever return `true` while fibers are still running?

**Fail sign:** `shutdown()` always returns `true`; priority inverted (metrics flushes before outbound drains → lost stats).

## 4. DLQ strategy

**Rule:** Tasks that can't be retried (skip/business error) go to DLQ via `AtomicDlqQueueContract::pushToDeadLetter()`. When the broker lacks the capability, use an explicit `dlqFallback` or poison-pill log — **never silent loss**. `DeadLetterEntry::canRedeliver()` caps retries at `MAX_REDELIVERIES=3`.

**Test:** Trace a task from a 400 response. Does it land in `tg-dlq:{botId}`? Can `tgbm:outbound-dlq --retry` recover it?

**Fail sign:** A `catch` that logs-and-continues without DLQ; a missing `dlqFallback` for a non-atomic broker.

## 5. Circuit breaker

**Rule:** The daemon checks `OutboundCircuitBreaker::allowsRequest($botId)` before popping. Successes/failures are recorded (`recordSuccess`/`recordFailure`). A failing bot doesn't stall the whole queue.

**Test:** Force a bot to 5xx repeatedly. Does the breaker open and short-circuit further sends for that bot, while other bots keep processing?

**Fail sign:** No `allowsRequest` check; breaker state in memory only (lost on restart) instead of Redis.

## 6. Idempotency & ordering

**Rule:** `OutboundTask`s are safe to re-deliver (the daemon's `process()` is idempotent for already-sent tasks, or guarded by idempotency keys). Strict per-chat ordering uses `orderingKey` + a queue implementing `OutboundOrderingQueueContract`.

**Test:** Re-deliver an acked task (simulate lease expiry race). Does the recipient see a duplicate, or is it deduplicated?

**Fail sign:** Side effects (DB writes, external calls) without an idempotency key; `orderingKey=null` for tasks that must be ordered.

## 7. Backpressure

**Rule:** `pressure()` returns load scaled to `100 = design limit`. The kernel reads `systemPressure` and throttles. A daemon that grows unbounded under load signals overload via `pressure() > 100`.

**Test:** Flood the queue to 10× capacity. Does `pressure()` climb past 100 and trigger throttling, or does the daemon OOM?

**Fail sign:** `pressure()` hardcoded to 0; unbounded `$inflight` map with no concurrency cap.

## 8. Redis state purity

**Rule:** Only readonly DTOs (`OutboundTask`, `OutboundTaskState`, `DeadLetterEntry`) and metric counters in Redis. No closures, connections, or service objects.

**Test:** `json_encode()` any persisted state. Does it round-trip through `json_decode()` with no `resource`/`Closure`/anonymous-class artifacts?

**Fail sign:** A persisted object with a `Closure` field; storing a service handle "temporarily".

See `redis-state-purity.md` for the extraction pattern.

## 9. Flush on shutdown

**Rule:** Every component with in-memory state flushes on shutdown — queue buffers, HTTP client connection pools, stats counters. The flush writes to Redis/DB so a restart resumes correctly.

**Test:** Kill the daemon mid-flight (after some sends but before normal shutdown). On restart, are in-flight counters/buffers recovered or lost?

**Fail sign:** Stats only flush on clean shutdown; a buffer that's never written to Redis.

## 10. Visibility lease

**Rule:** `visibilityTimeoutSec` > max realistic processing time. For long tasks, `LeaseRenewer` renews the lease so another worker doesn't re-pop it.

**Test:** Make a task take 2× `visibilityTimeoutSec`. Is the lease renewed, or does a second worker pick it up and duplicate the send?

**Fail sign:** `LeaseRenewer::track()`/`untrack()` missing around fiber execution; visibility timeout shorter than the slowest Telegram API call.

## 11. No swallowed exceptions

**Rule:** The daemon's `process()` catches exactly the 3 business exceptions + `Throwable` (poison pill → release + log). `ASKInterruptException` is NOT caught anywhere in the pipeline.

**Test:** Grep for `catch (Throwable` and `catch (Exception` in `src/Outbound/`. Each must either rethrow interrupts or be one of the sanctioned handlers.

**Fail sign:** `catch (\Throwable $e) { /* ignore */ }`; catching `ASKInterruptException`.

## 12. Versioned deserialization

**Rule:** When `OutboundTask`/`OutboundTaskState`/`DeadLetterEntry` schema changes, `schemaVersion` is bumped and a new `fromJsonV*()` branch is added. The old branch stays intact.

**Test:** Load a JSON blob written before your change. Does it deserialize correctly via the v1 path?

**Fail sign:** Editing `fromJsonV1()` in place; no `schemaVersion` field on new writes.
