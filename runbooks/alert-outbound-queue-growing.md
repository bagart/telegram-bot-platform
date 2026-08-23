# OutboundQueueGrowing — outbound queue depth sustained high

## Symptom
- Alert: `OutboundQueueGrowing` (`tg_outbound_queue_depth > 500` for 10m, warning)
- Visible impact: delayed messages/replies; users notice lag before anything breaks

## Diagnose
```bash
cmd/ops/queue
cmd/ops/metrics | grep -E 'outbound|circuit'
cmd/ops/ps
```
- Depth alone is not age — check how old the head-of-queue tasks are before paging anyone.
- Usual suspects: outbound daemon not running (`tgbm:outbound-daemon`), circuit breaker OPEN against Telegram, sustained 429 rate limiting, burst (broadcast) larger than drain rate.

## Act
1. Daemon dead: start it (`tgbm:outbound-daemon`); if supervised, check why supervise did not revive it.
2. Circuit breaker OPEN: find the underlying error class first (usually Telegram-side); the breaker will half-open on its own — do not force-flush through an open breaker.
3. Rate limiting: reduce concurrency/burst source; broadcasts must respect the rate-limit middleware budget.
4. Sustained overload: shed non-priority lanes rather than letting latency grow unbounded (degradation lanes are the planned long-term fix, see devops3 §06 §43–§46).

## Verify
- Depth monotonically decreasing across several scrapes and below threshold; no growth of `tg_dlq_depth_total` as a side effect.

## Escalation
- Owner: platform-team
- When to page a human: depth still rising 30m after mitigation, or queue age exceeds user-visible SLA
