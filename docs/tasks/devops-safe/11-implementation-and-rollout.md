# 11 — Implementation & Rollout

Status: Draft
Version: 0.2
Owner: platform-team
Updated: 2026-08-21
Depends on: 01-architecture-and-baseline.md through 10-telegram-platform-and-libraries.md (the full spec set)
Implementation: — (this document)

Guiding principle:

First write and wire the entire system, then switch it on.

Baseline development must not be slowed down by its own checks. Heavy checks, full CI, benchmarks, DR, and rollout are switched on only after the tooling is fully implemented.

## 1. Goal

As a result, all platform repositories must get a single lifecycle:

```text
create
↓
bootstrap
↓
develop
↓
fix
↓
quick-check
↓
commit
↓
push
↓
PR
↓
CI
↓
build
↓
security
↓
release
↓
deploy
↓
observe
↓
backup
↓
recover
```

In this scheme:

- the developer does not manage dozens of tools manually;
- AI works through the same interface;
- the baseline is updated centrally;
- CI is the final security/quality gate;
- production has SRE/DR guarantees.

## 2. Implementation Philosophy

During implementation:

```text
WRITE
↓
WIRE
↓
VALIDATE
↓
ENABLE
```

Not:

```text
WRITE
↓
RUN EVERYTHING
↓
FIX EVERYTHING
↓
WRITE MORE
```

## 3. Phase 0 — Repository Inventory

First, compile an inventory of all repositories:

- platform
- telegram-bot-lib
- telegram-bot-basic-lib
- telegram-bot-management-lib
- async-kernel
- async-tg-lib
- applications
- frontend/admin
- infrastructure

For each one, determine:

- type
- language
- framework
- runtime
- Docker
- CI
- tests
- dependencies
- production criticality
- owner

## 4. Repository Profiles

After the inventory, assign a profile:

- generic-php
- laravel
- frontend
- docker
- async-runtime
- application
- infrastructure

One repository may carry several profiles.

For example:

Laravel + frontend + Docker

## 5. Criticality Classification

Each repository gets a level:

- Tier 0 — library / low impact
- Tier 1 — normal application
- Tier 2 — production service
- Tier 3 — platform-critical

In particular:

- Async Kernel
- async TG-lib
- Telegram runtime
- core platform

receive the maximum level of validation.

## 6. Baseline Repository

Create:

`security-baseline/`

It becomes the source of truth for:

- commands
- hooks
- security
- profiles
- CI
- templates
- documentation

## 7. Baseline Versioning

Use:

`v1.x`, `v2.x`

Rules:

- `patch` — a baseline fix that does not change the contract.
- `minor` — new checks/features.
- `major` — breaking changes.

## 8. Shared Core

First implement the framework-agnostic core:

- `security/`
- `cmd/`
- `profiles/`
- `hooks/`
- CI templates

The core must not import:

- Laravel
- frontend frameworks
- Docker-specific libraries

## 9. Profile Layer

Then implement:

- generic-php
- laravel
- frontend
- docker
- async-runtime

Each profile adds only its own checks.

## 10. Unified Command Engine

All commands must use a common orchestration layer.

Do not build:

20 shell scripts held together by copy-paste.

Better:

```text
cmd/*
↓
common runner
↓
profiles
↓
tools
```

## 11. Command Contract

Every command must support uniform semantics:

- `--help`
- `--verbose`
- `--quiet`
- `--json`

where applicable.

Standardize:

- exit codes
- output
- error handling
- timing

## 12. Configuration

Central configuration must define:

- enabled profiles
- tools
- versions
- thresholds
- check levels
- CI behavior

But a repository must be able to override parameters within limits.

## 13. No Hidden Configuration

All security/quality rules must be visible:

`security/config/`, `profiles/`

There must be no magic that cannot be understood from within the repository.

## 14. Git Hooks

Implement:

- `pre-commit`
- `commit-msg`
- `pre-push`

via:

`tools/git-hooks/`

installed as part of the canonical setup entry point:

`./cmd/dev/setup`

## 15. Hook Performance

Do not enable heavy checks at this stage.

Define a budget:

- pre-commit < a few seconds
- commit-msg < 1 sec
- pre-push = configurable

## 16. Security Tooling

Wire in:

- TruffleHog
- composer audit
- npm/pnpm audit
- Semgrep
- Trivy
- actionlint
- Hadolint
- yamllint

But first build unified adapters.

## 17. Tool Availability

If a tool is missing:

`./cmd/dev/doctor`

must show:

```text
✗ Trivy missing

Install:
...
```

Do not emit a cryptic:

`command not found`

## 18. PHP Toolchain

For generic PHP:

- Composer
- PHPStan
- PHP-CS-Fixer
- Semgrep
- PHPUnit/Pest
- composer audit

For Laravel:

- Pint
- Enlightn
- artisan test

## 19. Frontend Toolchain

Automatically support:

- npm
- pnpm

with:

- ESLint
- Prettier
- Stylelint
- Semgrep
- audit
- build
- dist secret scan

## 20. Docker Toolchain

- Hadolint
- Docker build
- Trivy
- SBOM
- provenance
- signing
- digest

## 21. GitHub Baseline

Create reusable workflows:

- `ci.yml`
- `security.yml`
- `dependency-review.yml`
- `docker.yml`
- `release.yml`

Shared workflows must live in the baseline repository.

## 22. GitHub Hardening

Automatically apply:

- minimal permissions
- SHA-pinned actions
- OIDC
- CODEOWNERS
- branch rules
- protected tags

## 23. Rulesets

Create standard rulesets:

- main
- develop
- release tags
- security-sensitive paths

First:

audit mode

then:

enforcement

## 24. CODEOWNERS

Automatically generate/install ownership for:

- `.github/`
- `security/`
- Dockerfile
- dependencies
- deployment
- Async Kernel

## 25. PR Templates

Create a standard PR template:

- change
- reason
- tests
- security
- dependencies
- Docker
- CI
- AI-assisted

## 26. AGENTS.md

Create a standard:

`AGENTS.md`

with:

- architecture
- commands
- security rules
- testing
- dependency rules
- AI restrictions
- release rules

Repository-specific additions are allowed.

## 27. README Contract

Every repository must carry the same minimal block:

- Setup
- Doctor
- Check
- Fix
- Test
- Security
- Quick commit
- CI

## 28. Repository Bootstrap

Create an automatic bootstrap:

`./cmd/repository/create`

or equivalent platform tooling.

Input:

- repository type
- profiles
- runtime
- criticality

Output:

- baseline
- CI
- hooks
- docs
- CODEOWNERS
- security
- commands

## 29. Existing Repository Migration

Do not migrate everything by hand.

Create:

`baseline apply`

which:

```text
detect
↓
plan
↓
apply
↓
validate
```

## 30. Dry Run

Mandatory:

`baseline apply --dry-run`

It shows:

- files to create
- files to modify
- files to preserve
- conflicts

## 31. Migration Conflicts

Do not silently overwrite existing project-specific configuration.

On conflict:

`⚠ manual merge required`

## 32. Generated vs Owned Files

Clearly separate:

- baseline-owned
- project-owned
- generated

This makes it possible to update the baseline without destroying custom configuration.

## 33. Async Kernel Specialization

For the Async Kernel, create a dedicated profile:

`profiles/async-runtime/`

It includes:

- concurrency tests
- cancellation tests
- timeout tests
- scheduler tests
- transport tests
- stress tests
- benchmark
- soak test

## 34. Async Kernel Contract Tests

Create shared contract tests for:

- scheduler
- fiber lifecycle
- task cancellation
- timeouts
- transport
- queue
- shutdown

Any runtime implementation must pass the same contract suite.

## 35. Async TG-lib Contract

Verify separately:

- Telegram transport
- polling
- webhook
- rate limiting
- 429
- retry
- timeout
- cancellation
- connection reuse

## 36. Performance Baselines

After benchmark tooling is implemented, establish baselines:

- throughput
- latency
- memory
- CPU

for:

- Async Kernel
- async TG-lib
- HTTP transport
- scheduler
- Telegram processing

## 37. Do Not Gate Development Too Early

Benchmarks must not block PRs right away.

First:

report-only

After stable baselines accumulate:

warning

then, for critical regressions:

blocking

## 38. QA Automation

Full test matrix:

- unit
- integration
- contract
- e2e
- security
- concurrency
- performance
- recovery

## 39. Test Matrix

For the Async Kernel:

- PHP versions
- OS/environment
- transport
- concurrency
- failure mode

Do not run the whole matrix on every commit.

## 40. Test Tiers

- Tier 1 — local quick
  - syntax
  - lint
  - fast tests
  - secrets
- Tier 2 — PR
  - full unit
  - static analysis
  - security
  - integration
- Tier 3 — merge/release
  - full integration
  - Docker
  - SBOM
  - performance
- Tier 4 — scheduled
  - stress
  - soak
  - chaos
  - DR
  - full dependency matrix

## 41. Scheduled Validation

Run heavy checks on a schedule:

- nightly
- weekly

For example:

- stress
- soak
- dependency full scan
- DR restore
- container scan

## 42. SRE Integration

After observability is implemented:

- metrics
- logs
- traces
- health
- alerts
- runbooks

must be connected to the production runtime.

## 43. SLO Rollout

First:

measure

then:

report

then:

alert

and only after a baseline has accumulated:

enforce

## 44. Alert Tuning

For the first weeks:

alerts in non-blocking mode

collect:

- false positives
- false negatives
- noise

After tuning — production enforcement.

## 45. Backup Integration

After the backup system exists:

- backup
- verify
- restore test

connect it to:

- monitoring
- alerting
- SLO
- DR

## 46. DR Validation

Do not consider DR complete until a real drill has been run:

```text
destroy isolated environment
↓
restore
↓
deploy
↓
verify
```

## 47. Security Rollout

Introduce security checks gradually.

- Stage 1 — report
- Stage 2 — warning
- Stage 3 — PR blocking

This is especially important for existing repositories carrying technical debt.

## 48. Existing Security Debt

Do not create a situation where:

```text
1000 existing findings
↓
CI blocked forever
```

Use baseline/suppression only for known pre-existing findings, with:

- owner
- reason
- expiry/review date

New findings must block.

## 49. No Permanent Suppression

Every security exception must have:

- reason
- owner
- created_at
- review_at

and automatically turn into an alert after expiry.

## 50. Quality Debt

Similarly:

a PHPStan baseline,

if needed, must shrink gradually.

Do not use a baseline as a way to hide new problems.

## 51. CI Performance

Once enabled, measure:

- total CI time
- queue time
- each job duration
- cache hit rate
- failure rate

## 52. Parallelization

Run independent checks in parallel:

- SAST
- PHPStan
- frontend lint
- dependency audit
- tests

## 53. Caching

Use caches for:

- Composer
- npm/pnpm
- Docker layers
- PHPStan
- test artifacts

but caching must not compromise security.

## 54. CI Resource Control

Do not run:

Docker build

five times in one PR when a single artifact can be reused.

## 55. Artifact Promotion

Better:

```text
build once
↓
scan
↓
sign
↓
promote
```

than:

build dev, build staging, build production

and getting different binaries.

## 56. Release Pipeline

The standard:

```text
merge
↓
CI
↓
build
↓
scan
↓
SBOM
↓
provenance
↓
sign
↓
immutable artifact
↓
release
↓
deploy
```

## 57. Deployment

Deployment must use:

artifact digest

not:

latest

## 58. Rollback

Every release must have:

previous known-good artifact

and a simple rollback mechanism.

## 59. Canary / Progressive Rollout

For critical components:

```text
small %
↓
observe
↓
expand
```

If SLOs degrade:

stop

or:

rollback

## 60. Post-Deployment Verification

After deployment, automatically:

- health
- smoke test
- synthetic Telegram flow
- metrics
- errors
- queue

## 61. Auto Rollback

Allow for predefined failure conditions:

- health failure
- error spike
- critical synthetic failure

But only after rollback has been proven safe.

## 62. Developer Feedback

CI must show not:

job failed

but:

```text
Security scan failed
2 secrets detected
file: ...
fix: ...
```

## 63. Unified Diagnostics

All local/CI tools use identical:

- error codes
- failure categories
- messages

so that:

local failure

and:

CI failure

are recognizable as the same problem.

## 64. Operational Telemetry

The baseline tooling itself must be measured:

- check duration
- CI duration
- failure frequency
- baseline adoption
- tool failures
- false positives

## 65. Baseline Health

Track centrally:

- repositories on current baseline
- repositories outdated
- security exceptions
- missing hooks
- missing CI
- drift
- failed scheduled checks

## 66. Automatic Drift Detection

Periodically:

```text
scan repositories
↓
compare baseline
↓
detect drift
↓
create PR
```

## 67. Automatic Baseline PR

For example:

baseline v1.9

→ automatically open a PR in every repository.

The PR contains:

- what changed
- security impact
- tool updates
- breaking changes

## 68. Dependency Updates

Dependabot/Renovate PRs must pass the same pipeline:

- security
- tests
- build
- performance where relevant

## 69. Major Updates

Major updates of:

- PHP
- Composer dependencies
- Node
- Docker base image
- Async Kernel
- Telegram library

receive extended validation.

## 70. Canary Baseline

Apply new baseline versions first to:

a platform-critical test repository

then to:

a few repositories

and only afterwards to:

all repositories

## 71. Change Management

Every baseline change is classified:

- security
- quality
- DX
- performance
- CI
- SRE
- breaking

## 72. Documentation Generation

Commands/profile configuration generate documentation automatically wherever possible.

Goal:

```text
code/config
↓
docs
```

not two independent sources of truth.

## 73. New Repository Acceptance

A new repository counts as ready only after:

- bootstrap
- doctor
- quick check
- CI
- security
- CODEOWNERS
- ruleset

## 74. Production Readiness

A production service must not be released without:

- tests
- security
- observability
- backup
- health
- alerts
- rollback
- runbook

## 75. Platform Readiness

The platform counts as ready after:

- all repositories migrated
- baseline enforced
- CI enforced
- SLO active
- backup verified
- DR tested
- critical runtime benchmarks established

## 76. Final Enforcement

After migration:

hooks

become the standard of the developer environment.

But the final protection:

GitHub branch protection + CI

## 77. Enforcement Principle

Never rely solely on:

developer discipline

All critical rules must have enforcement at a higher level:

```text
local
↓
CI
↓
branch protection
↓
release
↓
runtime
```

## 78. Rollout Order

Recommended order:

1. baseline core
2. command interface
3. profiles
4. hooks
5. security
6. QA
7. CI
8. GitHub enforcement
9. Docker/release
10. observability
11. backup/DR
12. performance
13. auto-remediation

## 79. Why This Order

Do not enable first:

- SRE
- DR
- chaos
- performance gates

while a stable command, test, CI, artifact, and deployment interface does not yet exist.

Otherwise, debugging the infrastructure gets mixed up with debugging the baseline itself.

## 80. Implementation Order Within Each Component

Always:

```text
contract
↓
configuration
↓
implementation
↓
integration
↓
tests
↓
documentation
```

## 81. No Manual Copy-Paste

If one repository has already received the baseline, the next one must be onboarded via:

bootstrap

not:

copy files manually

## 82. Test the Baseline Itself

The security-baseline must have its own:

- unit tests
- integration tests
- fixture repositories
- CI tests
- security tests
- upgrade tests

## 83. Fixture Repositories

Create minimal fixtures:

- generic PHP
- Laravel
- frontend
- Docker
- async library
- full platform application

Validate:

- bootstrap
- check
- fix
- security
- CI generation

## 84. Negative Fixtures

There must be repositories with deliberately introduced problems:

- secret
- vulnerable dependency
- bad Dockerfile
- bad PHP
- bad JS
- bad workflow
- broken test

The baseline must detect all of them correctly.

## 85. Regression Suite

Every baseline bug must turn into:

fixture + test

so that the problem never comes back.

## 86. Security Tool Failure

If an external security tool:

- crashes
- times out
- is unavailable

CI must not automatically count it as:

security passed

It must be:

security infrastructure failure

## 87. Fail Closed

For critical security gates:

`unknown` does not equal `safe`.

## 88. Fail Gracefully

For optional tooling:

warning + remediation

if policy allows it.

But critical CI checks:

fail

## 89. Resource Budget

Each layer gets a budget:

- pre-commit
- pre-push
- PR
- merge
- release
- scheduled

For example:

```text
pre-commit → seconds
PR → minutes
nightly → heavy
```

## 90. Final Acceptance Criteria

The whole system counts as implemented when:

- the baseline repository exists;
- profiles work;
- all commands are standardized;
- setup/doctor/check/fix/security/test work;
- hooks work;
- the AI workflow is documented;
- generic PHP works without Laravel;
- the Laravel profile works;
- the frontend profile works;
- the Docker profile works;
- the Async Kernel has a dedicated quality/performance profile;
- CI is reusable;
- GitHub rules are enforced;
- dependencies are checked automatically;
- secrets are detected automatically;
- Docker images are scanned and signed;
- SBOM/provenance are produced;
- artifacts are immutable;
- observability is connected;
- SLOs are defined;
- alerts have runbooks;
- backup is verified;
- restore is tested;
- DR is tested;
- rollback is tested;
- a performance baseline is established;
- repository drift is detected;
- the baseline is updated through automated PRs;
- new repositories are created automatically;
- existing repositories are migrated automatically;
- security debt does not block migration, but new problems do get blocked;
- the tooling has its own regression tests;
- the entire lifecycle is reproducible.

## 91. Final Model of the Entire System

```text
PLATFORM
▼
SECURITY BASELINE
│
┌───────────────────┼───────────────────┐
▼                   ▼                   ▼
PROFILES            COMMANDS             CI/CD
│                   │                   │
┌────┼────┐              │             ┌────┼────┐
▼    ▼    ▼              ▼             ▼    ▼    ▼
PHP Laravel JS/Docker   Developer       QA Security Release
│                   │
└──────────┬────────┘
▼
REPO
│
┌────────────┼────────────┐
▼            ▼            ▼
Local          PR           CI
│            │            │
└────────────┼────────────┘
▼
Artifact
│
▼
Deploy
│
┌────────┼────────┐
▼        ▼        ▼
SRE       Backup    Runtime
│        │        │
└────────┼────────┘
▼
Recovery
│
▼
Improvement
│
└──────→ Baseline
```

## 92. Key Takeaway

Doc 11 must be the single implementation/rollout document, while 01–10 are the specifications of what the system must provide.

That is:

```text
01–10 = WHAT / WHY / CONTRACTS
11    = HOW TO BUILD + HOW TO ROLL OUT
```

And the most important part for your platform:

```text
                   ┌──────────────────────┐
                   │   Security Baseline  │
                   └──────────┬───────────┘
                              │
      ┌───────────────────────┼───────────────────────┐
      ▼                       ▼                       ▼
 PHP/Libraries            Applications           Async Runtime
      │                       │                       │
      └───────────────────────┼───────────────────────┘
                              ▼
                     Unified Developer API
                              │
            ┌─────────────────┼─────────────────┐
            ▼                 ▼                 ▼
         Local              CI/CD              SRE
            │                 │                 │
            └─────────────────┼─────────────────┘
                              ▼
                          Production
                              │
            ┌─────────────────┼─────────────────┐
            ▼                 ▼                 ▼
         Observe            Backup            Recover
                              │
                              └──────→ Improve
```

This is exactly how the eleven-document architecture should stay: the earlier documents define the individual contracts, and 11 ties them together into a single implementation and rollout, without turning the SDD itself into an endless operational manual.
