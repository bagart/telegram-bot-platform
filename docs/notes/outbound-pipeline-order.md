# Outbound Pipeline Order — Ground Truth

> Resolves a long-standing drift between older docs and the actual code. Confirmed against `TgBotSetupFactory.php:527-530` and the `OutboundPipeline` / `OutboundMiddleware` docblocks on 2026-07-27.

## Actual middleware chain

```
ExpiryMiddleware → RetryBudgetMiddleware → RateLimitMiddleware → TelegramOutboundExecutor
```

Constructed in `TgBotSetupFactory::resolveOutboundDeps()` (the private method behind `createOutboundPipeline()` / `createOutboundDaemonParts()`):

```php
// TgBotSetupFactory.php:527-530
new ExpiryMiddleware($config->maxAgeSec, $config->minAttemptsForExpiry),
new RetryBudgetMiddleware($config->maxAttempts),
new RateLimitMiddleware($rateLimiter),
new TelegramOutboundExecutor(/* ... */),
```

First item in the array is the outermost middleware; the executor sits at the chain end.

## What was wrong in the docs

Older `AGENTS.md` and design notes described the chain as:

```
RetryPolicy → RateLimit → Ordering → Executor
```

…with an `OrderingMiddleware` slot. **Neither matches the code:**

1. There is no `RetryPolicy` middleware — it's `ExpiryMiddleware` (drops stale tasks) + `RetryBudgetMiddleware` (caps retry count) at the front.
2. **There is no `OrderingMiddleware`.** Strict per-chat ordering is **not** a pipeline concern — it's handled at the **queue/sender level** by `DefaultOrderingStrategy` and `OutboundOrderingQueueContract`. The pipeline is simpler and faster for it.
3. `RateLimit` is correctly placed, but it sits *after* the retry budget, not before.

## Why ordering lives at the queue, not in middleware

A middleware sees tasks in **pop order**, which is already too late to enforce ordering — the queue has already interleaved them. To guarantee per-chat FIFO, the ordering decision must happen at push/pop time, which is exactly what `OutboundOrderingQueueContract` + `DefaultOrderingStrategy` do (using `OutboundTask::$orderingKey`).

Adding an `OrderingMiddleware` would only reorder within a single worker's in-flight window — a weaker guarantee and a performance cost. The current design is deliberate.

## If you need to change the chain

1. Edit `TgBotSetupFactory::resolveOutboundDeps()` (private method).
2. Update the docblock in `OutboundPipeline.php` and `OutboundMiddleware.php`.
3. Update the skill: `.agents/skills/outbound-pipeline-development/rules/middleware.md`.
4. Update this file.

## TODO (open question from the original note)

The original drift note (now superseded by this file) floated the idea of *adding* an `OrderingMiddleware`. Per the analysis above, that's the wrong layer — don't add it. If stricter ordering is needed, extend `OutboundOrderingQueueContract` or `DefaultOrderingStrategy` instead.
