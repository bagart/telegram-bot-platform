# PlatformDependencyDown — database or Redis unreachable

## Symptom
- Alert: `PlatformDependencyDown` (`tg_db_up == 0 or tg_redis_up == 0`, firing after 2m)
- Visible impact: bots stop processing updates; webhooks may 5xx; daemons degrade

## Diagnose
```bash
cmd/ops/status
cmd/ops/diagnose
cmd/ops/ps
```
- Distinguish which leg is down via `/health` JSON (`db_up` / `redis_up` series on `/health/metrics`).
- Common causes: process crashed, OOM kill, connection limit exhausted, credential/config drift after deploy, network/DNS between app host and service.

## Act
1. Check the service itself: `docker ps` (or systemd unit status) for postgres/redis containers.
2. Single crashed service: `cmd/ops/restart --confirm=restart`; do NOT loop-restart — two failed attempts means escalate.
3. Redis down: outbound queue and DLQ live in Redis — no data is lost while it is down, but nothing flows; restore before any manual queue surgery.
4. Postgres down: webhooks fail closed (token resolution needs DB) — expected; do not bypass validation.
5. If credentials were rotated: fix config first, then restart app once.

## Verify
- `/health` reports both flags true; `cmd/ops/queue` responds; alert auto-resolves within one scrape interval.

## Escalation
- Owner: platform-team
- When to page a human: either flag down >10m, or data-loss suspicion (Redis without persistence + crash)
