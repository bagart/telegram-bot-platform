# BackupStale — last verified backup older than 26h

## Symptom
- Alert: `BackupStale` (`time() - tg_backup_last_success_timestamp_seconds > 26h` for 30m, warning)
- Visible impact: none — this is an RPO breach in waiting; act before a real incident

## Diagnose
```bash
cmd/ops/backup-verify
ls -l deploy/monitoring/textfile/tg-backup.prom   # node_exporter textfile freshness
cmd/ops/status
```
- The metric comes from `cmd/ops/backup` via the textfile collector; a stale file usually means the schedule died, not that backups silently corrupt.
- Check: cron/systemd timer for `cmd/ops/backup`, disk space on dump target, offsite target reachability (`cmd/ops/backup-offsite`), PITR WAL archiving lag (docs/dr.md §2).

## Act
1. Run a manual backup now: `cmd/ops/backup`, then prove it: `cmd/ops/backup-verify`.
2. Fix the scheduler (timer unit, permissions, disk full) so the next cycle fires unattended.
3. If offsite push fails: restore connectivity/credentials to the object-lock target; until then local dumps are the only RPO — treat host as fragile.

## Verify
- `tg_backup_last_success_timestamp_seconds` updates after the next scheduled run; `cmd/ops/backup-verify` exits green.

## Escalation
- Owner: platform-team
- When to page a human: two consecutive stale days, or verify fails on the fresh dump (restore path is unproven — see docs/dr.md drill section)
