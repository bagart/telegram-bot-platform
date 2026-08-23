# Capacity Model (06-runtime-operations.md §53–§56)

Working assumptions for sizing the Telegram platform; revisit after the
first soak run (09 §55–§63) replaces estimates with measurements.

## 1. Load units

| Unit | Definition | Current reference |
|---|---|---|
| update | one Telegram incoming update processed end-to-end | p95 ≤ 200ms CPU-light |
| outbound task | one queued API send (envelope + middleware chain) | p95 ≤ 50ms + network |
| bot | isolated token, own queue slice `tg-outbound` | see tg_bots |

## 2. Per-bot Telegram ceilings (provider-imposed)

- ~30 msg/s aggregate, ~1 msg/s per chat, ~20/min per group.
- The outbound pipeline's rate limiter is authoritative; horizontal scaling
  NEVER increases per-bot throughput — it only adds failure tolerance.

## 3. Horizontal scaling rules

- Daemons are single-consumer per queue slice (singleton lease). Scale-out =
  more bots per host, not more daemons per bot.
- One host comfortably targets: 4 vCPU / 8GB → ~50 active bots, ~200
  update/s, outbound queue depth < 1000 steady-state.
- Redis: single instance until persistent queue ops > 20k/s (far beyond
  Telegram reality); monitor `tg_redis_latency_ms`.

## 4. Singleton guarantees (06 §56)

- Lease keys in Redis with TTL + renewal (`LeaseRenewer`); a crashed holder
  releases capacity within one lease period.
- Never run two daemon sets against the same Redis namespace; the lease is
  the enforcement, not convention.

## 5. Saturation signals → action

| Signal | Threshold | Action |
|---|---|---|
| `tg_outbound_queue_depth` | > 5000 sustained 10min | scale host / shed low-priority lanes (06 §43–§46) |
| `tg_dlq_depth_total` | > 500 | investigate before replay (`cmd/ops/queue`) |
| `proc_rss_mb` (watchdog) | > 1024 | restart window via runbooks/upgrade.md |
| `tg_redis_latency_ms` | > 20 sustained | check Redis host contention |

## 6. Review

Re-derive after each soak/bench cycle; record measured values here with a
date stamp instead of deleting estimates.
