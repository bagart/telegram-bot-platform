# Middleware Chain & Capability Interfaces

## The pipeline

`OutboundPipeline` is a PSR-15-style synchronous chain, void return, built with `array_reduce`. **First item in the array = outermost.**

```
ExpiryMiddleware → RetryBudgetMiddleware → RateLimitMiddleware → TelegramOutboundExecutor
```

(The last item is the executor, not a "middleware" semantically, but it implements the same `OutboundMiddleware` interface so it sits at the chain end.)

> **Ordering is NOT a middleware.** Strict per-chat ordering is handled by the queue implementation via `OutboundOrderingQueueContract`. The pipeline is simpler and faster for it — do not reintroduce an ordering middleware.

## OutboundMiddleware contract

```php
interface OutboundMiddleware
{
    /**
     * @param Closure(OutboundEnvelope): void $next Next middleware / final executor.
     */
    public function handle(OutboundEnvelope $envelope, Closure $next): void;
}
```

A middleware does exactly one of three things:

1. **Call `$next($envelope)`** — pass the task forward.
2. **Throw `OutboundRetryException`** — request a retry with delay.
3. **Throw `OutboundSkipException` or `OutboundBusinessErrorException`** — drop to DLQ.

It must NOT:
- Swallow exceptions silently.
- Catch `ASKInterruptException` (it must bubble).
- Return a value (the contract is `void`).

## Adding a new middleware

1. Implement `OutboundMiddleware` in a `final` class under `src/Outbound/`.
2. Decide its position in the chain — early middlewares see the task first and can short-circuit (e.g. `ExpiryMiddleware` drops stale tasks before rate-limiting is even checked).
3. Register it in the pipeline construction. The pipeline is built by `TgBotSetupFactory::resolveOutboundDeps()` (private) — the public `createOutboundPipeline()` / `createOutboundDaemonParts()` return the assembled chain. See `factory-and-daemon.md`.
4. Test with the existing patterns in `telegram-bot-lib/tests/Unit/Outbound/` (`ExpiryMiddlewareTest`, `RateLimitMiddlewareTest`, `RetryBudgetMiddlewareTest`).

## The executor (TelegramOutboundExecutor)

The last middleware. It actually calls the Telegram API and **classifies the result** inline — there is deliberately **no separate `ErrorClassifier`** class. Classification maps the API response/error to one of the three control exceptions:

- HTTP 429 / `TgApiRateLimitException` → `OutboundRetryException` (with `retry_after` as `delaySec`)
- HTTP 400/401/403/404 → `OutboundBusinessErrorException`
- Other `Throwable` → `OutboundRetryException` (transient) or rethrown

The daemon's `process()` then catches the thrown exception. Keep classification in the executor — splitting it out is an anti-pattern documented in the executor's own docblock.

## Capability interfaces

The base `OutboundQueueContract` is intentionally minimal. Optional capabilities are separate interfaces, checked via `instanceof` with explicit fallback:

| Capability | Methods | Used for |
|---|---|---|
| `LeaseRenewableQueueContract` | lease renew | `LeaseRenewer` (background renewal during long processing) |
| `AtomicDlqQueueContract` | `pushToDeadLetter`, `atomicFetchAndRemoveFromDlq`, `listDeadLetter`, `deadLetterSize` | DLQ operations (see queue-and-envelopes.md) |
| `OutboundOrderingQueueContract` | (ordering-aware push/pop) | Strict per-chat ordering via `orderingKey` |
| `ChannelDiscoverableQueueContract` | channel enumeration | Monitoring / CLI tools |
| `PurgeableQueueContract` | purge | `tg:outbound:tool` flush |

**The capability-check idiom** (from `TgOutboundDaemon::moveToDlq`):

```php
if ($this->queue instanceof AtomicDlqQueueContract) {
    $this->queue->pushToDeadLetter($envelope, $reason);
} elseif ($this->dlqFallback !== null) {
    ($this->dlqFallback)($envelope, $reason);
} else {
    // poison-pill log — never silent loss
}
```

This is the legitimate `instanceof` pattern: the capability is a declared contract with real callers, not duck-typing into a concrete class. See `async-kernel-development/rules/contracts.md` for the strict-contract rule.

## When a broker lacks a capability

Fallbacks must be **explicit and logged**:

- No `AtomicDlqQueueContract` → `dlqFallback` closure (or poison-pill log).
- No `OutboundOrderingQueueContract` → `TgOutboundDaemon` logs a warning at construction: *"ordering guarantees are not enforced"*. Tasks still process, just unordered.
- No `LeaseRenewableQueueContract` → the visibility timeout must be long enough to cover processing; otherwise tasks get re-popped mid-flight.

Never `throw new BadMethodCallException` at runtime for a missing capability — degrade gracefully.
