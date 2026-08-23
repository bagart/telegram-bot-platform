# Service Level Objectives (SLO)

**Status:** Active
**Scope:** Telegram bot platform — critical components
**Origin:** SDD spec 09 §3 (file removed post-implementation — see git history / `docs/tasks/devops3.md` header)
**Metrics source:** `/health/metrics` (`tg_*` series, see `app/Http/Controllers/HealthController.php`)
**Alert wiring:** `tools/baseline/prometheus-alerts.example.yml`
**Review cadence:** quarterly, or after any capacity-affecting change

---

## 1. Definitions

An **SLI** is the measured quantity; an **SLO** is the target for that quantity
over a rolling window. Error budget = 100% − SLO.

Measurement windows: **rolling 30 days** for availability/error-rate SLOs,
**rolling 7 days** for latency and success-rate SLOs (faster feedback on the
platform's fast-moving paths).

Until the live Prometheus/Grafana stack is deployed (plan §3 "needs live
infra"), these values are the authoritative targets and are enforced by the
alert thresholds below where instrumentation exists.

## 2. Critical components & objectives

| # | Component | SLI | SLO | Window |
|---|-----------|-----|-----|--------|
| 1 | Update ingestion (webhook/poller) | Availability of `/tg/*` endpoints | ≥ 99.9% | 30d |
| 2 | Update ingestion | End-to-end processing latency p95 (update received → processor dispatched) | ≤ 5 s | 7d |
| 3 | Update processing | Processing success rate (updates fully processed without fatal error, `tg_failed_fatal_last` rate) | ≥ 99.0% | 7d |
| 4 | Outbound send pipeline | Send success rate excluding business errors (`tg_sent_last` vs `tg_business_error_last`) | ≥ 99.0% | 7d |
| 5 | Outbound send pipeline | Queue wait time p95 (enqueue → successful send) | ≤ 30 s | 7d |
| 6 | Outbound send pipeline | Dead-letter accumulation: `tg_dlq_depth_total == 0` sustained | ≥ 99.5% of time | 30d |
| 7 | Async kernel | Tick/scheduling latency p99 (scheduled tick time − actual start) | ≤ 100 ms | 7d |
| 8 | Async kernel workers | Worker execution time p95 per task class | ≤ 2 s | 7d |
| 9 | Database | Availability (`tg_db_up == 1`) | ≥ 99.95% | 30d |
| 10 | Database | Query latency p95 (Eloquent/DB slow-query threshold) | ≤ 50 ms | 7d |
| 11 | Redis | Availability (`tg_redis_up == 1`) | ≥ 99.95% | 30d |
| 12 | Redis | Operation latency p99 | ≤ 5 ms | 7d |
| 13 | Telegram API | Outbound API call latency p95 (request → Telegram ack) | ≤ 1 s | 7d |
| 14 | Admin HTTP surface | Availability of authenticated admin endpoints | ≥ 99.5% | 30d |

Latency percentiles are measured server-side, excluding time spent in
intentional backoff (rate-limit waits are a platform feature, not an SLI
violation).

## 3. Error budget policy

- Budget burn > 25% of the 7-day budget in 24h → investigate, no deploys of
  the affected component except fixes.
- Budget exhausted → reliability work takes priority over features until the
  weekly burn rate returns under budget.
- SLO breaches caused by upstream Telegram incidents are excluded after
  annotation in the incident record.

## 4. Alert ↔ SLO mapping

Alerts in `tools/baseline/prometheus-alerts.example.yml` implement the
fast-burn detection subset:

| Alert | Related SLO |
|-------|-------------|
| `PlatformDependencyDown` | #9, #11 availability |
| `DeadLettersPresent` | #6 dead-letter accumulation |
| `OutboundQueueGrowing` | #5 queue wait (depth proxy until queue-age series lands) |

New alerts must reference the SLO they protect; alerts without an SLO link
are rejected in review (09 §44–§51).

## 5. Known gaps

These SLOs await instrumentation before they can be reported automatically:

- #2, #7: scheduler WIP blocks MetricsContract wiring (see plan, 09 §7–§13).
- #13: Telegram API latency histogram not yet exported.
- Percentiles require the live Prometheus stack; current exposition provides
  gauges/counters only.
