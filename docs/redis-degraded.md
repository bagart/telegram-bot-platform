# Redis Degraded-Mode Behavior Matrix (06-runtime-operations.md §21)

What the platform does when Redis is degraded (slow, read-only, restarting,
fully down). Derived from the outbound architecture rules in `AGENTS.md`
(readonly DTOs in Redis, TTL leases, at-least-once sends).

| Component | Redis slow | Redis restarting | Redis down |
|---|---|---|---|
| Inbound webhook processing | accepted; enqueue latency rises — watch `tg_redis_latency_ms` | 5xx on webhook → Telegram retries delivery (webhooks are at-least-once) | same as restarting |
| Outbound queue `tg-outbound` | drains slower than intake → depth grows | paused; depth frozen, nothing lost beyond AOF window (`docs/dr.md` §4) | unavailable; daemons idle-loop with backoff |
| DLQ `tg-dlq:*` | readable/replayable | intact after restart (persisted keys) | unreachable; replay postponed |
| Leases / singleton locks | renewal may miss a beat — TTL margin absorbs it | expire naturally within one lease period; daemons re-acquire cleanly | all leases gone after expiry window; no split-brain because holders cannot make progress without Redis either |
| Stats counters (`incrementWithTtl`) | buffered per tick; minor loss possible | partial hour buckets may undercount — metrics are disposable by rule | zeroed views; never blocks sending decisions |
| Scheduler/daemons | continue; backpressure via `pressure()` | graceful: ASKInterruptException path shuts ticks down cleanly | exit non-zero; systemd `Restart=on-failure` re-spawns with backoff |

## Operator actions

1. Confirm scope: `cmd/ops/health`, `cmd/ops/status` (correlation id links
   runs), Redis container state.
2. Queue safety: no operator action needed for short outages — Telegram
   webhook redelivery + AOF cover the window.
3. After recovery: check `tg_outbound_queue_depth` drain rate and
   `tg_dlq_depth_total`; replay only if needed (`cmd/ops/replay --confirm=replay --count≤50`).
4. If daemons exited during the outage: they self-heal via supervision
   (`deploy/systemd/`); verify with `cmd/ops/probe`.

## Non-goals

Redis is never the source of truth for bot/user state (PostgreSQL owns it);
a total, unrecoverable Redis loss costs only queued-but-unsent messages and
metric history.
