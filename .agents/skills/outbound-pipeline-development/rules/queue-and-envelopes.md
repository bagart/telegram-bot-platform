# Queue Contract, Envelopes & Redis Channels

## OutboundQueueContract — the base broker contract

```php
interface OutboundQueueContract
{
    public function push(OutboundTask $task): void;
    public function pop(int $visibilityTimeoutSec = 60): ?OutboundEnvelope;
    public function ack(OutboundEnvelope $envelope): void;
    public function release(OutboundEnvelope $envelope, int $delaySec): void;
    public function size(): int;
}
```

Key points:
- **No string queue names in the base contract.** The channel is fixed = `tg-outbound`. Brokers implement exactly one outbound channel.
- **Visibility lease.** `pop()` hides the task from other workers for `$visibilityTimeoutSec`. On worker crash the lease expires and the task reappears — this is the crash-recovery mechanism. `ack()` removes it permanently; `release($envelope, $delaySec)` puts it back with a delay (for retries).
- **`size()`** = ready + delayed, **excluding** in-flight.

Extended features (lease renewal, DLQ, channel discovery, purge, ordering) are **capability interfaces**, checked via `instanceof` with explicit fallback. See `middleware.md` and `adapters.md`.

## The four DTOs

### OutboundTask (immutable payload, stored in Redis)

```php
public readonly string $id;                       // bin2hex(random_bytes(16))
public readonly TgBotConfig $botConfig;
public readonly string $dtoClass;                 // e.g. SendMessageMethodDTO::class
public readonly array $dtoData;                   // serialized DTO fields
public readonly TaskPriority $priority;           // enum: Normal / ...
public readonly ?string $orderingKey;             // chat_id:session_id — null = broadcast
public readonly DateTimeImmutable $createdAt;
public readonly int $schemaVersion;               // = 1
```

- `JsonSerializable` + `fromJson()` with **versioned** `fromJsonV1()`. When you change the schema, bump `schemaVersion` and add `fromJsonV2()` — never mutate `fromJsonV1()` (it must still decode old entries).
- `orderingKey` enforces strict per-chat ordering when the queue implements `OutboundOrderingQueueContract`. `null` means broadcast (no ordering).

### OutboundTaskState (mutable, per-delivery)

```php
public const string STATUS_PENDING = 'pending';
public const string STATUS_IN_PROGRESS = 'in_progress';
public const string STATUS_DELIVERED = 'delivered';
public const string STATUS_BUSINESS_ERROR = 'business_error';

private string $status;
private int $attempt;
private ?string $lastError;
private ?array $errorContext;
```

Methods: `markInProgress()`, `markDelivered()`, `markBusinessError($reason, $context)`, `incrementAttempt()`, `setRetryContext($reason, $context)`, `isTerminal()`. Versioned `fromArray()` → `fromArrayV1()`.

### OutboundEnvelope (in-flight wrapper, NOT persisted to Redis long-term)

Wraps `task` + `state` + a `deliveryId`. Lives only while a worker is processing. `pop()` returns one; `ack()`/`release()` consume it.

### DeadLetterEntry (DLQ record, stored in Redis)

```php
public const int MAX_REDELIVERIES = 3;

public readonly OutboundTask $task;
public readonly OutboundTaskState $state;
public readonly string $reason;
public readonly DateTimeImmutable $failedAt;
public int $redeliveryCount;
```

- `fromEnvelope(OutboundEnvelope, $reason)` — constructor at DLQ push time.
- `restoreEnvelope()` — rebuilds an `OutboundEnvelope` for re-delivery.
- `canRedeliver(int $max = MAX_REDELIVERIES): bool` — caps retry count. Exceeding → entry stays in DLQ permanently (manual intervention).
- Stored as JSON in channel `tg-dlq:{botId}`.

## Redis channel naming conventions

| Channel | Format | Used by |
|---|---|---|
| Main queue | `tg-outbound` | `LaravelQueueAdapter`, `InMemoryOutboundQueue` |
| DLQ | `tg-dlq:{botId}` | `DeadLetterEntry`, `AtomicDlqQueueContract` impls |
| Stats | `tg_outbound:stats:{YmdH}:...` | `TgOutboundStats` (hour-bucketed, TTL 168h) |

When adding a new channel, prefix with `tg-` (queue-style) or `tg_outbound:` (key-style for counters/cache). Don't invent new prefixes.

## Serialization rules

- All four DTOs are JSON. Use `json_encode(..., JSON_THROW_ON_ERROR)` and the matching `JSON_THROW_ON_ERROR` decode.
- `DateTimeImmutable` serializes as ISO-8601; restore via constructor in `fromJson*()`.
- Enums serialize as their `->value` (backed enums only — `TaskPriority` is backed).
- **Never** put a closure, resource, or object-with-behavior into the serialized payload. If a field looks like it needs one, the design is wrong — see `highload-stability`'s Redis-purity rules.

## Versioned deserialization pattern

```php
public static function fromJson(string $json): self
{
    $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    return match ($data['schemaVersion'] ?? 1) {
        1 => self::fromJsonV1($data),
        default => throw new \RuntimeException("Unknown schemaVersion: {$data['schemaVersion']}"),
    };
}
```

To evolve the schema: add `schemaVersion = 2` on new writes, add a `fromJsonV2()` branch, keep `fromJsonV1()` intact. Old entries in Redis must remain readable until they drain.
