# Failure Modes & Recovery

How the platform behaves when things break, and how to diagnose each.

## Crash mid-task (worker dies)

**Mechanism:** `pop()` hides the task for `visibilityTimeoutSec`. If the worker crashes before `ack()`, the lease expires and the task reappears in the queue. Another worker re-pops it.

**Recovery:** Automatic, eventually consistent. The window = `visibilityTimeoutSec`.

**Requirement:** The task's processing must be **idempotent** — re-delivery must not duplicate side effects (sent messages, DB writes). See checklist #6.

**Fail mode:** If `visibilityTimeoutSec` < actual processing time, the lease expires *while the task is still in flight*, a second worker picks it up, and the user gets a duplicate. Fix: raise the timeout or use `LeaseRenewer`.

## Poison pill (task crashes every worker)

**Mechanism:** A task that throws a non-business `Throwable` every time it runs. Without protection, it would be released with delay 0 and re-popped infinitely — a tight crash loop.

**Handling in `process()`:**
```php
catch (Throwable $e) {
    $this->queue->release($envelope, 0);
    $this->stats->recordFailed(..., reason: 'fatal_worker_error');
    $this->logger->error('Fatal worker error', [
        'error' => $e->getMessage(),
        'task_md5' => md5(json_encode($envelope->task)),
    ]);
}
```

The retry-budget middleware (`RetryBudgetMiddleware`) caps total retries per task — beyond the budget, the task is moved to DLQ via `OutboundSkipException`. So poison pills eventually drain to the DLQ rather than looping forever.

**Recovery:** Inspect `tg-dlq:{botId}`, fix the underlying cause, then `tgbm:outbound-dlq --retry <id>` (respects `canRedeliver()` / `MAX_REDELIVERIES=3`).

## Retry vs DLQ — decision tree

```
Task fails
   │
   ├─ Transient? (5xx, timeout, 429 rate-limit, network)
   │     → OutboundRetryException(delaySec)
   │     → queue->release(envelope, max(delaySec, backoff))
   │     → circuitBreaker->recordFailure()
   │     → RetryBudgetMiddleware eventually moves to DLQ if budget exhausted
   │
   ├─ Business error? (400 bad request, 401/403 auth, 404 not found)
   │     → OutboundBusinessErrorException(reason, context)
   │     → moveToDlq() [ack + pushToDeadLetter]
   │     → permanent — won't succeed on retry
   │
   └─ Hopeless? (expired task, schema mismatch, undecodable)
         → OutboundSkipException(reason)
         → moveToDlq()
         → permanent
```

**Backoff:** `max(delaySec, min(defaultRetryDelaySec * attempt², 300))`. Capped at 300s to avoid multi-hour stalls.

## Retry storm

**Symptom:** A bot starts failing; retries pile up; the queue fills with retry-delayed tasks; throughput collapses.

**Defenses (all must be present):**
1. **Circuit breaker** — after N consecutive failures for a bot, `allowsRequest()` returns false; further pops for that bot are released with a 30s delay instead of being processed. Other bots keep running.
2. **Retry budget** — per-task cap on retries; exhausted tasks go to DLQ.
3. **Exponential backoff with jitter** — retries spread out, not thundering.
4. **DLQ as pressure relief** — permanent failures exit the queue, don't loop.

**Diagnose:** `tgbm:monitor` shows per-bot failure rates; `tgbm:outbound-dlq --list` shows what's been dropped.

## Lease expiry race (duplicate delivery)

**Symptom:** User receives the same message twice.

**Cause:** Task took longer than `visibilityTimeoutSec`; lease expired; second worker popped and sent.

**Fix:**
1. Raise `visibilityTimeoutSec` above the p99 processing time.
2. For known-long tasks, ensure `LeaseRenewer::track()` is called (it is, in `TgOutboundDaemon::tick()`).
3. Make the send idempotent at the Telegram layer when possible (e.g. dedupe by `orderingKey` + payload hash).

## Stuck daemon (tasks never process)

**Symptom:** Queue size grows; nothing comes out.

**Cause (most common):** The scheduler is missing from `WithASKTickableContract::tickable()`. `tick()` enqueues fibers into the scheduler, but if the scheduler isn't ticked, fibers never run.

**Diagnose:** Check `tickable()` returns `[$leaseRenewer, $scheduler]`. Check the daemon's `$inflight` — if it grows unbounded while `queueSize` stays flat, fibers aren't draining.

**Fix:** See `async-kernel-development/rules/daemons.md`.

## Daemon hangs on shutdown

**Symptom:** SIGTERM sent, daemon doesn't exit.

**Cause:** `shutdown()` keeps returning `false` because `$inflight` never empties — fibers are stuck (usually on a blocked I/O call without a timeout).

**Diagnose:** The shutdown log shows `count` of in-flight tasks not decreasing.

**Fix:** After `shutdownTimeout()` (30s for outbound), the kernel moves to FORCING and cuts the daemon. In-flight fibers are abandoned; their tasks reappear after `visibilityTimeoutSec`. Ensure all I/O has timeouts so this is rare, not normal.

## Redis connection drop

**Symptom:** Counter increments drift; locks fail; queue ops error.

**Mechanism:** The fiber client is lazy and reconnects on next use. In-flight sends that complete on the Telegram side but fail to `ack()` (because Redis dropped) will be re-delivered after the lease expires.

**Recovery:** Eventually consistent. Counters may under-count the drop window (acceptable for metrics; not for billing).

**Requirement:** All Redis ops must tolerate transient failures — catch `\RedisException` at the adapter boundary and either retry-once or degrade (e.g. log-and-continue for stats, fail-loud for locks).
