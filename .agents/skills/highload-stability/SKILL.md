---
name: highload-stability
description: "Apply as a cross-cutting review/audit skill for highload reliability — trigger when the user asks to review code for stability, harden it for production, check it is safe under load, audit a daemon/queue/middleware before merge, or reason about failure modes (crash recovery, poison pills, retry storms, lost tasks, idempotency, backpressure). Also trigger on keywords: highload, high-load, throughput, reliability, stability, hardening, production-ready, fail-safe, graceful degradation. Covers: the hardening checklist (lazy connections, atomic ops, graceful shutdown, DLQ, circuit breaker, idempotency, backpressure, Redis state purity, flush-on-shutdown), retry-vs-DLQ decision tree, lease renewal, partial-failure recovery. Use this skill ALONGSIDE the domain skills (async-kernel-development, outbound-pipeline-development) — it provides the checklist, they provide the mechanics. Do NOT use as a replacement for domain skills when actually implementing."
license: MIT
metadata:
  author: BAGArt
---

# Highload Stability

This is a **cross-cutting review skill**, not an implementation skill. Use it to audit code for production reliability before merge, or when diagnosing a failure mode. The domain skills (`async-kernel-development`, `outbound-pipeline-development`, `multi-bot-management`) explain *how* to implement; this skill explains *what to check*.

## When to Apply

- Before merging daemon / queue / middleware / webhook code.
- When the user asks "is this safe under load?", "will it survive a crash?", "what happens if Redis drops?".
- When diagnosing lost tasks, duplicate deliveries, retry storms, or stuck daemons.
- During a deliberate hardening pass.

## The Hardening Checklist

Run through `rules/checklist.md` for every change that touches the hot path. The short version:

| # | Concern | Quick check | Enforced how |
|---|---|---|---|
| 1 | Lazy connections | No I/O in constructors; warm via `ASKWarmableContract::warm()` | `tgbm:audit` (constructor-io) |
| 2 | Atomic counters | `incrementWithTtl` via `RedisOutboundCache` (Lua), NOT `KernelCacheAdapter` in multi-worker | `tgbm:audit` (non-atomic-counter) |
| 3 | Graceful shutdown | Implements `ASKShutdownAware`; correct `shutdownPriority()`; `shutdown()` returns `false` until drained | **arch-test** (implements + priority/timeout range) |
| 4 | DLQ strategy | Hopeless/business errors → DLQ via `AtomicDlqQueueContract`; `MAX_REDELIVERIES=3`; no silent drops | review |
| 5 | Circuit breaker | `OutboundCircuitBreaker` records success/failure; `allowsRequest()` checked before pop | review |
| 6 | Idempotency / ordering | `orderingKey` for strict per-chat ordering when needed; tasks safe to re-deliver | review |
| 7 | Backpressure | `pressure()` scaled to 100=design limit; kernel throttles on `systemPressure` | review |
| 8 | Redis state purity | Only readonly DTOs + counters; no closures/connections/services | **arch-test** (DTOs must not use Closure) |
| 9 | Flush on shutdown | Every in-memory component flushes (queue, cache, HTTP client, stats) | review |
| 10 | Visibility lease | `visibilityTimeoutSec` > max processing time; lease renewed for long tasks | review |
| 11 | No swallowed exceptions | Pipeline catches exactly the 3 business exceptions + `Throwable`; `ASKInterruptException` bubbles | `tgbm:audit` (swallowed-exceptions) |
| 12 | Versioned deserialization | `fromJsonV1()`/`fromArrayV1()` intact when schema evolves | review |

**"Enforced how"** column:
- **arch-test** — hard gate in `tests/Architecture/HighloadRulesTest.php`, runs in CI via pest.
- **`tgbm:audit`** — heuristic grep-based check, runs in CI via `php artisan tgbm:audit --strict` on the outbound hot path. Use `--all` for a broader (noisier) scan.
- **review** — not machine-checked; verify manually (or add an arch-test/audit rule when a reliable check exists).

Run `php artisan tgbm:audit` locally before merging outbound changes. CI runs `--strict` (fails the build on findings).

## Rule Files

| If reviewing… | Read this first |
|---|---|
| The full audit checklist with pass/fail criteria | `rules/checklist.md` |
| What may/may not live in Redis, callback→readonly-class extraction | `rules/redis-state-purity.md` |
| Retry-vs-DLQ decisions, poison pills, lease renewal, partial failure | `rules/failure-modes.md` |

## How to use this skill with the domain skills

1. Identify the component type (daemon / queue / middleware / webhook).
2. Activate the matching domain skill for implementation mechanics.
3. Run **this** skill's checklist against the change.
4. For each failed check, defer to the domain skill's "Common Pitfalls" for the fix.

## Failure-mode quick triage

When something goes wrong in production, the symptom usually maps to a specific checklist item:

| Symptom | Likely checklist failure | See |
|---|---|---|
| Duplicate deliveries | #10 (lease too short) or #6 (no ordering) | `failure-modes.md` |
| Lost tasks | #4 (silent drop) or #8 (closures in Redis) | `redis-state-purity.md` |
| Retry storm | #5 (no breaker) or backoff misconfigured | `failure-modes.md` |
| Daemon hangs on shutdown | #3 (premature `true`) or scheduler omitted | `checklist.md` |
| Stuck tasks (never process) | scheduler not in `tickable()` | `async-kernel/daemons.md` |
| Counter drift under load | #2 (non-atomic incr) | `checklist.md` |
