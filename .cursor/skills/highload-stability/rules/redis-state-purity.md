# Redis State Purity

Redis holds **state**, not **behavior**. The boundary is strict because anything stored in Redis must survive: serialization, a process restart, a PHP version bump, and concurrent access by N workers.

## What may live in Redis

1. **Readonly DTOs without behavior** — serialized as JSON:
   - `OutboundTask` (immutable payload)
   - `OutboundTaskState` (mutable but plain data: status, attempt, error context)
   - `DeadLetterEntry` (task + state + reason + failedAt + redeliveryCount)

2. **Metric counters** — via `incrementWithTtl` (atomic Lua `INCR + EXPIRE NX`):
   - `tg_outbound:stats:{YmdH}:...` hour buckets
   - Circuit-breaker counters
   - Ordering locks (key + TTL + owner)

3. **Locks** — short-TTL keys with an owner for safe release.

## What must NOT live in Redis

| Type | Why |
|---|---|
| Network connections (HTTP client, Guzzle, socket pool) | Not serializable; connection state is process-local |
| Closures / callbacks | `json_encode()` can't serialize them; they reference PHP internals |
| Service objects, middleware instances, executors | They hold behavior + dependencies; rehydrating them is unsafe |
| Resources (file handles, streams) | Not serializable |
| Anonymous classes | Class name is per-process; can't be rehydrated |

## The extraction pattern (callback → readonly class)

When you're tempted to store a callback, extract it into a `readonly` class and store only its state.

**Anti-pattern (don't):**
```php
// Trying to persist a retry strategy as a closure — WILL BREAK serialization
$task->retryStrategy = fn(OutboundTask $t) => computeDelay($t);
Redis::set('task:'.$id, serialize($task)); // Closure not serializable
```

**Pattern (do):**
```php
final readonly class RetryPolicy implements \JsonSerializable
{
    public function __construct(
        public readonly int $baseDelaySec,
        public readonly int $maxDelaySec,
        public readonly float $jitter,
    ) {}

    public function delayFor(int $attempt): int
    {
        $backoff = min($this->baseDelaySec * ($attempt ** 2), $this->maxDelaySec);
        return (int) round($backoff * (1 + $this->jitter));
    }

    public function jsonSerialize(): array { /* ... */ }

    public static function fromJson(string $json): self { /* ... */ }
}

// Store only state; rehydrate on read
Redis::hSet('tg-dlq:'.$botId, $taskId, json_encode($entry));
$policy = RetryPolicy::fromJson($data['retry_policy']);
```

The behavior (`delayFor`) lives in the class definition (in PHP code, loaded fresh per process). Only the state (`baseDelaySec`, `maxDelaySec`, `jitter`) is persisted. This survives serialization, restarts, and version bumps.

## In-memory vs Redis — the split

| Concern | In memory (process-local) | Redis (shared, persistent) |
|---|---|---|
| Network connections / pools | ✅ | ❌ |
| In-flight task map (`$inflight`) | ✅ | ❌ (rebuild from queue on restart) |
| Callbacks / handlers | ✅ | ❌ |
| Task payload + state | transient | ✅ (the queue itself) |
| Counters / metrics | batched buffer | ✅ (source of truth) |
| Locks | — | ✅ (cross-process) |

## Flush rule

Any process-local state that represents un-flushed truth (e.g. a stats counter batched in memory) must flush to Redis on shutdown. If the daemon crashes without flushing, the worst case is lost *recent* counters — not lost tasks (tasks live in the queue, recoverable via visibility-timeout).

## Test for purity

A quick invariant check: `json_encode($persistedThing, JSON_THROW_ON_ERROR)` must succeed AND `json_decode()` of the result must rehydrate an equivalent object via the `fromJson*()` constructor. If either step fails, the thing isn't Redis-pure — extract or refactor.
