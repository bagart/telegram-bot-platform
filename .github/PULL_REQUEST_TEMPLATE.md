<!--
Thanks for opening a PR! Fill the sections below. Delete anything that doesn't apply.
For reliability review, the checklist is mandatory for daemon / queue / middleware / tg_webhook changes.
Reference: .agents/skills/highload-stability/rules/checklist.md
-->

## Summary

<!-- What does this change do, and why? Link any relevant issue / RFC / todo doc. -->

## Change type

- [ ] Bug fix
- [ ] New feature
- [ ] Refactor (no behavior change)
- [ ] Docs / agent infrastructure
- [ ] Hot path (daemon / queue / middleware / webhook) — **fill the reliability checklist below**

## Verification

- [ ] `vendor/bin/pint --dirty --format agent` passes
- [ ] `php artisan test --compact --filter=<relevant test>` passes
- [ ] For library changes: `composer test` in the affected `misc/BAGArt/*` dir passes
- [ ] For frontend changes: `npm run build` succeeds (or note that `npm run dev` is needed)
- [ ] No `app/` changes unless explicitly justified (convention: dev in `misc/`)

## Reliability checklist (mandatory for hot-path changes)

See `.agents/skills/highload-stability/rules/checklist.md` for the full version with pass/fail tests.

- [ ] **Lazy connections** — no I/O in constructors; warm via `ASKWarmableContract::warm()`
- [ ] **Atomic counters** — `incrementWithTtl` via `RedisOutboundCache` (not `KernelCacheAdapter` in multi-worker)
- [ ] **Graceful shutdown** — implements `ASKShutdownAware` where relevant; `shutdown()` returns `false` until drained
- [ ] **DLQ strategy** — hopeless/business errors → DLQ; no silent drops; `MAX_REDELIVERIES=3` respected
- [ ] **Circuit breaker** — `allowsRequest()` checked; failures recorded
- [ ] **Idempotency / ordering** — `orderingKey` used where strict per-chat order matters; re-delivery is safe
- [ ] **Backpressure** — `pressure()` scaled to 100=design limit; no unbounded in-flight growth
- [ ] **Redis state purity** — only readonly DTOs + counters; no closures/connections/services
- [ ] **Flush on shutdown** — in-memory state (queue/cache/HTTP/stats) flushes to Redis
- [ ] **Visibility lease** — `visibilityTimeoutSec` > max processing time; `LeaseRenewer` used for long tasks
- [ ] **No swallowed exceptions** — `process()` catches the 3 business exceptions + `Throwable`; `ASKInterruptException` bubbles
- [ ] **Versioned deserialization** — `schemaVersion` bumped + `fromJsonV*()` added if DTO schema changed

## Notes for reviewers

<!-- Anything non-obvious? Edge cases? Follow-ups captured in a todo doc? -->
