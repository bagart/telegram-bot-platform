# 04 — QA & Testing

Status: Draft
Version: 0.2
Owner: platform-team
Updated: 2026-08-21
Depends on: 01-architecture-and-baseline.md, 02-developer-tooling.md, 03-security-and-supply-chain.md
Implementation: 11-implementation-and-rollout.md

## 1. Goal

The component must make every change provably correct before it is merged and released. This document covers the testing strategy only: quality gates, test levels, test profiles, and CI testing discipline.

Main principle:

```text
code
  ↓
tests
  ↓
static analysis
  ↓
build
  ↓
release
```

A developer must not have to remember the full set of checks manually — the platform selects and runs them automatically.

## 2. Quality Gates

Every repository automatically receives quality gates:

- Q1 — syntax / formatting
- Q2 — static analysis
- Q3 — unit tests
- Q4 — integration tests
- Q5 — security
- Q6 — dependency integrity
- Q7 — build
- Q8 — performance
- Q9 — artifact validation
- Q10 — release verification

Not every repository must run every gate the same way — the profile defines applicability.

## 3. Test Pyramid

The main strategy:

```text
E2E
 ▲
Integration
 ▲
Unit
 ▲
Static checks
```

Most checks must be fast and run locally. Heavy E2E / integration / performance checks run in CI.

## 4. Test Profiles

Minimal profiles:

- generic-php
- library
- laravel
- frontend
- docker
- async-runtime
- telegram

Especially important: `async-runtime` and `telegram`, because errors in the Async Kernel / async Telegram transport may only appear under concurrency and failure conditions.

## 5. Unit Tests

Unit tests must cover:

- business logic
- pure functions
- state machines
- parsers
- validators
- retry policies
- schedulers
- queue logic
- error handling

Tests must be deterministic, isolated, fast, and repeatable.

## 6. Integration Tests

Integration tests exercise real boundaries:

- PostgreSQL
- Redis
- Telegram transport
- filesystem
- HTTP
- Docker
- external services

Where possible, infrastructure is started automatically. A developer must not have to bring up a dozen services manually for a standard test run.

## 7. Async Kernel Tests

Special test classes are mandatory for the Async Kernel:

- scheduler
- fiber lifecycle
- cancellation
- timeouts
- queue ordering
- concurrency
- exception propagation
- resource cleanup
- shutdown
- restart
- backpressure

Especially: N concurrent tasks + random completion order + failure injection.

## 8. Async Resource Safety

Every async primitive must have tests proving the absence of:

- FD leaks
- fiber leaks
- timer leaks
- memory leaks
- pending task leaks
- socket leaks
- queue leaks

After a test scenario, the system must return to the expected state.

## 9. Concurrency Testing

The Async Kernel / TG library must have deterministic concurrency tests.

Example setup:

- 1000 tasks
- 50 workers
- random latency
- random failures
- timeouts
- cancellation
- retries

Verify:

- no deadlock
- no race-like state corruption
- no lost task
- no duplicate completion
- no resource leak

## 10. Telegram Integration Tests

The Telegram transport must be tested separately from application logic.

Checks:

- HTTP
- TLS
- timeouts
- retries
- 429
- 5xx
- network failure
- malformed response
- large response
- connection reset
- DNS failure

## 11. Contract Tests

Contract tests are mandatory for libraries, especially:

- telegram-bot-lib
- telegram-bot-basic-lib
- telegram-bot-management-lib
- async kernel
- async tg-lib

Goal: changing one library must not silently break the contract of another.

## 12. Compatibility Matrix

Important libraries must have a compatibility matrix:

- PHP versions
- framework versions
- dependency versions
- transport implementations
- runtime modes

For example:

- PHP 8.4 / PHP 8.5
- sync transport / async transport
- Redis enabled / Redis disabled

The exact set is defined by the profile.

## 13. Regression Tests

Every fixed production bug must, where possible, get a regression test.

Rule:

```text
bug → fix → test reproducing the bug → fix verification
```

This prevents the problem from reappearing.

## 14. Mutation Testing

Mutation testing is applied gradually to critical libraries, especially:

- Async Kernel
- retry logic
- scheduler
- rate limiting
- security policies
- Telegram request handling

Goal: make sure tests actually detect behavior changes, not merely pass. Mutation testing must not run on every commit.

## 15. Code Coverage

Coverage is used as a diagnostic metric, not as the single measure of quality.

Do not use:

```text
90% coverage = code is safe
```

Use the combination:

```text
coverage + mutation score + static analysis + integration tests + production signals
```

Minimal thresholds may be set for critical components.

## 16. Flaky Tests

A flaky test is a separate category of defect.

CI must distinguish:

- PASS
- FAIL
- FLAKY
- INFRASTRUCTURE FAILURE

Endlessly doing `retry 5 times` and calling the problem solved is not acceptable.

## 17. Retry Policy

Automatic retry is allowed only for known transient infrastructure failures.

Not for:

- assertion failure
- security failure
- static analysis failure
- compile error
- test logic failure

Otherwise CI starts hiding real regressions.

## 18. Test Quarantine

Temporarily flaky tests go to quarantine. A quarantine entry must have:

- reason
- owner
- issue
- created_at
- expiry

Quarantine must expire automatically.

## 19. Chaos / Failure Injection

Add controlled failure tests for the critical runtime:

- DNS failure
- connection reset
- timeout
- Redis unavailable
- Telegram unavailable
- worker crash
- process kill
- memory pressure

Deterministic failure injection is enough at the first stage; full chaos engineering comes later.

## 20. QA Automation

The QA pipeline must automatically select checks based on changed files, for example:

- `src/*.php` → PHP checks
- `frontend/**` → frontend checks
- `Dockerfile` → Docker checks
- `.github/**` → workflow validation
- `composer.*` → dependency checks
- `AsyncKernel/**` → async tests + benchmarks

Running the entire universe of checks on every change is not necessary.

## 21. Change Impact Analysis

The pipeline must compute:

```text
changed files → affected profile → affected tests → affected security checks → required CI gates
```

This is one of the main ways to keep the system fast.

## 22. Full Verification

Despite incremental checks, the following must exist:

- `./cmd/dev/security --full`
- `./cmd/dev/check --full`
- a CI release pipeline that runs the full set of mandatory checks.

## 23. Test Environment Reproducibility

The CI environment must be reproducible. Pin:

- PHP
- Node
- Composer
- package manager
- Docker
- OS / container
- test dependencies

"Works on my machine" differences must be minimized.

## 24. Time Budgets

Every CI stage has an expected runtime, for example:

- quick checks — seconds
- normal CI — minutes
- integration — minutes
- full benchmark — scheduled
- stress — scheduled

If the pipeline unexpectedly slows down, that is a separate quality regression.

## 25. CI Flakiness

The platform must track:

- test failure rate
- retry rate
- job duration
- flake rate

CI reliability is itself a first-class platform metric.

## 26. Failure Classification

Distinguish between:

- code failure
- test failure
- security failure
- dependency failure
- CI infrastructure failure

This is required for correct diagnosis.

## 27. Acceptance Criteria

The component is considered implemented when:

- repository profiles automatically select the required QA checks;
- unit/integration/contract tests are standardized;
- the Async Kernel has concurrency/failure/resource tests;
- the Telegram transport has network/error/retry tests;
- flaky tests are tracked;
- CI failures are classified;
- incremental checks are selected automatically;
- full verification remains available;
- CI runtime and flakiness are controlled.

## 28. QA Invariants

- **QA-01 — Tests must test failure.** Happy-path tests are not sufficient for platform runtime.
- **QA-02 — CI must be deterministic.** Flakiness is not a normal state.
- **QA-03 — Retry must be bounded.** No infinite retries.
- **QA-04 — Incremental by default, full when required.** Normal development must stay fast; full verification runs automatically at the appropriate stages.
- **QA-05 — Flakiness is a defect.** A flaky test has an owner and a fix path; it is never retried away.
- **QA-06 — Coverage is diagnostic, not a goal.** Coverage numbers never substitute for test quality.

## 29. Out of Scope

The following topics were re-homed from earlier revisions of this document and are owned elsewhere:

- Performance baselines, performance regression, benchmarks, load/stress testing, backpressure, resource limits, timeouts, retry/backoff, idempotency → `09-observability-and-performance.md`.
- Health checks, observability, structured logging, correlation IDs, metrics, SLO, error budgets, alerting, automated diagnostics → `06-runtime-operations.md` and `09-observability-and-performance.md`.
- Release validation, deployment verification, rollback, database safety, backup/restore, disaster recovery, incident readiness, postmortems → `05-ci-cd-and-release.md` and `07-resilience-and-disaster-recovery.md`.
