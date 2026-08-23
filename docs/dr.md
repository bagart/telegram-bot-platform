# Disaster Recovery & Backup Policy (07-resilience-and-disaster-recovery.md)

Single definition site for RPO/RTO targets, persistence semantics, recovery
procedures and scenario drills. `cmd/ops/*` tools and `docs/slo.md` reference
this document; values here are the contract.

Status: Active · v1.0 · 2026-08-22 · Owner: platform-team

## 1. Data classes, RPO/RTO targets (07 §3)

| Data class | Storage | Loss tolerance (RPO) | Resume target (RTO) | Mechanism |
|---|---|---|---|---|
| Bot/user relational state | PostgreSQL (`tg_bots`, users, modules) | ≤ 24h | ≤ 2h | nightly `cmd/ops/backup` + checksum + verify |
| Outbound queue / DLQ | Redis lists `tg-outbound`, `tg-dlq:*` | best effort¹ | ≤ 15min | AOF everysec; replay via `cmd/ops/replay` |
| Runtime leases/locks/metrics | Redis TTL keys | loss acceptable | n/a (self-healing) | none — rebuildable by design |
| Telegram chat context | provider side + local cache | ≤ 24h | ≤ 2h | follows DB backup |
| Configuration/secrets | `.env`, DB tokens column | ≤ 24h | ≤ 2h | `.env` in host secret store; tokens inside DB dumps |
| CI/GitHub state | GitHub | ≤ 7d | ≤ 1d | §5 below |

¹ Queue messages carry idempotency keys; a lost queue degrades to "messages
since last AOF fsync are re-delivered or dropped" — at-least-once semantics
are preserved for senders because sent-state is reconciled from DB.

## 2. Backup pipeline (07 §4–§6, §12–§15)

1. `cmd/ops/backup [keep]` — pg_dump through the compose service, gzip,
   sha256 sidecar, automatic verify (`cmd/ops/backup-verify`), retention
   default keep=7.
2. Freshness SLO: newest verified dump **≤ 26h** old. Enforced by
   `cmd/ops/readiness` (warning; `BASELINE_REQUIRE_BACKUP=1` promotes to
   blocking).
3. PITR: WAL archiving is shipped as a compose override
   (`deploy/postgres/pitr-compose.example.yml`) — archive_timeout 300s gives
   an effective RPO of ~5 minutes when enabled on the running stack. Without
   the override the effective RPO is the nightly dump cadence.

## 3. Off-site copies, immutability, encryption (07 §7–§11)

- Copy the newest dump off-site daily (crontab example ships an rsync line).
  Target must be on independent infrastructure (different provider/account).
- Immutability: enable object-lock/versioning on the bucket where available;
  until then retention relies on the source-side keep window plus the
  off-site copy lag (≤ 24h exposure window). Tracked in backlog §7–§9.
- Encryption at rest: `age`/`gpg` encrypt before off-site transfer when the
  target is not trusted; the decryption key lives ONLY in the platform
  password vault (separate recovery path — never next to the backups).

## 4. Redis persistence semantics (07 §16–§18)

- `appendonly yes`, `appendfsync everysec` — bounded loss window ≈ 1s of
  queue writes.
- Queue/DLQ keys are plain JSON DTOs (project rule: readonly state only), so
  a restore from RDB/AOF is schema-safe across versions.
- Leases are TTL-bound: after any Redis restart all leases self-expire
  within one lease period; daemons re-acquire. No operator action required.
- Metrics counters are disposable — never restore them at the cost of queue
  data.

## 5. GitHub / CI recovery (07 §27–§29)

- Repository = source of truth; a fresh clone + `cmd/dev/setup` rebuilds
  hooks/tooling deterministically (manifest-checked).
- Runner/caches are disposable: GH caches and artifacts auto-expire
  (`.github/workflows/maintenance.yml` prunes weekly).
- Restore plan: recreate repo settings from `.github/` (workflows,
  CODEOWNERS, dependabot, PR template); rulesets/branch protection are UI
  state — export screenshots quarterly into the vault.

## 6. Scenarios / tabletop drills (07 §38–§40)

| Scenario | Rehearsal command path | Cadence |
|---|---|---|
| Bad migration deployed | `cmd/ops/rollback` → verify health | quarterly |
| Postgres data loss | `cmd/ops/dr-test` then real `cmd/ops/restore --confirm=database` into staging | quarterly |
| Ransomware/encryptor | restore from immutable off-site copy per §3 | annual tabletop |
| Region/host loss | provision new host → `cmd/dev/setup` → restore → deploy | semi-annual |
| Redis flush | restart daemons; queues rebuild from DLQ/replay if present | tabletop |

A drill is complete only when §"Verify" of the matching runbook passes.

## 7. Completion criteria (07 §51)

DR counts as done when: this doc's values are enforced by tooling
(readiness gate), the latest drill report is linked here, and restore has
been executed end-to-end within the last 6 months.
