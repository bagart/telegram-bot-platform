# Graceful Shutdown

The kernel's shutdown is designed for **seamless completion of in-flight work**, not fast exit. Three phases (plus bookends) model the transition.

## Shutdown Phases

`BAGArt\AsyncKernel\Enum\ShutdownPhase`:

| Phase | Meaning |
|---|---|
| `RUNNING` | Normal operation. |
| `STOPPING` | Signal received. `ASKShutdownAware::prepareShutdown()` is called — daemons stop accepting *new* tasks (no new `pop()`), but finish in-flight work. |
| `DRAINING` | Daemons drain by priority. Each `shutdown($context)` returns `true` only when fully drained. |
| `FORCING` | A daemon's `shutdownTimeout()` elapsed without `true` — force-cut. |
| `STOPPED` | Kernel exited. |

The goal of DRAINING: complete every in-flight task even if it takes minutes, without pulling new work from the queue.

## ASKShutdownAware — the real "complete" contract

> ⚠️ Older `AGENTS.md` prose said "if the queue implements `ASKShutdownToCompleteContract`". **That name does not exist in code.** The actual contract is `ASKShutdownAware`, and the "complete all in-flight work" behavior is implemented by the daemon's `shutdown()` method returning `false` until drained — driven by `AsyncKernel::doShutdown()`. The contract below governs ordering and timeouts.

```php
interface ASKShutdownAware
{
    public function shutdownPriority(): int;    // higher = shuts down earlier
    public function shutdownTimeout(): int;     // max seconds before FORCING
    public function prepareShutdown(): void;    // called in STOPPING — stop accepting new tasks
}
```

Canonical priorities (from the contract docblock):

| Daemon | Priority | Timeout |
|---|---|---|
| `OutboundDaemon` | 100 (first) | 30s |
| `QueueDaemon` | 50 | — |
| `MetricsDaemon` | 0 (last) | 5s |

**Why ordering matters.** The outbound daemon must finish sending before the metrics daemon flushes its final counters, otherwise the last batch of stats is lost. Higher priority shuts down first. Reversing this is a data-loss bug.

## How `shutdown()` should behave

Return `true` only when there is nothing left in flight. Otherwise return `false` and let the kernel re-call you next tick until `shutdownTimeout()` forces you.

`TgOutboundDaemon::shutdown()` is the reference:

```php
public function shutdown(ASKShutdownContext $context): bool
{
    if (!$this->isShuttingDown) {
        $this->isShuttingDown = true;   // also stops tick() from popping new work
        $this->logger->debug('[OutboundWorker::shutdown]: need to complete: ', [
            'count' => count($this->inflight),
        ]);
    }

    if ($this->inflight === []) {
        return true;   // fully drained
    }

    $this->logger->debug('[OutboundWorker] shutdown: waiting for in-flight tasks', [
        'count' => count($this->inflight),
    ]);
    return false;
}
```

Note how `tick()` checks `if ($this->isShuttingDown) return;` at the top — that's the "no new tasks" gate that `prepareShutdown()` semantics rely on.

## Flushing in-memory state

Any component holding in-memory state (queue buffers, HTTP client pools, stats counters) must flush on shutdown. The flush is triggered from the daemon's `shutdown()` (or a collaborator's), and writes go to Redis/DB. **State in Redis must be readonly DTOs + counters only** — see the outbound skill's Redis-state rules.

## FORCING fallback

If `shutdown()` returns `false` past `shutdownTimeout()`, the kernel moves to FORCING and cuts the daemon. In-flight fibers are abandoned. For the outbound daemon this means: tasks whose lease hasn't expired will be re-popped by another worker after the visibility timeout — so the crash is recoverable, not lossy, as long as visibility timeouts are configured sanely (see `OutboundWorkerConfig::visibilityTimeoutSec`).

## Do NOT

- **Catch `ASKInterruptException` in `shutdown()`** or anywhere in a pipeline. It propagates to the kernel.
- **Return `true` prematurely** to "exit faster". The whole point is to drain.
- **Pull new tasks in `shutdown()`**. `prepareShutdown()` / `isShuttingDown` exist precisely to stop ingestion.
