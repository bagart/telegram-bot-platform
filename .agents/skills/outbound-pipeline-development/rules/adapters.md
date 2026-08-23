# Adapters: Cache, Locker, Queue Drivers

## incrementWithTtl — the atomic counter pattern

`OutboundCacheContract::incrementWithTtl()` is the atomic counter used by metrics and the circuit breaker. The contract is explicit about atomicity:

> Atomic operation (Redis Lua `INCR + EXPIRE NX`). The core `ASKCacheContract::increment()` is not atomic in its trait implementation (get+set), therefore cannot be used directly — only through this contract. TTL is set only on key creation (`EXPIRE NX`), not reset.

```php
public function incrementWithTtl(string $key, int $value, int $ttlSec): int;
```

**Two implementations, choose carefully:**

| Adapter | How it works | Safe for multi-process Redis? |
|---|---|---|
| `RedisOutboundCache` | Lua script: `INCR` + `EXPIRE key ttl NX` (single round-trip, atomic) | ✅ Yes — production |
| `KernelCacheAdapter` | Serial section: short-TTL lock → `get` → `set` → unlock | ❌ No — only for non-Redis backends / single-process |

**Rule:** in any multi-worker deployment, the outbound cache MUST be `RedisOutboundCache`. `KernelCacheAdapter` is a fallback for tests / single-process mode. Using it in production causes lost counter increments under concurrency (race between get and set).

## Cache contract — full surface

```php
interface OutboundCacheContract
{
    public function incrementWithTtl(string $key, int $value, int $ttlSec): int;
    public function lock(string $key, int $ttlSec, ?string $owner = null): bool;
    public function unlock(string $key, ?string $owner = null): void;  // owner-checked release
    public function get(string $key): mixed;
    public function put(string $key, mixed $value, int $ttlSec): void;
    public function forget(string $key): void;
}
```

`lock()`/`unlock()` with an `$owner` enable safe release (only the owner can release). Used for ordering locks and dedup. Without an owner, release is unconditional.

## Locker implementations

| Locker | Location | Notes |
|---|---|---|
| `InMemoryLocker` | `php-async-kernel-client/src/Lockers/` | Map-based, lazy-TTL. **Not thread-safe.** Supports both legacy `acquire()`/`release()` and owner-based `acquireWithTtl()`/`releaseWithOwner()`. |
| `CacheLocker` | `php-async-kernel-lib/src/Lockers/` | Laravel cache-based adapter. |
| `RedisLocker` | `php-async-kernel-client-redis/src/Lockers/` | Production Redis locker. |

## Queue adapters

`telegram-bot-lib/src/Outbound/Adapters/`:
- `InMemoryOutboundQueue` — tests / single-process. Channel `tg-outbound`, DLQ `tg-dlq:`.
- `LaravelQueueAdapter` — bridges to Laravel's queue (channel `tg-outbound`). Lacks `AtomicDlqQueueContract` — needs `dlqFallback`.
- `KernelCacheAdapter` — cache-backed queue + cache (non-atomic counters — see above).
- `RedisOutboundCache` — production atomic cache.

⚠️ **Code-quality note:** the file `RedisOutboundQueue.php` in this directory has a mangled class name (repeated suffix). This is a pre-existing issue — flag it to the maintainer; do not propagate the name.

Other adapters live in `php-async-kernel-client/src/Queue/Adapters/` (`CacheQueueAdapter`, `InMemoryQueueAdapter`, `LaravelQueueAdapter`, `QueueLaravelJob`) and `php-async-kernel-client-redis/src/Queue/` (`QueueRedisAdapter`). Registries: `OutboundQueueRegistry`, `QueueAdapterRegistry`, `CacheDriverRegistry`.

## The `Framework/Laravel/Laravel*Adapter` convention

When the library's internal driver differs from Laravel's equivalent, create a thin adapter in the library at `Framework/Laravel/Laravel*Adapter`:

- The adapter wraps a Laravel service (`CacheManager`, `Logger`, etc.) and exposes the library's contract.
- Example: `ASKCacheWrapper` (wraps Laravel `CacheManager` → `OutboundCacheContract`), `ASKLogWrapper` (wraps Laravel `Logger`).

> Note: `AGENTS.md` prescribes the `Framework/Laravel/` directory, but some adapters currently live in `Http/Laravel/` or `Outbound/Adapters/`. When adding a new adapter, prefer `Framework/Laravel/` for the framework-binding concern; leave `Outbound/Adapters/` for protocol/broker adapters (Redis, in-memory).

## Config-driven drivers

Inside the Laravel framework, read cache/locker/queue/transport drivers via `config()`:

```php
$transport = config('tg-outbound-daemon.daemon.transport'); // guzzle | curl-multi | ask-socket
```

The `HttpTransportContract` is selected via `HttpTransportRegistry` in `TelegramBotServiceProvider`. If the configured transport is unset, the registry throws `TgTechnicalException` — fail loud, not silent.
