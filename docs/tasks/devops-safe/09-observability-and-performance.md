09 — Observability & Performance
Status: Draft
Version: 0.2
Owner: platform-team
Updated: 2026-08-21
Depends on: 01-architecture-and-baseline.md, 02-developer-tooling.md, 03-security-and-supply-chain.md, 04-qa-and-testing.md, 05-ci-cd-and-release.md, 06-runtime-operations.md, 07-resilience-and-disaster-recovery.md, 08-developer-experience-and-ai.md
Implementation: 11-implementation-and-rollout.md

## 1. Goal

The system must show by itself:

what broke, where, why, how much it affects users, and what to do next.

Core principle:

observe → detect → diagnose → recover → learn

Do not build observability as a set of dashboards. It must be part of the runtime architecture.

## 2. Golden Signals

For every production service, at minimum:

latency
traffic
errors
saturation

Additionally for the Telegram platform:

updates
processing latency
queue depth
delivery failures
retries
rate limits

## 3. SLI / SLO

Define for critical components:

availability
latency
error rate
processing success
queue delay

Examples:

Telegram update processing success
Telegram API latency
Async Kernel scheduling latency
queue wait time
worker execution time
database query latency

## 4. Do Not Measure Only HTTP

A typical web application:

request
→ response

is not sufficient for the platform.

The main workload:

Telegram update
↓
poller/webhook
↓
Async Kernel
↓
processor
↓
queue
↓
external API
↓
side effect

Therefore observability must span the entire update lifecycle.

## 5. Correlation ID

Correlation-ID propagation rules are defined in this document; other documents reference them.

Every significant operation must have a correlation context:

request_id
update_id
bot_id
job_id
trace_id

where applicable.

This allows:

Telegram update
→ processor
→ async task
→ HTTP request
→ DB query

to be linked into a single diagnostic chain.

## 6. Telegram Update Lifecycle

Standard timestamps:

received_at
queued_at
started_at
completed_at
failed_at

Derived from them:

ingestion latency
queue latency
processing latency
total latency

## 7. Async Kernel Observability

For the Async Kernel it is mandatory to measure:

active fibers
ready fibers
scheduled tasks
queue depth
event-loop iterations
poll wait time
callback latency
task execution time

## 8. Event Loop Health

Detect:

event-loop starvation

for example:

expected scheduling interval: 10ms
actual: 800ms

This is one of the most important signals for the async runtime.

## 9. Blocking Detection

The Async Kernel must be able to detect suspiciously long synchronous sections:

fiber
↓
CPU/blocking operation
↓
event loop stalled

Minimum:

long task duration
event-loop lag

In debug/staging, more expensive instrumentation may be used.

## 10. Scheduler Metrics

Measure:

scheduler_run_total
scheduler_task_total
scheduler_task_duration
scheduler_queue_depth
scheduler_lag
scheduler_errors

Do not create a huge number of labels.

## 11. Cardinality Control

Never use as unrestricted metric labels:

user_id
chat_id
message_id
update_id
request URL with IDs
exception message

Otherwise the metrics backend can be destroyed by a cardinality explosion.

## 12. Metrics Dimensions

Allowed dimensions must be limited:

bot
operation
processor
transport
status
error_type
dependency

Moreover:

bot

must also have a configurable cardinality policy.

## 13. Metrics

Minimal categories:

runtime
application
telegram
queue
database
cache
HTTP
Docker/container
host
deployment

## 14. Runtime Metrics

Examples:

process_cpu
process_memory
event_loop_lag
fiber_count
task_count
worker_count
restart_count

## 15. Queue Metrics

For every critical queue:

queue_depth
queue_oldest_age
enqueue_rate
dequeue_rate
processing_rate
retry_rate
dead_letter_count

## 16. Queue Health

Especially important:

oldest_job_age

Because:

queue_depth = 10

can be normal if jobs are processed quickly.

While:

queue_depth = 2
oldest_job_age = 15 min

can be a serious incident.

## 17. Worker Health

For a worker:

started
healthy
busy
idle
draining
failed

Metrics:

jobs_processed
jobs_failed
jobs_retried
job_duration
worker_restarts

## 18. Telegram API

Measure:

request count
latency
status
Telegram error
retry
429
timeout
network error

Separately:

429 rate

and:

retry-after

## 19. Telegram Rate Limits

Do not simply count errors.

Track:

rate-limit events
per bot
per method

and distinguish:

normal throttling
sustained throttling
runaway traffic

## 20. External Dependencies

For every critical dependency:

availability
latency
error rate
timeout rate
retry rate

Examples:

Telegram
PostgreSQL
Redis
HTTP APIs
DNS

## 21. Dependency Health

Health status must distinguish:

healthy
degraded
unavailable
unknown

Do not do:

Redis unavailable → application instantly "dead"

when Redis is an optional/cache dependency.

## 22. Health Endpoints

Paths are defined in `06-runtime-operations.md` (§39): `/health/live`, `/health/ready`, `/health` (deep, authenticated). This section covers their observability behavior only.

Liveness

The process is alive.

Readiness

The process can accept work.

Health

Extended diagnostics.

## 23. Health Checks

Readiness may check:

database
Redis
required dependencies
runtime
configuration

But do not perform expensive checks on every health request.

## 24. Startup Validation

At startup, check:

configuration
required extensions
database connectivity
Redis connectivity
Telegram configuration
queue configuration

Errors must be understandable.

## 25. Graceful Shutdown

Async workers must support:

SIGTERM
↓
stop accepting new work
↓
finish/drain current work
↓
flush telemetry
↓
exit

with a timeout.

## 26. Worker Stuck Detection

If a worker is:

alive

but does not process jobs:

healthy ≠ process exists

a stuck state must be detected.

## 27. Heartbeats

Critical long-running processes:

worker
scheduler
poller
processor

publish a heartbeat.

Checked:

last_seen

## 28. Logs

Logs must be structured JSON in production.

Minimum:

timestamp
level
service
environment
message
trace_id
request_id
bot_id
operation
error_type

where applicable.

## 29. Log Levels

Standardize:

debug
info
notice
warning
error
critical

Production default:

info

but debug must be enabled dynamically/locally without changing application code.

## 30. Do Not Log Secrets

Never write:

bot token
API key
password
session token
Authorization header
cookies
private credentials

## 31. PII / Sensitive Data

Do not log unless necessary:

message contents
personal data
full user profiles
raw authorization data

Telegram payloads must be redacted or limited.

## 32. Error Logging

An exception log must contain:

exception class
safe message
location
trace/correlation ID
context

but not credentials/secrets.

## 33. Error Fingerprinting

Group identical errors by:

exception type
normalized location
operation

so that:

10,000 identical exceptions

do not look like:

10,000 separate incidents

## 34. Logs vs Metrics

Do not use logs instead of metrics.

For example:

Bad:

log "queue has 50 jobs"

Better:

queue_depth = 50

and log only for diagnostic events.

## 35. Tracing

Use tracing for critical distributed flows:

Telegram update
↓
processor
↓
Async task
↓
Redis
↓
HTTP
↓
PostgreSQL

## 36. Async Context Propagation

Trace/context must propagate correctly through:

Fiber
task
queue
retry
HTTP request
worker

This is especially important for the Async Kernel.

## 37. Trace Sampling

Do not store 100% of traces forever.

Use:

normal sampling
+
100% errors
+
slow requests
+
selected critical operations

## 38. Slow Operation Detection

Automatically mark:

slow DB query
slow HTTP request
slow Telegram API
slow processor
slow queue job
slow fiber

Threshold is configurable.

## 39. Database Observability

PostgreSQL:

connections
active queries
query latency
slow queries
locks
deadlocks
transactions
pool saturation
disk usage
replication lag

where applicable.

## 40. Redis Observability

memory
connections
commands
latency
evictions
blocked clients
queue depth
replication
persistence

## 41. Resource Saturation

Monitor:

CPU
RAM
disk
IOPS
network
file descriptors
connections
worker capacity

## 42. Capacity Signals

The system must show in advance:

approaching CPU saturation
memory pressure
disk exhaustion
queue growth
DB connection exhaustion

instead of waiting for a failure.

## 43. Autoscaling Signals

If the deployment supports scaling, use real workload metrics:

queue age
queue depth
CPU
request rate
processing latency

rather than CPU only.

## 44. Alerting Philosophy

Do not send an alert for every error.

An alert must mean:

human intervention or automated remediation is needed.

## 45. Alert Severity

Alert severity classes are defined in this document, platform-wide.

Minimum:

INFO
WARNING
CRITICAL

## 46. Critical Alerts

Examples:

service unavailable
database unavailable
queue stuck
Telegram processing stopped
backup failure
security incident
certificate expiration imminent
disk exhaustion

## 47. Warning

Examples:

latency degradation
memory growth
queue growth
dependency degradation
increasing retries

## 48. Alert Deduplication

If:

500 workers

break at the same time, do not send 500 alerts.

Group the incident by:

root cause

## 49. Alert Routing

Split by:

application
infrastructure
security
database
Telegram
deployment
backup

## 50. Alert Context

Every alert must contain:

what
impact
when
affected component
current value
threshold
likely cause
runbook

## 51. Runbooks

Every actionable alert must have a runbook:

alert
↓
diagnosis
↓
safe actions
↓
recovery
↓
escalation

## 52. Automated Remediation

Safe remediation can be automated:

restart unhealthy worker
drain worker
recreate crashed container
rotate failed instance
clear known-safe temporary state

But destructive actions require an explicit policy.

## 53. Restart Protection

Automatic restart must not create:

crash loop

Use:

backoff
restart limit
circuit breaker

## 54. Circuit Breakers

For external dependencies:

normal
↓
errors increase
↓
open
↓
cooldown
↓
half-open
↓
healthy → closed

## 55. Retry Policy

Every retryable operation must have:

max attempts
backoff
jitter
timeout
retryable errors

Must not do:

retry forever

## 56. Retry Storm Protection

Control:

retry rate
retry amplification
queue growth
dependency load

Under degradation, retries must not destroy the dependency itself.

## 57. Timeouts

Every external operation must have a timeout:

DNS
connect
TLS
read
total
queue
job
database

Do not use unlimited waits.

## 58. Performance Budget

Sections §58–§63 (budget, regression, benchmarks, load, soak, leak detection) are the platform's performance/load specification; document 04 references them instead of duplicating them.

Define budgets for critical operations:

Telegram update processing
Async scheduler
HTTP transport
database
queue

## 59. Performance Regression

The CI/benchmark pipeline must detect significant regressions:

latency
throughput
memory
CPU

Especially for:

Async Kernel
async TG-lib
transports
scheduler

## 60. Async Kernel Benchmark

Minimal benchmark suite:

task scheduling
fiber switching
timer scheduling
I/O wait
concurrent HTTP
queue processing
cancellation
timeouts

## 61. Load Testing

Periodically run controlled load tests:

updates/sec
concurrent bots
concurrent HTTP
queue throughput

Goal:

find saturation point

not just to produce a nice benchmark.

## 62. Soak Testing

For the Async Kernel:

hours

continuous workload.

Check for:

memory leaks
fiber leaks
task leaks
FD leaks
connection leaks
queue growth
latency drift

## 63. Leak Detection

Long-running workers must be monitored for:

memory
file descriptors
connections
fibers/tasks

If a resource grows monotonically — alert.

## 64. Deployment Observability

Every deployment must record:

version
commit
artifact digest
deployment time
operator/automation

## 65. Deployment Correlation

After a deployment, automatically compare:

before
vs
after

on:

error rate
latency
CPU
memory
queue
Telegram failures

## 66. Deployment Guard

If after a deploy:

error rate ↑
latency ↑
health ↓

the system must:

alert

and, if policy allows:

rollback

## 67. Canary

For critical runtime components it is desirable:

small traffic
↓
observe
↓
expand

instead of an instant 100% rollout.

## 68. Rollback

Rollback must be:

fast
deterministic
tested

And use an immutable artifact:

image@sha256:...

## 69. Incident Mode

There must be a single way to enable enhanced diagnostics:

debug/incident mode

without code changes and without a full redeploy, if the architecture allows.

## 70. Incident Mode Safety

Do not allow:

logging secrets
unbounded debug
unlimited trace sampling

Incident mode must have:

TTL

and turn off automatically.

## 71. SRE Dashboard

Minimal dashboard set:

Platform Overview
Telegram
Async Runtime
Queues
Database
Redis
Infrastructure
Deployments
Security

## 72. Platform Overview

One screen:

availability
error rate
latency
queue health
Telegram health
database health
Redis health
active incidents
recent deployments

## 73. Telegram Dashboard

updates/sec
processing latency
failed updates
429
Telegram API latency
queue age
worker health

## 74. Async Runtime Dashboard

event loop lag
active fibers
tasks
scheduler queue
task duration
timeouts
cancellations
worker restarts
memory

## 75. SRE Data Retention

Define retention separately for:

metrics
logs
traces
audit
security events

Do not store everything equally long.

## 76. Observability Cost Control

Monitor:

metric cardinality
log volume
trace volume
storage cost

Observability itself must not become a production bottleneck.

## 77. Self-Monitoring

The monitoring system must monitor its own health:

collector health
storage
ingestion failures
alert delivery
missing telemetry

## 78. Missing Telemetry Detection

It is important to distinguish:

service healthy

from:

service stopped sending metrics

The latter is itself an incident.

## 79. Synthetic Checks

Use synthetic tests for the critical Telegram flow:

test bot
↓
send update
↓
platform processes
↓
expected result

This checks not a single service but the real business path.

## 80. End-to-End SRE Check

Minimal synthetic flow:

Telegram update
↓
ingestion
↓
Async Kernel
↓
processor
↓
Telegram API
↓
success

## 81. Reliability Testing

Test periodically:

worker crash
Redis restart
DB restart
Telegram unavailable
network timeout
queue overload
deployment rollback

## 82. Failure Budget

For SLOs use:

error budget

If the budget is exhausted:

new risky changes

may automatically fall under a stricter review/release policy.

## 83. Operational Readiness

A new critical component must not be considered production-ready without:

metrics
logs
health check
alerts
runbook
SLO
failure handling
rollback

## 84. Observability Contract

Every service/module must define:

health
metrics
logs
traces
failure modes
alerts

## 85. Library Observability

Core libraries must not depend on a specific monitoring vendor.

Use an abstraction:

Logger
Metrics
Tracer
Clock

or a compatible standard interface.

## 86. Async Library Constraint

The Async Kernel must not:

directly depend on Prometheus
directly depend on Grafana
directly depend on Sentry

Instrumentation is connected via an adapter/integration layer.

## 87. Zero-Overhead When Disabled

In production-critical hot paths, instrumentation must be cheap.

Especially:

fiber scheduling
task queue
HTTP transport
Telegram updates

## 88. Sampling / Aggregation

Instead of creating a telemetry event for every little thing, use:

counters
histograms
sampling
aggregation

## 89. Observability Testing

CI must verify:

metrics emitted
health endpoint works
structured logs valid
trace context propagated

for critical components.

## 90. Acceptance Criteria

A component is ready when:

SLI/SLO are defined for critical services;
golden signals exist;
the Telegram update lifecycle is measured;
the Async Kernel has its own telemetry;
event-loop lag is measured;
queue age/depth are controlled;
worker stuck detection exists;
structured logs are standardized;
secrets/PII do not get into logs;
correlation/trace context propagates through async boundaries;
external dependencies have timeout/retry metrics;
health/readiness are standardized;
critical alerts have runbooks;
alert deduplication is implemented;
safe auto-remediation is possible;
retry storms are controlled;
deployments are correlated with health changes;
rollback exists;
performance regression is checked;
the Async Kernel passes load/soak testing;
resource leaks are detected;
a synthetic Telegram flow exists;
observability itself is monitored;
production readiness includes the observability contract.

## 91. Architectural Invariants

### OBS-01 — If it cannot be observed, it cannot be operated reliably.

### OBS-02 — Metrics measure state; logs explain events; traces explain causality.

### OBS-03 — Every critical operation has timeout.

### OBS-04 — No unbounded retry.

### OBS-05 — No uncontrolled telemetry cardinality.

### OBS-06 — Async boundaries preserve diagnostic context.

### OBS-07 — Process alive ≠ service healthy.

### OBS-08 — Queue depth alone is not queue health; queue age is mandatory.

### OBS-09 — Deployment must be observable and reversible.

### OBS-10 — Critical services require automated health detection.

### OBS-11 — Async Kernel must be observable without coupling core code to a monitoring vendor.

### OBS-12 — Observability must not become a performance or reliability bottleneck.

## 92. Final Model

    PRODUCTION
    │
    ┌────────────────────┼────────────────────┐
    ▼                    ▼                    ▼
    TELEGRAM             ASYNC                 DATA
    │                  KERNEL                  │
    ▼                    ▼                    ▼
    PROCESSING            WORKERS             PostgreSQL
    │                    │                  Redis
    └────────────────────┼────────────────────┘
    ▼
    TELEMETRY
    ┌──────────────┼──────────────┐
    ▼              ▼              ▼
    METRICS         LOGS           TRACES
    │              │              │
    └──────────────┼──────────────┘
    ▼
    DETECTION
    │
    ┌──────────────┼──────────────┐
    ▼              ▼              ▼
    ALERT          DIAGNOSE       AUTO-REMEDIATE
    │              │              │
    └──────────────┼──────────────┘
    ▼
    RECOVERY
    │
    ▼
    VERIFY
    │
    ▼
    SLO

The main idea of 09: SRE for this platform must be built around the real Telegram/Async lifecycle, not around ordinary HTTP monitoring. Especially important are event-loop lag, queue age, worker health, Telegram API/429, async task latency, retries, resource leaks, and the end-to-end synthetic Telegram flow. This enables the platform to detect degradation of the Async Kernel and the Telegram runtime long before the developer starts digging through logs manually.
