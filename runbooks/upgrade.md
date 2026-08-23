# Zero-Downtime Upgrade Runbook (06-runtime-operations.md §68)

## Symptom
- Planned platform upgrade (app or library) without user-visible downtime.

## Diagnose (pre-flight)
```bash
cmd/ops/readiness          # must PASS before any upgrade
git status                 # clean tree
cmd/ops/status             # baseline runtime state recorded
```

## Act
1. Build/promote the new artifact:
   ```bash
   cmd/release/promote <digest-or-tag> staging
   ```
2. Drain in-flight work so nothing is lost mid-deploy (ASK drains across
   STOPPING→DRAINING→FORCING):
   ```bash
   cmd/ops/drain [--containers]
   ```
3. Apply database migrations in expand mode only (no breaking contract in
   the same release as the code switch).
4. Switch code/artifact and restart services:
   ```bash
   cmd/ops/deploy --confirm=deploy    # consumes digest, runs readiness first
   ```
5. Contract-migrate (backfills) if any, then remove old columns in the NEXT
   release (expand/contract pattern).

## Verify
```bash
cmd/ops/health             # /health/live + /health/ready green
cmd/ops/probe              # synthetic getMe round-trip within budget
cmd/ops/readiness          # PASS again post-upgrade
```

## Rollback
```bash
cmd/ops/rollback --confirm=rollback   # previous known-good artifact
```
Rollback validity was proven at deploy time (previous artifact retained).

## Escalation
- Owner: platform-team
- Page a human if: drain does not finish within 10min, or readiness fails
  twice consecutively post-switch.
