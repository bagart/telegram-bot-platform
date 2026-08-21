# 05 — CI/CD & Release

Status: Draft

Version: 0.2

Owner: platform-team

Updated: 2026-08-21

Depends on: 01-architecture-and-baseline.md, 02-developer-tooling.md, 03-security-and-supply-chain.md, 04-qa-and-testing.md

Implementation: 11-implementation-and-rollout.md

## 1. Goal

Create a single automated CI/CD circuit for the entire platform:

```text
create repository
↓
bootstrap
↓
branch
↓
commit
↓
push
↓
PR
↓
CI
↓
review
↓
merge
↓
build
↓
security
↓
artifact
↓
release
↓
deploy
↓
verify
↓
rollback if required
```

Main principle:

A developer must work on code, not on CI/CD setup.

## 2. Repository Bootstrap

A new repository is created through a single baseline.

Automatically:

```text
repository
↓
profile detection
↓
GitHub configuration
↓
CODEOWNERS
↓
branch rules
↓
CI workflows
↓
security
↓
dependency automation
↓
PR templates
↓
developer commands
```

Manual copying of dozens of files is not required.

## 3. Repository Profiles

Minimal profiles:

- library
- php
- laravel
- frontend
- docker
- async-runtime
- telegram
- application

One repository may carry several profiles.

For example:

```text
telegram-platform
├── php
├── laravel
├── async-runtime
├── telegram
└── docker
```

## 4. CI Architecture

Do not build one giant workflow.

Use independent logical stages:

```text
prepare
│
├── quality
├── security
├── dependencies
├── tests
├── frontend
└── docker
↓
aggregate
↓
build
↓
artifact
↓
release
```

This allows:

- running jobs in parallel;
- retrying only the failed stage;
- seeing the cause of a failure;
- reducing CI time.

## 5. CI Layers

Minimal pipeline:

- 01 prepare
- 02 changed-files analysis
- 03 quality
- 04 tests
- 05 security
- 06 dependencies
- 07 build
- 08 container
- 09 artifact validation
- 10 release

Not all stages run for every repository.

The profile defines applicability.

## 6. Changed Files Analysis

The first CI stage determines:

changed files

and builds:

affected profiles

For example:

```text
composer.lock changed
→ dependency
→ PHP
→ security

Dockerfile changed
→ docker
→ security
→ build

.github/workflows/* changed
→ CI security
→ actionlint
```

## 7. Matrix Builds

For compatibility, use the GitHub Actions matrix.

For example:

PHP: 8.4, 8.5

or:

Node: 22, 24

But not every PR is obliged to run the full compatibility matrix.

The full matrix may run on:

- main
- release
- nightly

## 8. CI Tiers

Three modes:

**PR**

Fast mandatory set.

**Main**

Full regression suite.

**Nightly**

Maximum set:

- full compatibility
- stress
- performance
- dependency checks
- extended integration

This prevents turning every PR into an hour-long pipeline.

## 9. Pull Request Pipeline

A PR must automatically execute:

- format
- lint
- static analysis
- unit tests
- relevant integration tests
- security
- dependency checks
- build validation

For changed components — additional profile-specific checks.

## 10. PR Status

A PR must have one aggregated status:

Platform CI

with an understandable result:

```text
✓ Quality
✓ Tests
✓ Security
✓ Dependencies
✓ Build
```

or:

```text
✗ Security
└─ secret detected
```

A developer must not hunt for a failure across ten workflows.

## 11. Required Checks

Protected branches require:

CI aggregate

and the necessary security checks.

Important:

A required check must be a stable name, even if the internal CI structure changes.

This allows the pipeline to evolve without constant reconfiguration of branch rules.

## 12. Pull Request Template

The PR template must automatically ask:

- What changed?
- Why?
- Tests?
- Security impact?
- Dependencies?
- Docker?
- CI?
- Database?
- Authentication?
- Authorization?
- Performance?

And:

AI-assisted?

## 13. CODEOWNERS

CODEOWNERS automatically assigns reviewers for:

- core
- async kernel
- telegram libraries
- security
- CI
- Docker
- deployment
- dependencies

Critical components must have mandatory owner review.

## 14. Branch Strategy

Base model:

```text
main
↑
PR
↑
feature branch
```

main is protected.

develop is allowed only if the real workflow of the platform uses it.

Do not introduce branches solely "because it is customary".

## 15. Branch Protection

For protected branches:

- PR required
- required CI
- CODEOWNER review where applicable
- no force push
- no deletion

For critical repositories:

- required up-to-date branch
- stale approval dismissal
- conversation resolution

## 16. Rulesets

Prefer GitHub Rulesets as the centralized policy mechanism.

For example:

- production branches
- release tags
- critical repositories

Rules must be governed by the baseline, not manually by each developer.

## 17. Commit Policy

Conventional Commits:

- feat:
- fix:
- refactor:
- perf:
- test:
- docs:
- build:
- ci:
- chore:
- security:

Commit policy is enforced locally.

CI may additionally validate commits/PR metadata where necessary.

The canonical prefix list is defined in `02-developer-tooling.md` (§21); this document does not redefine it.

## 18. Merge Policy

Merge is allowed only if:

```text
required CI = pass
required reviews = pass
security gates = pass
```

A local `--no-verify` must not affect mergeability.

## 19. Dependency Automation

GitHub automatically receives:

Dependabot or Renovate

with the policy:

```text
security updates → priority
minor updates → grouped
major updates → explicit review
```

## 20. Dependency PRs

A dependency PR automatically receives:

- dependency diff
- security scan
- tests
- lockfile validation
- build

For critical dependency changes:

CODEOWNER review

## 21. Security Workflow

A separate security workflow may perform:

- secret scan
- SAST
- dependency audit
- Dependency Review
- configuration checks
- GitHub security integration

It must not duplicate absolutely all checks from CI.

## 22. Docker Workflow

Docker workflow:

```text
Dockerfile validation
↓
build
↓
image scan
↓
SBOM
↓
provenance
↓
sign
↓
push
```

Push to the production registry happens only after the mandatory gates succeed.

## 23. Build Reproducibility

A build must use:

- immutable source commit
- pinned dependencies
- pinned base image
- defined toolchain

The result must be tied to a specific Git commit.

## 24. Artifact Naming

Artifacts must have an immutable identity.

For example:

`app:<git-sha>`

and the final reference:

`app@sha256:<digest>`

## 25. Artifact Metadata

Each release artifact must be linked to:

- repository
- commit
- branch/tag
- build workflow
- build ID
- baseline version
- dependency state
- SBOM
- provenance
- signature

## 26. Registry

The production registry must support:

- immutable artifacts
- retention policy
- access control
- artifact scanning
- signature verification

Deletion of production artifacts must be strictly restricted.

## 27. Release Tags

A release tag:

`vX.Y.Z`

is created only after passing the release gates.

Tags must be protected against:

- force push
- deletion
- unauthorized creation

## 28. Semantic Versioning

For libraries:

`MAJOR.MINOR.PATCH`

is used according to the backward compatibility policy.

Especially important for:

- async kernel
- async tg-lib
- telegram-bot-lib

because these packages sit in the Composer dependency graph of other platform components.

## 29. Library Release

For a Composer library:

```text
commit
↓
CI
↓
compatibility matrix
↓
security
↓
tests
↓
tag
↓
package registry
```

The main artifact for a library is the versioned Composer package.

Platform reality: libraries live in `misc/BAGArt/*` and are consumed via Composer `path` repositories (symlinks under `vendor/bagart/`). The publishing pipeline must therefore:

- validate the path-repo dev setup separately from the published VCS repo;
- tag in the library's own repository;
- publish the versioned Composer package from the real VCS source (never from the path-repo checkout);
- run library tests both via the path-repo (monorepo context) and via a standalone checkout.

## 30. Platform Application Release

For an application:

```text
commit
↓
build
↓
image
↓
scan
↓
SBOM
↓
sign
↓
registry
↓
deploy
```

## 31. Release Gate

Release is forbidden if:

- tests failed
- security failed
- artifact scan failed
- SBOM missing
- provenance missing
- signature missing
- required review missing

## 32. Deployment Strategy

Support profile-based strategies:

- rolling
- blue/green
- canary
- recreate

Not every service is obliged to use complex deployment.

The default must be as safe as possible at minimal operational complexity.

## 33. Environment Promotion

Do not rebuild the artifact between environments.

The correct model:

```text
build once
↓
test
↓
staging
↓
verify
↓
production
```

One immutable artifact passes through environment promotion.

## 34. Deployment Verification

After deployment:

- health
- readiness
- version
- metrics
- error rate

are verified automatically.

If verification fails:

deployment failed

and the rollback policy is triggered.

## 35. Automatic Rollback

Automatic rollback is allowed under clearly defined conditions:

- healthcheck failure
- readiness failure
- critical error rate
- startup failure

A release must not be rolled back automatically based on a single random spike.

Required:

- threshold
- duration
- confidence

## 36. Rollback

Rollback:

```text
current artifact
↓
verification failure
↓
previous known-good artifact
↓
deploy
↓
verify
```

The previous artifact must be immutable and known to the system.

## 37. Database Deployment

Application deployment and database migrations must be coordinated.

Preferred pattern:

```text
expand
↓
deploy compatible application
↓
migrate data
↓
contract
```

Do not combine a destructive migration with a deployment if it breaks rollback.

## 38. Release Channels

The platform may have:

- dev
- staging
- production

Libraries:

- dev
- stable

But do not create many channels without a real need.

## 39. Preview Environments

For large application repositories it is possible to have:

```text
PR
↓
temporary environment
↓
E2E
↓
destroy
```

Use only if it really reduces QA cost.

A preview environment must not become mandatory for every small library.

## 40. Scheduled CI

Nightly pipeline:

- full test matrix
- full security scan
- dependency audit
- performance benchmark
- stress tests

Weekly/monthly:

- deep dependency analysis
- DR/restore checks
- toolchain updates
- baseline verification

## 41. CI Maintenance

CI must automatically detect:

- deprecated Actions
- outdated runtimes
- expired secrets
- expired exceptions
- stale workflows
- unused CI configuration

## 42. Workflow Validation

All workflows pass:

`actionlint`

and security validation.

A change to:

`.github/workflows/**`

is security-sensitive.

## 43. GitHub Token

GITHUB_TOKEN must be used with minimal permissions.

Do not use a PAT when GITHUB_TOKEN or OIDC is sufficient.

## 44. Environment Protection

The production environment must have:

- protected environment
- restricted deployment
- required approvals where appropriate
- secrets scoped to the environment

Automation must not turn into unrestricted production write access.

## 45. Deployment Credentials

Use:

- OIDC
- short-lived credentials
- environment-scoped credentials

instead of permanent:

- AWS_ACCESS_KEY
- DEPLOY_PASSWORD
- LONG_LIVED_TOKEN

wherever the infrastructure supports it.

## 46. Release Observability

Every deployment must produce an event:

- service
- version
- commit
- artifact digest
- environment
- deployment time
- actor

This links:

```text
incident
↔
deployment
↔
commit
```

## 47. Change Tracking

It must be possible to answer:

What changed in production during the last N hours?

Automatically link:

```text
Git commit
→ PR
→ release
→ artifact
→ deployment
```

## 48. Failed Deployment

On failure the system must preserve:

- deployment ID
- artifact
- commit
- logs
- health results
- rollback result

This is necessary for diagnostics.

## 49. Manual Approval

Manual approval is used only where a human gate is genuinely needed.

For example:

- production deployment
- critical security change
- destructive database migration

Do not introduce manual approval for a regular lint/test pipeline.

## 50. Human-in-the-loop

Principle:

A human confirms risk, not mechanical work.

Bad:

- run tests manually
- copy image
- open server
- restart container

Good:

Approve production deployment

after all other actions are performed automatically.

## 51. CI Failure UX

An error must be:

actionable

For example:

```text
✗ dependency-security

Package:
foo/bar

Severity:
HIGH

Fixed version:
3.2.1

Action:
composer update foo/bar
```

and not:

Process exited with code 1

## 52. Retry Policy

CI retry is allowed only for infrastructure failures.

Do not retry automatically, without reason:

- test failure
- security failure
- lint failure
- build failure

## 53. Concurrency Control

For PRs:

```text
new push
↓
cancel obsolete CI
```

An obsolete pipeline must not spend resources after a new commit.

Release workflows must have a stricter concurrency policy.

## 54. Caching

Cache:

- Composer
- npm/pnpm
- PHPStan
- Docker layers
- security databases

where it is safe.

Cache must be:

- scoped
- versioned
- invalidatable

Cache must not become a source of unreliable security results.

## 55. GitHub Actions Cost Guard

Actions minutes are a finite, budgeted resource. Guardrails:

- PR pipelines run changed-surface jobs only (see §6 Changed Files Analysis).
- `concurrency` with cancel-in-progress applies to PR runs, aligned with §53 Concurrency Control.
- The matrix is limited on PR; the full matrix runs only on main/nightly (see §7 Matrix Builds).
- Scheduled (nightly) jobs have an explicit monthly minutes budget.
- Artifact and log retention limits are configured.
- Cost anomalies (job duration / minutes spikes) are a tracked CI metric, tied into CI Maintenance (§41).

## 56. Self-hosted Runners

If self-hosted runners are used:

- ephemeral
- isolated
- minimal privileges
- clean workspace

are preferable to permanent shared runners.

Especially for untrusted PR workloads.

## 57. Runner Security

Untrusted PR code must not run on a runner that has:

- production credentials
- registry admin access
- persistent secrets
- host Docker socket

unless it is an isolated trusted environment.

## 58. Release Automation

Ideal path:

```text
merge
↓
CI
↓
artifact
↓
security
↓
tag/version
↓
publish
↓
deploy
```

The fewer manual steps, the fewer human errors.

## 59. Library Publishing

Composer libraries must be published automatically after a verified release.

Pipeline:

```text
tag
↓
validate tag
↓
run full tests
↓
security
↓
publish
```

Publishing must be impossible for an unsigned/unverified release.

Platform reality: libraries live in `misc/BAGArt/*` and are consumed via Composer `path` repositories (symlinks under `vendor/bagart/`). Publishing must:

- validate the path-repo dev setup separately from the published VCS repo;
- tag in the library's own repository;
- publish the versioned Composer package from the real VCS source (never from the path-repo checkout);
- run library tests both via the path-repo (monorepo context) and via a standalone checkout.

## 60. Release Integrity

It is forbidden to:

- build artifact A
- test artifact B
- deploy artifact C

All stages must reference one immutable artifact identity.

## 61. Acceptance Criteria

The component is considered ready when:

- a new repository automatically receives CI/CD;
- profiles automatically determine the pipeline;
- a PR has a single quality/security gate;
- required checks protect main;
- dependency updates are automated;
- workflows are validated;
- Actions are pinned by SHA;
- permissions are minimal;
- production credentials are isolated;
- OIDC is used wherever possible;
- Docker artifacts are immutable;
- SBOM/provenance/signature are linked to the artifact;
- libraries are published automatically;
- applications follow build once / promote many;
- deployment is verified automatically;
- rollback uses the previous immutable artifact;
- the database migration policy accounts for rollback;
- CI has a retry/concurrency/caching policy;
- flaky and infrastructure failures are distinguished;
- production changes are traceable down to commit/PR;
- the release pipeline is maximally automated;
- manual approvals exist only where a human risk decision is genuinely needed.

## 62. Architectural Invariants

### CICD-01 — Build once, promote many

One artifact passes through all environments.

### CICD-02 — Immutable production

Production never uses a mutable artifact identity.

### CICD-03 — CI is authoritative

The developer's local state does not determine merge/release eligibility.

### CICD-04 — No manual mechanical deployment

Repeatable deployment operations are automated.

### CICD-05 — Human approves risk, not mechanics

A human confirms only genuinely risky actions.

### CICD-06 — Failed release is reversible

Every release has a deterministic rollback.

### CICD-07 — CI itself is secured

Workflows and runners are part of the security boundary.

### CICD-08 — CI should be fast by default

Incremental/parallel execution is used by default; the full pipeline runs at the corresponding stages.

### CICD-09 — One artifact identity

The tested artifact = the published artifact = the deployable artifact.

### CICD-10 — Every production change is traceable

```text
production
↓
artifact
↓
release
↓
commit
↓
PR
```

must be reconstructed automatically.

## 63. Final Model

```text
DEVELOPER
│
▼
PUSH / PR
│
▼
CHANGE IMPACT ANALYSIS
│
┌────────────────────┼────────────────────┐
▼                    ▼                    ▼
QUALITY             SECURITY              TESTS
│                    │                    │
└────────────────────┼────────────────────┘
▼
AGGREGATE
│
┌───┴───┐
│ PASS  │
└───┬───┘
▼
MERGE
│
▼
BUILD
│
┌─────────┼─────────┐
▼         ▼         ▼
SCAN       SBOM   PROVENANCE
│         │         │
└─────────┼─────────┘
▼
SIGN
│
▼
IMMUTABLE ARTIFACT
│
▼
STAGING
│
VERIFY
│
▼
PRODUCTION
│
VERIFY
│
┌──────────┴──────────┐
▼                     ▼
OK                  FAIL
│                     │
▼                     ▼
OBSERVE                ROLLBACK
```

Key idea: this component turns the previous Security + QA + SRE work into an automatic delivery system. The developer makes a push/PR, and the platform itself determines the required checks, assembles a single artifact, verifies it, publishes it, delivers it, and rolls it back when necessary.
