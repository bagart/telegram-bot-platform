01 — Platform DevSecOps Baseline — Architecture & Baseline
Status: Draft
Version: 0.2
Owner: platform-team
Updated: 2026-08-21
Depends on: —
Implementation: 11-implementation-and-rollout.md

---

# Platform DevSecOps Baseline — Architecture & Baseline

**Status:** Draft
**Scope:** PHP platform, Composer libraries, Laravel applications, frontend/admin, Docker, GitHub CI/CD, Telegram platform and async runtime
**Document role:** Architecture / SDD
**Implementation:** Defined separately in `11-implementation-and-rollout.md`

---

## 1. Purpose

The Platform DevSecOps Baseline provides a single, versioned engineering standard for all repositories in the platform.

The baseline covers the complete repository lifecycle:

```text
create
  ↓
bootstrap
  ↓
clone
  ↓
develop
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
release
  ↓
deploy
  ↓
observe
  ↓
operate
```

The system must provide:

* security by default;
* consistent QA;
* predictable SRE practices;
* supply-chain protection;
* automated repository configuration;
* fast developer feedback;
* authoritative CI enforcement;
* automated detection of configuration drift;
* minimal developer cognitive load;
* first-class support for AI-assisted development.

The desired developer experience is:

```bash
./cmd/dev/setup
./cmd/dev/check
./cmd/dev/fix
./cmd/dev/security
./cmd/git/quick-commit "fix: ..."
```

The developer must not need to understand the internal implementation of the baseline to use it.

---

## Set Overview & Reading Order

The DevSecOps Baseline is a numbered document set:

| Document | Role |
| --- | --- |
| `00` | Plan / orchestrator of the document set |
| `01` | Architecture & baseline (this document) |
| `02` | Developer tooling specification |
| `03` | Security & supply chain specification |
| `04` | QA & testing specification |
| `05` | CI/CD & release specification |
| `06` | Runtime operations specification |
| `07` | Resilience & disaster recovery specification |
| `08` | Developer experience & AI specification |
| `09` | Observability & performance specification |
| `10` | Telegram platform & libraries specification |
| `11` | Implementation & rollout |

Read `00` first for plan and orchestration, then specifications `01`–`10`, then `11` for implementation and rollout.

Files under `tasks/` matching `W*.md` are execution briefs for parallel work waves, not specifications.

---

# 2. Core Principles

## 2.1 Every repository is born secure

A repository is not considered ready for development until the required baseline is installed and validated.

The baseline automatically provides, where applicable:

* Git hooks;
* formatting and linting;
* static analysis;
* secret scanning;
* dependency security;
* SAST;
* QA gates;
* CI;
* CODEOWNERS;
* pull request templates;
* branch/ruleset configuration;
* Docker security;
* artifact security;
* release controls;
* observability and runtime controls.

---

## 2.2 CI is authoritative

Local checks exist primarily to provide fast developer feedback.

CI is the authoritative enforcement layer.

Therefore:

```text
local bypass
    ≠
security bypass
```

A developer may technically bypass a local hook only through an explicit, auditable mechanism where necessary.

CI must still enforce all required gates.

The system must never rely on Git hooks as the final security boundary.

---

## 2.3 Secure defaults, minimal developer attention

The baseline must prefer automation over documentation.

Bad:

```text
Developer must remember:
run A
then B
then C
then D
```

Good:

```bash
./cmd/dev/check
```

The baseline should detect the repository technology and automatically activate the applicable checks.

---

## 2.4 Framework-agnostic core

The baseline core MUST NOT depend on Laravel or any other application framework.

Framework-specific behavior is implemented through profiles.

Example:

```text
Baseline
├── generic-php
├── laravel
├── frontend
├── docker
├── github-actions
├── qa
├── sre
└── telegram-platform
```

Laravel functionality must never become a mandatory dependency of generic PHP libraries.

---

## 2.5 Policy over tools

Tools are implementation details.

The architecture defines controls and policies first.

For example:

```text
Policy:
  secrets must not be committed
```

Implementation:

```text
TruffleHog
GitHub Secret Scanning
Push Protection
```

Changing the scanning tool must not require redesigning the baseline architecture.

---

## 2.6 One baseline, many profiles

All repositories use the same baseline architecture.

Profiles extend it.

```text
                 Baseline
                    │
       ┌────────────┼────────────┐
       │            │            │
      PHP        Frontend      Docker
       │
    Laravel
       │
Telegram Platform
       │
 Async Kernel
```

A repository may activate multiple profiles.

---

# 3. Scope

The baseline applies to:

### Core libraries

* pure PHP libraries;
* Composer packages;
* async runtime libraries;
* Telegram libraries;
* shared platform libraries.

### Applications

* Laravel applications;
* Telegram bot applications;
* management applications;
* frontend/admin applications.

### Infrastructure

* Dockerfiles;
* Docker images;
* Docker Compose;
* GitHub Actions;
* release pipelines;
* deployment configuration.

### Runtime

* workers;
* async processes;
* queue consumers;
* HTTP services;
* Telegram polling/webhook infrastructure.

---

# 4. High-Level Architecture

```text
                         Repository
                              │
                              ▼
                    Repository Detection
                              │
                              ▼
                         Baseline
                              │
                 ┌────────────┼────────────┐
                 │            │            │
                 ▼            ▼            ▼
              Policy       Profiles      Version
                 │            │
                 └──────┬─────┘
                        ▼
                     Runners
                        │
             ┌──────────┼──────────┐
             ▼          ▼          ▼
          Security      QA        SRE
             │          │          │
             └──────────┼──────────┘
                        ▼
                       Gates
                        │
       ┌────────────────┼─────────────────┐
       ▼                ▼                 ▼
     Local              CI              Release
       │                │                 │
       ▼                ▼                 ▼
 Developer          Merge Gate       Production
 Feedback                              Artifact
```

---

# 5. Baseline Components

The baseline consists of six logical components.

## 5.1 Baseline

The versioned engineering standard.

Example:

```text
baseline v1.0.0
```

Defines:

* supported controls;
* default policies;
* profile contracts;
* runner contracts;
* gate contracts;
* repository integration;
* compatibility rules.

---

## 5.2 Policy

Defines what is required.

A policy determines:

* whether a control is enabled;
* severity;
* failure behavior;
* scope;
* exceptions;
* thresholds;
* profile-specific overrides.

The policy must be machine-readable.

Conceptually:

```yaml
security:
  secrets:
    required: true
    fail_on_detection: true

quality:
  phpstan:
    required: true

docker:
  trivy:
    required: true
```

The exact schema is defined in `03-security-and-supply-chain.md` and related SDDs.

---

## 5.3 Profile

A profile activates controls appropriate to a repository type.

Examples:

```text
generic-php
laravel
frontend
docker
github-actions
qa
sre
telegram-platform
async-kernel
async-tg-lib
```

Profiles may:

* add controls;
* configure runners;
* define thresholds;
* add tests;
* define architecture rules;
* add runtime requirements.

Profiles MUST NOT weaken mandatory baseline controls unless the policy explicitly permits an exception.

---

## 5.4 Runner

A runner executes one technical control.

Examples:

```text
secret scanner
PHPStan
Semgrep
Composer audit
npm audit
Trivy
Hadolint
actionlint
PHPUnit
Pest
Infection
benchmark runner
```

The runner layer normalizes tool-specific behavior into a common result format.

Conceptually:

```text
Runner
├── detect applicability
├── prepare
├── execute
├── collect output
├── normalize result
└── return result
```

---

## 5.5 Gate

A gate determines when controls execute and whether their results block progress.

Primary gates:

```text
pre-commit
pre-push
PR
main
nightly
release
runtime
```

Different gates may execute different subsets of controls.

---

## 5.6 Repository Integration

The baseline is materialized into the repository through:

```text
cmd/
security/
tools/
.github/
configuration
documentation
```

The repository contains enough integration to operate independently and deterministically from the selected baseline version.

---

# 6. Repository Detection

The baseline MUST automatically detect repository characteristics.

Examples:

```text
composer.json          → PHP
laravel/framework      → Laravel
package.json           → frontend
Dockerfile             → Docker
.github/workflows/*    → GitHub Actions
PHP async runtime      → async profile
Telegram dependencies  → Telegram profile
```

Detection must be deterministic.

A repository may explicitly override detection when necessary, but an override must be declared and validated.

Example:

```text
Detected:
  PHP
  Laravel
  Docker
  GitHub Actions

Active:
  generic-php
  laravel
  docker
  github-actions
```

---

# 7. Automatic Profile Activation

The normal developer workflow must not require manual selection of every profile.

The baseline should calculate:

```text
Repository
    ↓
Detection
    ↓
Applicable profiles
    ↓
Effective policy
```

A newly introduced technology must automatically introduce its required controls.

Example:

```text
Existing repository:
PHP + Laravel

Developer adds:
Dockerfile

Next baseline validation:

+ docker profile enabled
+ Dockerfile lint
+ image scan
+ SBOM
+ provenance
```

This prevents security drift caused by repository evolution.

---

# 8. Effective Policy

The final policy is calculated from:

```text
baseline defaults
        +
repository profiles
        +
platform policy
        +
explicit repository configuration
        +
approved exceptions
```

Precedence must be deterministic.

Recommended precedence:

```text
1. hard baseline constraints
2. platform security policy
3. profile policy
4. repository policy
5. approved exception
```

A lower-level configuration must never silently weaken a higher-level mandatory control.

An approved exception acts **point-wise**: it exempts one specific rule, finding or path, always carries scope, reason, owner and expiry, and can never weaken a REQUIRED baseline control globally. Global weakening of a REQUIRED control requires a baseline version change.

---

# 9. Control Classification

Every control must have an explicit classification.

```text
REQUIRED
RECOMMENDED
OPTIONAL
FORBIDDEN
```

### REQUIRED

Failure blocks the applicable gate.

### RECOMMENDED

Failure produces a warning unless promoted by policy.

### OPTIONAL

Disabled unless explicitly enabled.

### FORBIDDEN

The repository must not use the behavior.

Example:

```text
FORBIDDEN:
git commit --no-verify
```

or:

```text
FORBIDDEN:
Docker image deployment using latest
```

---

# 10. Severity

Security and reliability findings use normalized severity:

```text
info
low
medium
high
critical
```

The gate policy determines whether a severity blocks execution.

Example:

```text
critical → fail
high     → fail
medium   → warn
low      → informational
```

The exact thresholds are policy-controlled.

Individual tools must not independently decide the final repository gate behavior.

---

# 11. Exceptions

Exceptions are first-class objects.

An exception MUST contain:

```text
rule
reason
owner
created
expires
```

Example:

```yaml
exception:
  rule: example-rule
  reason: "Verified false positive"
  owner: platform-team
  expires: 2026-12-01
```

Permanent untracked suppressions are forbidden.

Exceptions act **point-wise**. An approved exception exempts one specific rule, finding or path; it always has explicit scope, reason, owner and expiry. An exception can never weaken a REQUIRED baseline control globally — global weakening requires a baseline version change.

The system should report:

```text
expired exception
unknown exception
ownerless exception
invalid exception
```

Expired exceptions must fail the appropriate gate.

---

# 12. Baseline Versioning

The baseline is versioned independently from repositories.

Example:

```text
v1.0.0
v1.1.0
v2.0.0
```

Repositories consume an immutable baseline version.

The baseline version determines:

* policy;
* runner definitions;
* profiles;
* commands;
* hooks;
* CI templates;
* security rules;
* QA rules.

Repositories must be able to determine exactly which baseline version they use.

---

# 13. Baseline Updates

Baseline updates must be automated.

Preferred mechanism:

```text
New baseline
     ↓
update detection
     ↓
automated PR
     ↓
repository CI
     ↓
review
     ↓
merge
```

The system must not silently mutate repository security configuration.

Updates should be reviewable as normal changes.

---

# 14. Baseline Drift

A repository may drift from the expected baseline because files or settings are manually changed.

The baseline must detect:

```text
missing file
modified workflow
modified hook
missing security check
wrong version
modified policy
missing CODEOWNERS
missing required configuration
```

Example:

```text
Expected baseline: v1.8.0
Repository baseline: v1.7.0

FAIL: baseline outdated
```

Or:

```text
Expected:
security workflow SHA = X

Actual:
security workflow SHA = Y

FAIL: baseline drift
```

Drift detection is part of CI.

---

# 15. Repository Lifecycle

The baseline owns the engineering lifecycle:

```text
CREATE
  ↓
BOOTSTRAP
  ↓
DEVELOP
  ↓
COMMIT
  ↓
PUSH
  ↓
PR
  ↓
MERGE
  ↓
BUILD
  ↓
RELEASE
  ↓
DEPLOY
  ↓
OBSERVE
  ↓
OPERATE
```

Each stage has explicit controls.

---

# 16. Local Development Gates

Local execution is optimized for speed.

### Pre-commit

Target:

```text
seconds
```

Run:

* changed-file formatting;
* syntax;
* fast lint;
* fast static checks;
* secret scanning.

### Pre-push

Target:

```text
tens of seconds where practical
```

Run:

* relevant tests;
* static analysis;
* fast security;
* dependency checks where inexpensive.

Heavy checks should not be mandatory on every commit.

---

# 17. CI Gates

CI is the authoritative quality and security gate.

PR CI may include:

```text
format
lint
static analysis
unit tests
integration tests
contract tests
architecture tests
security
dependency scanning
secret scanning
Docker scanning
```

Main/nightly CI may additionally include:

```text
E2E
mutation testing
extended compatibility matrix
load testing
soak testing
performance regression
```

---

# 18. Release Gate

A production artifact must pass:

```text
tests
security
dependency policy
Docker security
SBOM
provenance
artifact signing
artifact verification
release policy
```

Production deployment must use immutable artifact identity.

Preferred:

```text
image@sha256:<digest>
```

Never:

```text
latest
```

---

# 19. Runtime Gate

Runtime systems must provide:

* health checks;
* readiness;
* metrics;
* structured logs;
* traces where applicable;
* SLO monitoring;
* queue monitoring;
* resource monitoring;
* failure detection.

Runtime controls are specified in the SRE SDDs.

---

# 20. Developer Interface

The baseline exposes a stable command interface.

Minimum:

```bash
./cmd/dev/setup
./cmd/dev/doctor
./cmd/dev/check
./cmd/dev/fix
./cmd/dev/test
./cmd/dev/lint
./cmd/dev/security

./cmd/git/commit
./cmd/git/quick-commit
./cmd/git/prepush

./cmd/deps/audit
./cmd/deps/outdated
./cmd/deps/update

./cmd/docker/build
./cmd/docker/scan

./cmd/ci/check
./cmd/ci/validate
```

The internal implementation may change without changing these command contracts unless the baseline version explicitly introduces a breaking change.

---

# 21. AI-Agent Compatibility

The baseline is designed for autonomous coding agents.

Agents must be able to discover:

```text
AGENTS.md
README.md
cmd/
security/
baseline configuration
```

The standard workflow is:

```text
read repository instructions
        ↓
inspect repository
        ↓
implement
        ↓
./cmd/dev/fix
        ↓
./cmd/dev/check
        ↓
./cmd/dev/security
        ↓
review diff
        ↓
./cmd/git/quick-commit
        ↓
push
        ↓
CI
```

Agents must not:

* disable security checks;
* modify thresholds to hide failures;
* add unjustified suppressions;
* use `--no-verify`;
* remove tests;
* weaken CI;
* modify baseline policy merely to make a change pass.

---

# 22. Platform-Specific Profiles

The baseline remains generic, but the platform provides additional profiles.

## Telegram Platform

Adds controls for:

* Telegram API;
* bot lifecycle;
* webhook/polling;
* queues;
* rate limits;
* modules;
* processors;
* platform contracts.

## Async Kernel

Adds controls for:

* Fibers;
* scheduler;
* event loop;
* concurrency;
* cancellation;
* timeouts;
* resource lifecycle;
* worker lifecycle;
* race detection;
* deadlock detection;
* memory/FD leak detection;
* performance regression.

## Async TG Library

Adds controls for:

* Telegram transport;
* HTTP transport;
* DNS;
* TLS;
* HTTP/2;
* connection reuse;
* concurrency;
* retry;
* backoff;
* rate limiting;
* transport benchmarks.

These profiles are mandatory for the corresponding platform repositories.

---

# 23. Library vs Application

The baseline distinguishes two major repository classes.

## Library

Typical:

```text
telegram-bot-lib
async-kernel
async-tg-lib
```

Focus:

* API stability;
* backward compatibility;
* contracts;
* PHP compatibility;
* dependency constraints;
* package quality;
* integration tests;
* performance.

## Application

Typical:

```text
Laravel bot
admin
management service
```

Focus:

* application behavior;
* integration;
* E2E;
* deployment;
* runtime;
* observability;
* production security.

The same baseline is used by both, but profiles determine applicable controls.

---

# 24. Dynamic Library Development

Platform libraries may be developed dynamically through the platform's `misc` workflow while production consumers use Composer/vendor packages.

The baseline must explicitly support:

```text
dynamic development
        ↓
local integration
        ↓
library tests
        ↓
package validation
        ↓
Composer artifact
        ↓
consumer integration
```

Dynamic development must not bypass the normal library quality and security controls.

The same source must pass the same relevant checks before becoming a production dependency.

---

# 25. Repository Independence

After bootstrap, a repository should contain enough baseline integration to run its normal checks without depending on a mutable external checkout.

External baseline repositories may provide:

* templates;
* reusable workflows;
* updates;
* shared rules.

But builds and releases must resolve to immutable/versioned inputs.

This prevents:

```text
today's build
≠
tomorrow's build
```

because a mutable external script changed.

---

# 26. Determinism

The baseline must be deterministic.

Given:

```text
same source
same lockfiles
same baseline version
same profile
same policy
```

the expected result should be equivalent.

Tools that introduce non-determinism must be isolated or normalized.

Build artifacts must be reproducible as far as technically practical.

---

# 27. Failure Philosophy

A failure must provide actionable information.

Bad:

```text
FAILED
```

Good:

```text
SECURITY FAILURE

Control: secrets
Rule:    AWS credential
File:    config/example.php
Line:    42

Action:
Remove the secret and rerun:

./cmd/dev/security
```

Every important failure should provide:

* control;
* severity;
* affected resource;
* reason;
* remediation;
* command to reproduce.

---

# 28. Observability of the Baseline

The baseline itself must be diagnosable.

It should expose:

```text
baseline version
active profiles
effective policy
executed runners
skipped runners
failed controls
exceptions
drift
```

A developer should be able to answer:

> Why did this check run?

and:

> Why did this check fail?

without reading implementation code.

---

# 29. Security and Quality Boundaries

Security, QA and SRE have different purposes but share the same execution framework.

```text
                 Runner Engine
                      │
       ┌──────────────┼──────────────┐
       ▼              ▼              ▼
   Security           QA            SRE
       │              │              │
   secrets/SCA      tests         health
   SAST             coverage      observability
   supply chain     contracts     resilience
   Docker           performance   capacity
```

They must not implement independent orchestration systems.

---

# 30. Non-Goals

This baseline does NOT attempt to:

* replace application-specific security design;
* replace human review for high-risk changes;
* guarantee absence of vulnerabilities;
* automatically fix security findings;
* make every check mandatory locally;
* require every repository to use Laravel;
* require every repository to use Docker;
* replace production incident response;
* replace architectural decisions with static analysis.

Automation reduces routine work; it does not eliminate engineering responsibility for critical decisions.

---

# 31. Architectural Invariants

The following are mandatory invariants.

### INV-01 — CI authority

Local hooks cannot bypass CI enforcement.

### INV-02 — Framework independence

Generic baseline code must not depend on Laravel.

### INV-03 — Policy centralization

Tools do not independently define repository policy.

### INV-04 — Deterministic profiles

The same repository state must produce the same applicable profiles.

### INV-05 — Immutable release identity

Production artifacts are identified by immutable digest/version.

### INV-06 — No permanent silent exceptions

Security exceptions require reason, owner and expiry.

### INV-07 — No hidden bypass

Security checks cannot be silently disabled.

### INV-08 — Baseline drift is detectable

Manual removal or modification of required controls must be detected.

### INV-09 — Fast developer feedback

Heavy checks belong primarily to CI/nightly/release, not every commit.

### INV-10 — Platform-specific logic belongs in profiles

Telegram, Laravel and async-runtime behavior must not leak into generic baseline code.

### INV-11 — One command interface

Developer-facing commands remain stable even if internal runners change.

### INV-12 — Baseline itself is versioned

A repository must always have an identifiable baseline version.

---

# 32. Success Criteria

The architecture is successful when a developer can create a repository and reach a secure development state without manually assembling the toolchain.

Expected workflow:

```text
create repository
      ↓
./cmd/dev/setup
      ↓
automatic detection
      ↓
profiles activated
      ↓
hooks installed
      ↓
GitHub configuration generated
      ↓
initial checks
      ↓
ready for development
```

During development:

```text
edit
 ↓
./cmd/dev/fix
 ↓
./cmd/dev/check
 ↓
./cmd/git/quick-commit
 ↓
push
```

The developer should not need to remember:

* which security scanner to run;
* which static analyzer to run;
* which dependency audit to run;
* which Docker scanner to run;
* which tests are applicable;
* which profile applies;
* which CI workflow performs the authoritative check.

The baseline determines this automatically.

---

# 33. Document Boundaries

This document defines the architecture and contracts of the baseline.

The set consists of:

```text
00-remediation-plan.md                      — plan & orchestrator (execution briefs in tasks/)
01-architecture-and-baseline.md             — architecture & baseline contracts
02-developer-tooling.md                     — developer CLI, hooks, commit workflow
03-security-and-supply-chain.md             — secrets, SCA, SAST, artifacts
04-qa-and-testing.md                        — QA & testing strategy
05-ci-cd-and-release.md                     — CI/CD, release, rollback
06-runtime-operations.md                    — runtime, shutdown, health endpoints
07-resilience-and-disaster-recovery.md      — backup, restore, DR
08-developer-experience-and-ai.md           — DX & AI-agent platform
09-observability-and-performance.md         — observability, SLO, performance/load
10-telegram-platform-and-libraries.md       — Telegram platform & libraries
11-implementation-and-rollout.md            — implementation & rollout
```

No individual tool should receive its own architectural document unless implementation complexity later proves that a dedicated SDD is necessary.

---

# 34. Final Architecture

The final conceptual model is:

```text
                         PLATFORM
                            │
                            ▼
                     SECURITY BASELINE
                            │
              ┌─────────────┼─────────────┐
              │             │             │
           POLICY        PROFILES       VERSION
              │             │
              └──────┬──────┘
                     ▼
                AUTO-DETECTION
                     │
                     ▼
                RUNNER ENGINE
                     │
        ┌────────────┼────────────┐
        ▼            ▼            ▼
     SECURITY        QA          SRE
        │            │            │
        └────────────┼────────────┘
                     ▼
                    GATES
                     │
       ┌─────────────┼─────────────┐
       ▼             ▼             ▼
     LOCAL           CI          RELEASE
       │             │             │
       ▼             ▼             ▼
   developer       merge       artifact
   feedback        gate        verification
                                   │
                                   ▼
                                DEPLOY
                                   │
                                   ▼
                              OBSERVABILITY
                                   │
                                   ▼
                              SRE / DR
```

The central architectural rule is:

> **One baseline, one policy model, one profile system, one runner engine, one gate model — with Security, QA and SRE implemented as controls over the same lifecycle.**

And the operational rule is:

> **Automate everything that can be deterministic; make CI authoritative; keep local feedback fast; make exceptions explicit and temporary; make the secure path the easiest path.**

---

# 35. Invariant ID Registry

Every invariant ID in the set has exactly one definition site; other documents reference IDs instead of redefining them. The registry below lists each prefix, its owning document, the covered numeric range and the topic. The `SP-` prefix is reserved for supply-chain-specific invariants in document 03; no `SP-nn` identifiers are currently defined.

| Prefix | Document | Range | Topic |
| --- | --- | --- | --- |
| INV- | 01-architecture-and-baseline.md | 01-12 | Architecture & baseline |
| DEV- | 02-developer-tooling.md | 01-10 | Developer tooling |
| SEC- | 03-security-and-supply-chain.md | 01-12 | Security & supply chain |
| QA- | 04-qa-and-testing.md | 01-06 | QA & testing |
| CICD- | 05-ci-cd-and-release.md | 01-10 | CI/CD & release |
| OPS- | 06-runtime-operations.md | 01-12 | Runtime operations |
| DR- | 07-resilience-and-disaster-recovery.md | 01-10 | Resilience & disaster recovery |
| DX- | 08-developer-experience-and-ai.md | 01-12 | Developer experience & AI |
| OBS- | 09-observability-and-performance.md | 01-12 | Observability & performance |
| TG- | 10-telegram-platform-and-libraries.md | 01-08 | Telegram platform & libraries |
