# 06 — Runtime Operations

Status: Draft
Version: 0.2
Owner: platform-team
Updated: 2026-08-21
Depends on: 01-architecture-and-baseline.md, 02-developer-tooling.md, 03-security-and-supply-chain.md, 04-qa-and-testing.md, 05-ci-cd-and-release.md
Implementation: 11-implementation-and-rollout.md

## 1. Goal

Ensure platform operation such that:

- Telegram bots can run for a long time, predictably, and automatically recover from typical failures without developer involvement.

Special attention:

- Async Kernel
- Async TG library
- Telegram transports
- workers
- queues
- Redis
- HTTP
- DNS
- TLS
- process lifecycle

Main principle:

`observe → detect → diagnose → recover → verify`

not:

`observe → alert developer → developer fixes manually`

## 2. Runtime Architecture

Basic model:

```text
                    ┌──────────────┐
                    │ Telegram API │
                    └──────┬───────┘
                           │
                           ▼
                    Async TG Transport
                           │
                           ▼
                     Async Kernel
                           │
             ┌─────────────┼─────────────┐
             ▼             ▼             ▼
          Scheduler      Workers       Timers
             │             │             │
             └─────────────┼─────────────┘
                           ▼
                       Queues
                           │
              ┌────────────┼────────────┐
              ▼            ▼            ▼
            Redis       Database     External APIs
```

The runtime must be:

- bounded;
- observable;
- restartable;
- cancellable;
- horizontally scalable;
- tolerant of transient failures.

## 3. Process Lifecycle

Every long-running process must have:

- start
- ready
- running
- draining
- stopping
- stopped
- failed

One must not rely on:

`process exists = healthy`

## 4. Graceful Shutdown

> This section is the single definition site of the platform's shutdown semantics (signal → stop accepting → drain → flush → exit, with timeout); other documents reference it instead of redefining shutdown.

On SIGTERM/SIGINT:

```text
signal
↓
stop accepting new work
↓
finish/cancel active tasks
↓
flush required state
↓
close connections
↓
stop workers
↓
exit
```

There must be a timeout:

graceful shutdown timeout

After it:

forced termination

## 5. Async Kernel Lifecycle

The Async Kernel must centrally manage:

- scheduler
- fibers
- timers
- IO watchers
- processes
- sockets
- HTTP clients
- shutdown

Independent event loops inside components are not allowed.

## 6. One Scheduler

Architectural invariant:

```text
one runtime
one scheduler
```

Libraries must not create their own hidden loops.

## 7. Fiber Lifecycle

Every Fiber/task must have:

- created
- running
- waiting
- completed
- failed
- cancelled

After completion:

- fiber references
- timers
- callbacks
- resources

must be released.

## 8. Task Cancellation

Cancellation must be a first-class operation.

For example:

```text
request timeout
↓
cancel task
↓
cancel IO
↓
release resources
```

A task must not be left running after its owner has cancelled the operation.

## 9. Timeout Hierarchy

Use different timeout levels:

- operation
- request
- queue
- worker
- shutdown
- deployment

Do not make one global timeout for the whole runtime.

## 10. Resource Limits

The runtime must limit:

- active fibers
- active HTTP requests
- connections
- queue depth
- memory
- open files
- retries
- timers
- processes

The default must be bounded.

## 11. Backpressure

When capacity is exceeded:

```text
producer
↓
queue
↓
capacity reached
```

the system must have a defined policy:

- wait
- reject
- drop
- defer
- shed load

rather than growing memory indefinitely.

## 12. Worker Model

A worker must:

```text
start
↓
initialize
↓
ready
↓
process
↓
health monitoring
↓
drain
↓
shutdown
```

A worker crash must not require a manual start.

## 13. Worker Supervision

The supervisor must track:

- worker alive
- worker heartbeat
- worker memory
- worker CPU
- worker task count
- worker failures

On crash:

```text
detect
↓
restart
↓
verify
```

## 14. Restart Policy

Restart must have:

- max restart rate
- backoff
- jitter
- crash threshold

To avoid a crash loop:

```text
crash
restart
crash
restart
...
```

## 15. Crash Loop Detection

If a worker keeps crashing:

```text
healthy
↓
crash
↓
restart
↓
crash
↓
...
```

the system must enter:

degraded / unhealthy

and raise an actionable alert.

## 16. Queue Architecture

Queues must have:

- priority
- partitioning
- visibility
- retry
- dead-letter
- backpressure

For the platform, a possible model is:

- high
- normal
- low

or the existing partitioned queue architecture.

## 17. Queue Guarantees

For each queue, explicitly define:

- delivery semantics
- ordering
- retry semantics
- visibility timeout
- deduplication
- dead-letter policy

Do not use the word "reliable" without defining semantics.

## 18. Queue Poison Messages

A message that keeps failing:

```text
process
↓
fail
↓
retry
↓
fail
```

must not block the queue indefinitely.

After a defined number of retries:

dead-letter queue

## 19. Dead-Letter Queue

The DLQ must be observable:

- message count
- age
- failure reason
- source
- retry count

Critical queues must have a controlled replay mechanism.

## 20. Queue Replay

Replay must be safe:

```text
inspect
↓
fix
↓
replay selected message
```

There must be no "replay everything" button without safeguards.

## 21. Redis Reliability

If Redis is used as the queue/state layer:

check:

- connectivity
- latency
- memory
- evictions
- connection count
- errors

A Redis outage must lead to a predictable degraded mode.

## 22. External Dependency Failure

For every external dependency:

- Telegram
- Redis
- Postgres
- HTTP API
- DNS

the following must be defined:

- timeout
- retry
- backoff
- circuit breaker where appropriate
- fallback
- degraded mode

## 23. Circuit Breaker

For unstable external services, the following is possible:

```text
closed
↓ failures
open
↓ cooldown
half-open
↓ success
closed
```

A circuit breaker is not automatically needed for every dependency.

Use it where it actually prevents cascading failure.

## 24. Telegram Rate Limits

Telegram API throttling must be part of the runtime policy.

Handle:

- 429
- retry_after

centrally.

Do not allow every bot processor to implement its own incompatible retry logic.

## 25. Telegram Failure Handling

Handle as separate classes:

- network failure
- DNS failure
- TLS failure
- timeout
- 429
- 5xx
- 4xx
- malformed response
- authentication failure

The retry policy must depend on the error class.

## 26. Transport Abstraction

The async TG transport must have a single contract regardless of implementation:

- curl multi
- Guzzle
- fsockopen

Runtime policy must not depend on the specific transport.

## 27. Connection Pooling

The HTTP/TG transport must control:

- max connections
- idle connections
- connection lifetime
- keep-alive
- TLS reuse

Uncontrolled connection growth is not allowed.

## 28. DNS

The DNS subsystem must have:

- timeout
- cache
- failure handling

A DNS failure must be visible as a separate error class.

## 29. TLS

The production transport must provide:

- certificate verification
- hostname verification
- secure defaults

Performance optimization must never disable TLS verification.

## 30. Network Resource Protection

External requests must have:

- connect timeout
- read timeout
- total timeout
- response size limit
- connection limit

This protects the runtime from:

- slowloris
- hung connection
- memory exhaustion
- connection exhaustion

## 31. Observability Architecture

Minimum:

- logs
- metrics
- health
- alerts

Tracing — as needed.

## 32. Structured Logs

All runtime components use structured logs.

Required fields:

- timestamp
- level
- service
- component
- event
- request_id
- trace_id where available
- bot_id where appropriate
- duration
- error

Sensitive data is forbidden.

## 33. Telegram Logging

Do not log:

- bot token
- Authorization headers
- private credentials

Telegram updates must be logged in accordance with the privacy policy and with the minimum necessary content.

## 34. Metrics

Core metrics:

- process uptime
- worker count
- worker restarts
- active tasks
- completed tasks
- failed tasks
- cancelled tasks
- queue depth
- queue latency
- retry count
- timeouts
- connections
- memory
- CPU

## 35. Async Kernel Metrics

Mandatory:

- active fibers
- fiber creation rate
- fiber completion rate
- fiber failure rate
- scheduler latency
- event-loop iterations
- pending timers
- pending IO
- FD usage

A particularly useful metric:

scheduler lag

which shows how far the runtime lags behind the expected event scheduling.

## 36. Telegram Metrics

Minimum:

- requests
- success
- 4xx
- 5xx
- 429
- latency
- retry count
- timeout count
- DNS errors
- TLS errors
- connection failures

Breakdown:

- method
- bot
- transport
- endpoint

must be used carefully so that cardinality does not become huge.

## 37. Cardinality Control

Do not use in metric labels:

- user_id
- chat_id
- message_id
- arbitrary URL
- request body

if it creates a high-cardinality explosion.

For such data, use logs/traces.

## 38. Correlation

A single request must be traceable:

```text
Telegram update
↓
processor
↓
async task
↓
queue
↓
worker
↓
Telegram API
```

via a correlation identifier.

## 39. Health Endpoints

This section is the platform-wide definition of the health endpoints; other documents must reference it.

- `/health/live` — liveness: the process is functioning.
- `/health/ready` — readiness: the process is able to accept work.
- `/health` — deep diagnostics: authenticated, never public.

Deep diagnostics (`/health`) may expose:

- workers
- queues
- connections
- memory
- runtime
- dependencies
- version
- artifact

Deep diagnostics must not be publicly accessible without authentication.

## 40. Deep Diagnostics

Deep diagnostics is exposed via the `/health` endpoint defined in §39 (Health Endpoints); that section is the single definition of this endpoint, and this section introduces no separate endpoint wording.

## 41. Runtime Configuration

Configuration must be:

- validated
- typed
- immutable where possible
- environment-specific

Invalid production config:

startup failure

not:

start with dangerous defaults

## 42. Safe Defaults

There must be no production defaults such as:

- unlimited retries
- unlimited queue
- debug=true
- TLS verify=false
- no timeout
- unlimited connections

## 43. Graceful Degradation

On dependency failure, the system must transition to a predefined state.

For example:

```text
Telegram unavailable
↓
retry with backoff
↓
queue
↓
degraded
```

not:

spawn unlimited retries

## 44. Load Shedding

On exhaustion of capacity:

- CPU
- memory
- queue
- connections

the system must be able to drop/defer non-critical work.

Critical operations must have priority.

## 45. Priority Scheduling

For the async runtime, the following is possible:

- critical
- high
- normal
- low
- background

The scheduler must not allow a low-priority workload to clog the runtime completely.

## 46. Fairness

Priority must not create starvation.

For example:

high priority continuously active

must not block forever:

normal priority

unless the policy provides for it.

## 47. Memory Protection

For long-running PHP processes, the following are mandatory:

- memory monitoring
- periodic diagnostics
- leak detection
- worker limits

If a worker exceeds the memory budget:

```text
graceful drain
restart
```

is preferable to an abrupt OOM.

## 48. Worker Recycling

For PHP long-running workers, controlled recycling is allowed:

```text
N tasks
or
T lifetime
or
memory threshold
```

after which:

- drain
- shutdown
- restart

This is additional protection against gradual process degradation.

## 49. File Descriptor Monitoring

Especially important for the Async Kernel.

Monitor:

- open FDs
- FD limit
- socket count
- pipes
- files

When approaching the limit:

- warning
- load shedding

## 50. Event Loop Health

The scheduler must have a watchdog.

If the event loop stops making progress:

```text
watchdog
↓
detect stall
↓
diagnose
↓
restart worker/process
```

## 51. Watchdog

The watchdog must distinguish:

busy

from:

stalled

For example, high CPU by itself does not mean a deadlock.

## 52. Runtime Self-Diagnostics

Periodically check:

- scheduler progress
- queue progress
- worker health
- memory
- FD
- connections
- dependency health

Publish the results in metrics.

## 53. Capacity Planning

The system must collect data to answer:

- how many bots can one worker handle?
- how many requests/sec?
- what queue depth is acceptable?
- where is the bottleneck?

Key indicators:

- throughput
- latency
- CPU
- memory
- queue depth
- connections

## 54. Autoscaling

If the infrastructure supports autoscaling:

- queue depth
- CPU
- request rate
- worker utilization

can be used as signals.

Do not scale by CPU alone for queue-driven workloads.

## 55. Horizontal Scaling

The async platform must allow:

```text
1 worker
→
N workers
```

without changing application semantics.

The following must be explicitly defined:

- ownership
- partitioning
- deduplication
- ordering

## 56. Leader / Singleton Work

If singleton work exists:

- scheduler
- cleanup
- cron
- migration

an explicit distributed locking/leader election mechanism is needed.

One must not rely on:

"usually a single instance is running"

## 57. Runtime Security Boundary

A production worker must not have more permissions than necessary.

Separate credentials for:

- application
- worker
- scheduler
- deployment
- observability

## 58. Operational Commands

cmd/ must have operational presets:

- cmd/ops/status
- cmd/ops/health
- cmd/ops/diagnose
- cmd/ops/logs
- cmd/ops/restart
- cmd/ops/drain
- cmd/ops/queue
- cmd/ops/replay

But dangerous commands must have safeguards.

## 59. Dangerous Operations

For example:

- flush queue
- delete queue
- force restart
- replay messages
- database destructive operation

must require explicit confirmation.

## 60. Automated Recovery

Automatically recover typical states:

- worker crash
- container crash
- temporary Telegram outage
- temporary Redis outage
- stuck worker
- expired connection

Do not automate operations with a high risk of data loss without additional safeguards.

## 61. Incident Detection

The incident detector must use a combination of:

- health
- metrics
- logs
- deployment events

not a single metric alone.

## 62. Deployment Correlation

After a release, the system must know:

```text
new version
↓
error rate increased
↓
deployment correlation
↓
rollback candidate
```

This makes it possible to automate detection of a bad release.

## 63. SLO Runtime

Define for the platform:

- availability
- Telegram request success rate
- Telegram latency
- queue processing latency
- worker availability

for each critical component.

## 64. Alert Classes

Minimum:

- critical
- warning
- info

**Critical** — immediate reaction is required.

**Warning** — the system is degraded, but self-healing works.

**Info** — for dashboards/audit.

## 65. Alert Suppression

During:

- deployment
- maintenance
- known outage

some alerts may be suppressed.

But suppression must be:

- scoped
- time-bounded
- visible

## 66. No Silent Recovery

Automatic recovery must leave an event:

```text
worker restarted
reason=memory_limit
```

The developer must not get a pager alert for every successfully fixed transient failure, but recovery must be visible in telemetry.

## 67. Runbooks

For every critical alert:

- symptom
- likely causes
- automatic actions
- diagnostics
- manual actions
- rollback
- verification

## 68. Runtime Upgrade Safety

A change to:

- Async Kernel
- async TG
- PHP runtime
- transport
- Redis

must pass:

- compatibility
- integration
- performance
- failure

tests.

## 69. Long-Running Runtime Tests

Before an Async Kernel release, it is advisable to run:

an hours-long soak test

with:

- concurrency
- network traffic
- retries
- cancellation
- timeouts
- worker recycling

The goal is to catch degradation that does not show up within a 10-second unit test.

## 70. Acceptance Criteria

A component is ready when:

- the Async Kernel has lifecycle management;
- fibers/tasks are cancellable;
- the runtime is bounded;
- backpressure exists;
- workers are supervised;
- crash loops are detected;
- queues have a retry/DLQ policy;
- Telegram rate limiting is centralized;
- external dependencies have a timeout/retry policy;
- graceful shutdown is implemented;
- worker recycling is possible;
- memory/FD/resource limits are controlled;
- structured logging is standardized;
- metrics are standardized;
- correlation IDs pass through async boundaries;
- health/readiness checks exist;
- SLOs are defined for critical services;
- alerts are actionable;
- automated recovery is implemented for typical transient failures;
- operational commands are standardized;
- dangerous operations are protected;
- capacity metrics are collected;
- deployments are linked to runtime telemetry;
- rollback can be triggered based on objective signals;
- backup/restore and DR remain part of the relevant operational baseline.

## 71. Architectural Invariants

### OPS-01 — Everything important is observable

If a state matters for operations, it must have telemetry.

### OPS-02 — Everything transient should self-heal

A transient failure must not automatically become a developer's task.

### OPS-03 — Everything bounded

No unlimited:

- retry
- queue
- memory
- connections
- tasks

### OPS-04 — Cancellation is mandatory

Async work must have a cancellation path.

### OPS-05 — Shutdown is graceful

A worker must be able to terminate without losing acceptable work.

### OPS-06 — Crash is recoverable

An ordinary process crash must not require manual intervention.

### OPS-07 — Degradation is predictable

Under resource shortage, the system degrades according to policy, not randomly.

### OPS-08 — Runtime state is diagnosable

Telemetry must make it possible to understand where the system is stuck.

### OPS-09 — Automation leaves evidence

Self-healing must not be silent.

### OPS-10 — Operational commands are standardized

A developer/operator must not have to memorize dozens of specific commands.

### OPS-11 — Core runtime remains framework-agnostic

The Async Kernel does not depend on the Laravel/application layer.

### OPS-12 — Performance optimizations cannot weaken reliability

Speeding up the transport/scheduler must not disable:

- timeouts
- TLS verification
- limits
- cancellation
- resource cleanup

## 72. Final Model

```text
    PRODUCTION
    │
    ┌────────────────────┼────────────────────┐
    ▼                    ▼                    ▼
    WORKERS              QUEUES            EXTERNAL APIs
    │                    │                    │
    └────────────────────┼────────────────────┘
    ▼
    OBSERVABILITY
    │
    ┌───────────────┼───────────────┐
    ▼               ▼               ▼
    LOGS            METRICS          HEALTH
    │               │               │
    └───────────────┼───────────────┘
    ▼
    DETECTION
    │
    ┌────────┴────────┐
    ▼                 ▼
    HEALTHY          DEGRADED
    │                 │
    │                 ▼
    │             AUTO-RECOVERY
    │                 │
    │          ┌──────┴──────┐
    │          ▼             ▼
    │       SUCCESS       FAILURE
    │          │             │
    │          ▼             ▼
    │       RESUME         ALERT
    │
    ▼
    SLO
```

The main idea: 06 turns the platform from merely a well-tested application into a self-healing runtime system. For your architecture this is especially critical precisely around the Async Kernel + async TG-lib + worker/queue layer: there must be not only tests, but also lifecycle, backpressure, cancellation, supervision, telemetry, and automatic recovery.
