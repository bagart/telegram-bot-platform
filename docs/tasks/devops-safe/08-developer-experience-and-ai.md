08 — Developer Experience & AI
Status: Draft
Version: 0.2
Owner: platform-team
Updated: 2026-08-21
Depends on: 01-architecture-and-baseline.md, 02-developer-tooling.md
Implementation: 11-implementation-and-rollout.md

## 1. Goal

Make platform development as simple as possible.

A developer or AI agent should need to know a few standard commands and must not have to remember the internal implementation of security, QA, CI, SRE, or release tooling.

The primary interface:

```
./cmd/dev/setup
./cmd/dev/doctor
./cmd/dev/check
./cmd/dev/fix
./cmd/dev/test
./cmd/dev/security
./cmd/git/quick-commit "..."
```

## 2. One Interface

Every repository must provide the same structure:

```
cmd/
├── dev/
├── git/
├── deps/
├── docker/
├── ci/
└── ops/
```

Specific commands may be absent if the profile does not support them.

For example, a library without Docker is not required to have `cmd/docker/*`.

## 3. Developer Lifecycle

The standard path:

```
setup
↓
doctor
↓
develop
↓
fix
↓
check
↓
quick-commit
↓
push
↓
PR
```

Most actions are automated.

## 4. Setup

`./cmd/dev/setup` prepares the environment: detects the profile, installs dependencies and hooks, configures local tooling, and validates the configuration. The setup contract is defined in `02-developer-tooling.md`.

Setup must be idempotent: running it repeatedly must never break the environment.

## 5. Doctor

`./cmd/dev/doctor` verifies the environment: required binaries, versions, extensions, environment variables, permissions, hooks, lockfiles, and security tooling — reporting readiness or an exact fix. The doctor contract is defined in `02-developer-tooling.md`.

## 6. Automatic Tool Detection

The baseline must detect on its own whether the repository is Laravel, frontend, Docker, async-runtime, library, or application — without making the developer manually select dozens of tools.

## 7. Profile Resolution

For example, a `composer.json` with a Laravel dependency automatically enables the `php` and `laravel` profiles. A `Dockerfile` enables `docker`. Frontend manifests (`package.json`, `pnpm-lock.yaml`) enable the frontend profile.

## 8. Local Tool Bootstrap

If a tool is missing, setup must detect it and either install it or report exact instructions. Project-local tooling (`vendor/bin/...`, `node_modules/.bin/...`) is preferred so the local version matches CI.

## 9. Tool Version Consistency

Local and CI must use the same versions of PHP, Composer, Node, PHPStan, Pint, ESLint, Semgrep, and Trivy wherever practically possible. The developer must not run PHPStan X while CI runs PHPStan Y and get different results.

## 10. cmd/dev/fix

`./cmd/dev/fix` runs the applicable safe automatic fixers (Pint/PHP-CS-Fixer, Prettier, ESLint `--fix`, Stylelint `--fix`) and re-checks afterwards. The fix contract is defined in `02-developer-tooling.md`.

## 11. Safe Fix Policy

Only deterministic safe fixes may run automatically. Never fix automatically: security findings, business logic, dependency vulnerabilities, database migrations, authentication logic, authorization.

## 12. cmd/dev/check

`./cmd/dev/check` is the developer's primary command: it detects the profile and runs only the applicable checks (format, syntax, lint, static analysis, tests, security, dependency checks). The check contract is defined in `02-developer-tooling.md`.

## 13. Check Levels

- `--quick` — seconds.
- `--full` — full local validation.
- `--ci` — as close to CI as possible.

## 14. Intelligent Incremental Checks

`check` must analyze `git diff` and run only relevant fast checks. For example, changes in `docs/*.md` must not trigger PHPStan or a Docker build.

## 15. Dependency Changes

If `composer.json`, `composer.lock`, `package.json`, or `pnpm-lock.yaml` changed, validation is automatically extended with a dependency audit, lock validation, security checks, and relevant tests.

## 16. Docker Changes

If `Dockerfile`, `docker/*`, `.dockerignore`, or `compose*` changed, hadolint, Docker validation, build, and image scan run automatically at the corresponding check level.

## 17. CI Changes

If `.github/workflows/*` changed, actionlint and workflow security checks run automatically, with an elevated review requirement.

## 18. Core / Async Changes

Changes in the Async Kernel, async TG-lib, transport, scheduler, runtime, or queue must automatically enable additional unit, integration, concurrency, timeout, and cancellation tests plus performance checks.

## 19. cmd/dev/test

`./cmd/dev/test` is the unified test interface (`unit`, `integration`, `e2e`, `async`, `telegram`, `--changed`, `--full`). These commands are aliases over a single orchestration layer, not independent implementations. The test contract is defined in `02-developer-tooling.md`.

## 20. Test Selection

A change in `src/Async/*` must not run the whole frontend test suite. The baseline must maintain a dependency/ownership mapping.

## 21. Developer Security

`./cmd/dev/security` is the single interface for secrets, SAST, dependency, configuration, and Docker checks. The security contract is defined in `02-developer-tooling.md`.

Security tool failures must be understandable to a human.

## 22. Git Interface

The git commands `./cmd/git/commit`, `./cmd/git/quick-commit`, `./cmd/git/prepush`, and `./cmd/git/branch` are standardized so the developer never manually combines `git diff`, `git add`, formatter, tests, secret scanner, and `git commit`. The git interface contract is defined in `02-developer-tooling.md`.

## 23. Quick Commit

`./cmd/git/quick-commit "fix: ..."` chains changed-file detection, safe fixes, quick validation, secret scan, diff review, staging, and commit. The quick-commit contract is defined in `02-developer-tooling.md`.

## 24. Quick Commit Safety

Never use `git commit --no-verify`; never hide failures; never run an uncontrolled `git add .` that could add unexpected, generated, or secret files. The safety rules are defined in `02-developer-tooling.md`.

## 25. Commit Scope

Quick commit must explicitly show the files to commit and especially warn about new files, `.env`, credentials, large files, and generated artifacts.

## 26. Commit Message

Commit messages must use the canonical prefix list defined in `02-developer-tooling.md` §21; this document does not redefine it.

If a message is invalid, the tool must suggest a correction instead of failing with a bare `exit 1`.

## 27. Branch Commands

`./cmd/git/branch` can automate create, switch, status, and cleanup.

Branch naming: `feature/*`, `fix/*`, `security/*`, `refactor/*`.

## 28. Dependency Commands

`./cmd/deps/audit`, `./cmd/deps/outdated`, and `./cmd/deps/update` form the dependency interface; updates must be safe, with explicit confirmation for major updates. The contract is defined in `02-developer-tooling.md`.

## 29. Docker Commands

`./cmd/docker/build`, `./cmd/docker/scan`, and `./cmd/docker/shell` provide the same interface regardless of the specific compose setup. The contract is defined in `02-developer-tooling.md`.

## 30. CI Commands

`./cmd/ci/check` reproduces CI results locally as closely as possible; `./cmd/ci/validate` verifies workflows, configuration, profiles, and required files. The contract is defined in `02-developer-tooling.md`.

## 31. Ops Commands

For repositories that have a runtime: `./cmd/ops/status`, `./cmd/ops/health`, `./cmd/ops/diagnose`, `./cmd/ops/logs`, `./cmd/ops/restart`, `./cmd/ops/drain`.

All dangerous operations require explicit confirmation.

## 32. Output Contract

All commands must use a single output format:

```
✓ passed
⚠ warning
✗ failed
ℹ info
```

On failure: what failed, why, and how to fix it.

## 33. Exit Codes

Exit codes are standardized platform-wide in `02-developer-tooling.md` §3 (`0` success · `1` validation failure · `2` invalid command/configuration · `3` missing required environment/tool · `4` infrastructure/tool execution failure · `5` baseline/policy failure). This document does not redefine them.

This matters for CI, AI agents, scripts, and IDEs.

## 34. Machine-readable Output

All commands must support machine-readable output via `--format=json` where useful (`--json` is a documented alias of `--format=json`; the general flag is `--format=text|json|github`). For example:

```
./cmd/dev/doctor --format=json
```

This lets AI/automation tools read results without parsing human output.

## 35. Quiet Mode

`--quiet` prints only errors and the final summary — intended for automation. The flag contract is defined in `02-developer-tooling.md`.

## 36. Verbose Mode

`--verbose` shows commands, timings, tool versions, and paths — intended for debugging. The flag contract is defined in `02-developer-tooling.md`.

## 37. Timing

Commands must report per-stage timings (e.g. static analysis, tests, secret scanning) to expose slow stages. The contract is defined in `02-developer-tooling.md`.

## 38. Failure Diagnostics

On failure, keep a machine-readable result in `.tmp/dev-check/` or a similar ignored directory. Never commit diagnostic artifacts.

## 39. Documentation

The README must contain only the primary interface: Setup, Check, Fix, Security, Test, Quick commit, Doctor. Detailed documentation lives in `docs/`.

## 40. Command Discovery

Support `./cmd/help` or `./cmd/dev/help` listing the available commands. A developer must not have to search the filesystem for commands.

## 41. Self-documenting Commands

Every command must have `--help` showing purpose, usage, options, examples, and risk. The `--help` contract is defined in `02-developer-tooling.md`.

## 42. AI-Agent Contract

`AGENTS.md` becomes the machine-readable operational contract. It must define: repository architecture, allowed commands, required checks, security restrictions, dependency policy, testing policy, commit policy, release policy.

## 43. AI Golden Workflow

The AI agent must follow:

```
Read AGENTS.md
↓
Inspect
↓
Plan
↓
Modify
↓
./cmd/dev/fix
↓
./cmd/dev/check
↓
./cmd/dev/security
↓
git diff
↓
./cmd/git/quick-commit
```

## 44. AI Must Not

Without an explicit human decision, the AI is forbidden from: `--no-verify`, disabling CI, weakening rules, adding security ignores, reducing test coverage, silencing warnings, deleting failing tests, changing security thresholds, committing secrets.

## 45. AI Failure Loop

On failure:

```
failure
↓
read diagnostic
↓
identify cause
↓
modify code
↓
rerun
```

Never:

```
failure
↓
disable check
```

## 46. AI Context

The AI must have access to the minimally necessary documentation: `AGENTS.md`, `README.md`, `CONTRIBUTING.md`, `SECURITY.md`, architecture docs, the relevant profile. Do not force the agent to read the entire repository.

## 47. AI Change Classification

Before modifying anything, the AI must classify the change: code, test, security, dependency, database, Docker, CI, runtime, public API. This determines the mandatory checks.

## 48. Sensitive Change Detection

Apply especially elevated control to: authentication, authorization, crypto, secrets, Async Kernel, Telegram transport, queue, database migrations, CI, Docker, deployment. Such changes must automatically require extended validation.

## 49. Developer Guardrails

IDE/editor integration may run `./cmd/dev/check --quick` on save/commit, but running heavy checks on every save must not be mandatory.

## 50. Local Environment Isolation

Local setup must not require production credentials. Where possible, the developer environment must use local credentials, test bots, mock services, and local containers.

## 51. .env

Provide `.env.example` but never commit `.env` to Git. Setup must be able to create a safe local `.env`.

## 52. Reproducible Development Environment

Where possible, use Docker, a devcontainer, or another reproducible mechanism. But the baseline must not make Docker mandatory for all PHP libraries.

## 53. PHP Library DX

For Composer libraries, `composer install` + `./cmd/dev/check` must be enough. No Laravel, Node, or Docker required.

## 54. Async Library DX

For the Async Kernel / async TG-lib, add:

```
./cmd/dev/test async
./cmd/dev/test integration
./cmd/dev/test stress
./cmd/dev/bench
```

Expensive benchmarks must not run as part of the normal check.

## 55. Benchmark Interface

Standardize `./cmd/dev/bench` with the profiles quick, full, transport, scheduler, async. Results cover throughput, latency, memory, CPU.

## 56. Regression Detection

Benchmarks must compare current results against a baseline and report `✓ no regression`, `⚠ 3.2% slower`, or `✗ 18.4% regression`. The threshold must be configurable and account for noise.

## 57. Onboarding

A new developer going through clone → `./cmd/dev/setup` → `./cmd/dev/doctor` → `./cmd/dev/check` must get a working environment without reading 20 pages of documentation.

## 58. New Repository

Creating a new repository: create → select profile → bootstrap baseline → setup CI → setup security → setup commands → setup docs → ready.

## 59. Baseline Updates

All repositories receive baseline updates centrally. For example, baseline v1.8.2 can update CI, security rules, commands, hooks, and profiles without manual copying.

## 60. Pinning

The baseline must be versioned (v1.x), and consumers must use an immutable revision/SHA for CI components.

## 61. Baseline Compatibility

A repository must record its baseline version and profile versions, and doctor must warn:

```
⚠ baseline outdated
Current: 1.7
Recommended: 1.9
```

## 62. Automatic Baseline Update

No unconditional auto-update of production-critical CI. The safe model: new baseline → automated PR → CI → review if required → merge.

## 63. Repository Drift

Automatically verify that a repository has not lost required files, workflows, hooks, `CODEOWNERS`, security configuration, or commands. On drift: `⚠ baseline drift detected`.

## 64. Standard Repository Contract

Every repository must answer the following questions in the same way: How do I setup? How do I test? How do I check? How do I fix? How do I run security? How do I commit? How do I build? How do I release? How do I diagnose?

## 65. Acceptance Criteria

The component is ready when:

- a new developer can start via setup;
- the environment is verified via doctor;
- core operations share a single `cmd/` interface;
- commands behave identically in all profiles;
- commands have `--help`;
- automation supports `--format=json`;
- exit codes are standardized;
- check runs incrementally;
- security is selected automatically by profile/change;
- Docker/PHP/frontend/async changes automatically extend validation;
- quick commit is safe;
- `--no-verify` is never used;
- the AI has a single workflow;
- the AI cannot "fix" a failure by disabling a check;
- the Async Kernel has dedicated test/bench commands;
- performance regressions can be detected automatically;
- repository drift is detected;
- the baseline is updated via a controlled PR;
- the local workflow matches CI as closely as possible;
- documentation does not require memorizing the internal architecture of the tooling.

## 66. Architectural Invariants

### DX-01 — One command, one responsibility

### DX-02 — Same interface everywhere

### DX-03 — Automation over memorization

### DX-04 — Local and CI must agree

### DX-05 — Safe automation by default

### DX-06 — AI uses the same developer interface as humans

### DX-07 — Heavy checks are explicit

### DX-08 — Incremental checks are default

### DX-09 — Every failure explains how to recover

### DX-10 — Repository baseline is centrally maintainable

### DX-11 — Framework-specific logic stays inside profiles

### DX-12 — Critical runtime changes automatically trigger deeper validation

## 67. Final Model

```
DEVELOPER / AI
│
▼
./cmd/dev/setup
│
▼
./cmd/dev/doctor
│
▼
CODE
│
┌────────┴────────┐
▼                 ▼
./cmd/dev/fix     ./cmd/dev/test
│                 │
└────────┬────────┘
▼
./cmd/dev/check
│
▼
./cmd/dev/security
│
▼
./cmd/git/quick-commit
│
▼
PR
│
▼
CI
```

The main idea: 08 is not just a set of convenient shell scripts. It is the single developer/AI API of the platform. Internally, one can swap PHPStan, Semgrep, Trivy, GrumPHP, GitHub Actions, Docker, or the CI structure — but the developer keeps using the same few commands.
