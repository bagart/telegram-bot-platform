07 — Resilience & Disaster Recovery
Status: Draft
Version: 0.2
Owner: platform-team
Updated: 2026-08-21
Depends on: 01-architecture-and-baseline.md, 02-developer-tooling.md, 03-security-and-supply-chain.md, 04-qa-and-testing.md, 05-ci-cd-and-release.md, 06-runtime-operations.md
Implementation: 11-implementation-and-rollout.md

## 1. Goal

Ensure that:

Any critical production state can be recovered after deletion, corruption, hardware failure, ransomware/credential compromise, or complete infrastructure failure.

Main principle:

backup ≠ recovery

A system is considered protected only if:

backup
↓
restore
↓
verify

is verified automatically.

Single-home note: backup, restore verification, RPO/RTO and DR drills are specified in this document; documents 04, 05 and 06 reference this document and must not re-specify them.

## 2. Data Classification

All data is split by criticality:

critical
important
rebuildable
ephemeral

Critical

For example:

PostgreSQL production data
bot configuration
security configuration
persistent application state

Important

For example:

operational metadata
historical data
non-critical configuration

Rebuildable

Docker images
Composer vendor
frontend dist
generated caches

Ephemeral

runtime cache
temporary files
worker state

## 3. Recovery Objectives

For every critical dataset define:

RPO
RTO

RPO

How much data may be lost.

RTO

How fast the system must recover.

Do not use one global RPO/RTO for the whole platform.

## 4. Backup Policy

For every datastore define:

frequency
retention
storage
encryption
verification
restore procedure

For example:

PostgreSQL
→ continuous/WAL
→ daily full
→ periodic snapshot

## 5. PostgreSQL

For PostgreSQL the baseline must support:

logical backup
physical backup
WAL archiving
point-in-time recovery

The concrete mode is chosen based on the infrastructure.

## 6. PostgreSQL PITR

Goal:

database
↓
failure at 14:37
↓
restore
↓
14:36:59

and not only:

last nightly backup

## 7. Backup Storage

Backups must not be stored only next to the production database.

Minimum:

production
↓
backup
↓
independent storage

It is desirable to have:

different failure domain

## 8. Backup Isolation

Production credentials must not automatically allow:

delete all backups

Backup storage must have separate permissions.

## 9. Immutable Backups

For critical backups it is desirable to use:

immutability
retention lock
object versioning

so that compromised production credentials cannot destroy the backup history.

## 10. Encryption

Backups must be encrypted:

at rest

and during transmission:

in transit

Keys must be separated from the backup artifacts themselves.

## 11. Backup Secrets

Backup credentials:

must not be stored in Git

and must not be shared with application credentials.

## 12. Backup Verification

After a backup is created, automatically verify:

file/object exists
size sane
checksum
metadata
encryption

But this is not enough.

## 13. Restore Test

Periodically perform:

backup
↓
isolated environment
↓
restore
↓
integrity checks
↓
application startup
↓
queries

It is the restore test that proves a backup is usable.

## 14. Automated Restore Verification

For PostgreSQL after restore verify:

tables
indexes
constraints
row sanity
critical queries
migration state
application connectivity

## 15. Data Integrity

The backup system must detect corruption.

Use:

checksums
backup verification
database integrity checks
restore verification

## 16. Redis

Split Redis into:

persistent state
cache
queue
ephemeral runtime state

Do not treat all of Redis as equally important.

## 17. Redis Cache

If the data is cache:

backup not required

if it is fully rebuildable.

This reduces:

backup size
complexity
restore time

## 18. Redis Queues

Define semantics for every queue.

If a queue contains recoverable work:

RPO

may differ from the database.

For critical queues define a recovery strategy:

replay
rebuild
drop

## 19. Telegram Update State

If the runtime uses state for Telegram processing:

offsets
deduplication
locks
scheduled jobs
pending work

classify it explicitly.

The following must not be allowed:

restore database
+
lost queue
=
duplicate side effects

without defined semantics.

## 20. Idempotency

Critical Telegram operations must be as idempotent as possible.

For example:

update
↓
processing
↓
crash
↓
retry

must not lead to an uncontrolled repeated side effect.

## 21. Deduplication

For operations where repetition is possible:

update_id
event_id
job_id
idempotency_key

can be used for deduplication.

## 22. Recovery Ordering

After a disaster you cannot simply start everything at once.

Basic order:

infrastructure
↓
storage
↓
database
↓
Redis/state
↓
configuration
↓
application
↓
workers
↓
Telegram processing

## 23. Application Recovery

The application must be able to start on restored data without manual editing of production files.

## 24. Configuration Backup

Store/recover:

application configuration
runtime configuration
deployment configuration
database configuration
queue configuration

But secrets must be restored via the secret manager, not as plaintext backup.

## 25. Secrets Recovery

Document:

secret source
owner
rotation
recovery

for every critical secret.

## 26. Secret Rotation

After a security incident there must be a procedure:

compromise
↓
revoke
↓
rotate
↓
redeploy
↓
verify

## 27. GitHub Recovery

The backup/DR plan must account for:

repositories
branches
tags
rulesets
CODEOWNERS
Actions workflows
environment configuration
deployment configuration

A Git repository by itself does not guarantee recovery of the GitHub platform configuration.

## 28. Repository Recovery

Critical repositories must be recoverable from:

Git mirror

or another independent backup.

## 29. CI/CD Recovery

After a full GitHub/infrastructure failure it must be possible to restore:

source
baseline
CI
artifact
deployment configuration

without manually restoring dozens of settings.

## 30. Artifact Recovery

A production image/artifact must not depend solely on the ability to rebuild it from the current repository.

Store:

immutable artifacts

with a retention policy.

## 31. Release Metadata

Preserve the linkage:

release
↓
artifact digest
↓
commit
↓
SBOM
↓
provenance

## 32. Docker Registry Failure

When the registry is unavailable, define:

fallback registry

or a retention policy sufficient for recovery.

Do not assume that:

docker build

can always reproduce the production artifact identically.

## 33. Backup Retention

Retention must account for:

daily
weekly
monthly
incident
release

For critical data a useful scheme is:

short-term frequent
+
long-term periodic

## 34. Incident Backups

Before dangerous operations create a recovery point:

major migration
major upgrade
destructive maintenance

For example:

DB migration
↓
verified backup
↓
migration

## 35. Migration Safety

Database migrations must account for:

backup
rollback
compatibility
data transformation

Especially:

Async Kernel
Telegram state
bot configuration

if the schema is tied to the runtime.

## 36. Restore Environments

Restore tests must not break production.

Use:

isolated environment

with separate:

network
credentials
Telegram credentials
external integrations

## 37. Telegram Safety During Restore

A restore environment must not accidentally start sending real Telegram messages.

By default:

real bot tokens unavailable

or:

outbound Telegram disabled

## 38. Disaster Scenarios

Model at minimum:

database corruption
database deletion
Redis loss
server loss
disk loss
container loss
registry loss
GitHub outage
credential compromise
bad deployment
bad migration
accidental deletion

## 39. Recovery Scenarios

For each:

detect
↓
contain
↓
restore
↓
verify
↓
resume

## 40. Ransomware / Credential Compromise

Backups must survive the scenario:

production credentials compromised

That is, an attacker must not be able to delete:

all backups

at the same time as production.

## 41. Backup Monitoring

Monitor:

last successful backup
backup age
backup size
backup failures
storage usage
restore test status

## 42. Backup Alerting

Critical alert if:

backup missing
backup too old
verification failed
storage nearly full
restore test failed

## 43. Backup Freshness

Instead of:

backup job succeeded

what matters more is:

last verified recoverable backup = X

## 44. Restore SLO

Define:

maximum acceptable restore time

and periodically check it against a real restore test.

## 45. DR Drill

Periodically run a controlled disaster drill:

destroy isolated environment
↓
restore
↓
deploy
↓
verify

The result is recorded.

## 46. Chaos / Failure Testing

For the runtime and DR, controlled failures are useful:

kill worker
drop Redis
restart database
network latency
packet loss
dependency unavailable

But run them only in an isolated/non-production environment or with an explicitly limited blast radius.

## 47. Recovery Automation

Create a single operational command/API:

cmd/ops/backup
cmd/ops/backup-verify
cmd/ops/restore
cmd/ops/dr-test

Commands must show:

what
where
when
risk

before a destructive operation.

## 48. Restore Safety

Restoring production data must require explicit confirmation.

For example:

cmd/ops/restore --target=production

must not be run accidentally.

## 49. Restore Idempotency

A repeated restore of the same backup must have a predictable result.

## 50. Post-Restore Verification

After recovery automatically verify:

database
Redis
application
workers
queues
Telegram connectivity
health
metrics

## 51. Recovery Completion

DR is considered complete only when:

restore
↓
application ready
↓
workers ready
↓
queues healthy
↓
critical Telegram flow verified

and not when:

docker compose up

has completed successfully.

## 52. Backup Cost Control

Do not back up what can be safely rebuilt.

For example:

vendor/
node_modules/
dist/
Docker build cache
temporary logs

if the source/artifact pipeline allows restoring them.

## 53. Logs

Logs must have a separate retention policy.

Critical audit/security logs must not be lost together with the production disk.

## 54. Audit Data

Store security/audit events separately from regular application logs where necessary.

Especially:

authentication
authorization
secret changes
deployment
administrative actions

## 55. Backup of Backup Configuration

The backup system configuration itself must be version-controlled or reproducible.

Otherwise a disaster may destroy not only the data but also the knowledge of how to restore it.

## 56. Documentation

For every critical datastore keep a short runbook:

backup location
backup format
restore command
verification
RPO
RTO
owner
failure modes

## 57. No Single Point of Recovery

You must not have:

one backup
one storage
one credential
one operator

as the only recovery path.

## 58. Acceptance Criteria

A component is ready when:

critical data is classified;
RPO/RTO are defined;
PostgreSQL has verified backup;
PITR is provided where needed;
backups are stored separately from production;
critical backups are protected from deletion;
backups are encrypted;
backup freshness is monitored;
restore tests are automated;
Redis state is classified;
queue recovery semantics are defined;
Telegram processing idempotency/deduplication are defined;
application/configuration recovery is reproducible;
GitHub/CI configuration recovery is provided;
immutable artifacts are retained;
secrets have a separate recovery/rotation path;
disaster scenarios are documented;
a DR drill is performed periodically;
restore ends with automated verification;
recovery commands are standardized.

## 59. Architectural Invariants

### DR-01 — Backup is not recovery

backup ≠ proof of recoverability

### DR-02 — Every critical dataset has RPO/RTO

### DR-03 — Production cannot destroy all backups

### DR-04 — Restore is tested, not assumed

### DR-05 — Rebuildable data is not unnecessarily backed up

### DR-06 — Recovery is automated where safe

### DR-07 — Recovery must be observable

### DR-08 — Restore cannot accidentally affect external systems

### DR-09 — Runtime recovery preserves delivery semantics

Especially important for:

Telegram updates
queues
async jobs

### DR-10 — One immutable artifact can be recovered independently of a live build pipeline

## 60. Final Model

    PRODUCTION
    │
    ┌──────────────┼──────────────┐
    ▼              ▼              ▼
    DATABASE        STATE          ARTIFACTS
    │              │              │
    └──────────────┼──────────────┘
    ▼
    BACKUP
    │
    ┌───────────┴───────────┐
    ▼                       ▼
    VERIFY                    STORE
    │
    isolated /
    immutable
    │
    ▼
    DISASTER EVENT
    │
    ▼
    RESTORE
    │
    ┌───────────────┼───────────────┐
    ▼               ▼               ▼
    DATA           RUNTIME          ARTIFACT
    │               │               │
    └───────────────┼───────────────┘
    ▼
    VERIFY
    │
    ┌──────────┴──────────┐
    ▼                     ▼
    READY                 FAIL
    │                     │
    ▼                     ▼
    RESUME                ALERT

Main idea: for your platform, DR must not be reduced to "making a PostgreSQL dump". The integrity of the entire chain Telegram state → queues → Async Kernel → application state → database → artifact must be preserved, and it must be guaranteed that after a serious failure the system can recover automatically to a verified working state.
