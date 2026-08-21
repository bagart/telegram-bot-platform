02 — Developer Tooling & Local Automation

Status: Draft
Version: 0.2
Owner: platform-team
Updated: 2026-08-21
Depends on: 01-architecture-and-baseline.md
Implementation: 11-implementation-and-rollout.md

## 1. Purpose

Developer tooling provides a single, predictable interface to the DevSecOps baseline.

The developer should not need to know which underlying tools are used.

Instead of:

composer validate
vendor/bin/phpstan analyse
vendor/bin/pint
trufflehog ...
npm audit
semgrep ...
phpunit ...

the standard interface is:

./cmd/dev/check

The tooling must optimize for:

minimum developer attention;
fast feedback;
safe automatic fixes;
changed-file optimization;
consistent behavior for humans and AI agents;
deterministic execution;
useful diagnostics;
no security bypasses.

## 2. Developer Interface

The repository exposes a stable command tree:

cmd/
├── dev/
│   ├── setup
│   ├── doctor
│   ├── check
│   ├── fix
│   ├── test
│   ├── lint
│   └── security
│
├── git/
│   ├── commit
│   ├── quick-commit
│   ├── prepush
│   └── branch
│
├── deps/
│   ├── audit
│   ├── outdated
│   └── update
│
├── docker/
│   ├── build
│   ├── scan
│   └── shell
│
└── ci/
├── check
└── validate

Commands are executable files without mandatory extensions.

## 3. Command Contract

Every command must:

return a meaningful exit code;
print human-readable output;
support CI/non-interactive execution;
avoid interactive prompts unless explicitly requested;
fail safely;
identify the failing control;
provide remediation where possible.

Standard exit codes:

0  success
1  validation/check failure
2  invalid command/configuration
3  missing required environment/tool
4  infrastructure/tool execution failure
5  baseline/policy failure

Standard flags:

--format=text|json|github   (alias: --json)
--quick|--full|--ci         (execution levels)
--verbose
--quiet
--help

Every command must support `--help`.

The flags above are the single definition of the command-line surface; other documents must reference this section instead of redefining it.

The exact implementation may evolve, but the semantic contract should remain stable.

## 4. Common Execution Engine

Commands must not independently implement orchestration.

All commands should ultimately use the same internal execution layer:

cmd/*
│
▼
baseline runner
│
├── detection
├── profile loading
├── policy loading
├── dependency graph
├── runner selection
├── execution
├── result normalization
└── reporting

Therefore:

./cmd/dev/check

and CI should execute the same logical controls.

CI may use different execution parameters, but must not silently implement a separate security system.

## 5. cmd/dev/setup

Purpose:

Make a fresh checkout ready for development.

Expected workflow:

./cmd/dev/setup
│
├── detect environment
├── detect profiles
├── validate baseline
├── install dependencies
├── configure local integration
├── install Git hooks
├── validate tools
├── run initial checks
└── report status

It should detect:

PHP
Composer
Node
npm/pnpm
Docker
Git
required platform tools

The command should be idempotent.

Running it repeatedly must not corrupt the repository or duplicate configuration.

## 6. cmd/dev/doctor

Purpose:

Diagnose the development environment without changing it.

Example:

Development environment


✓ Git             2.x
✓ PHP             8.5.x
✓ Composer        2.x
✓ Node            24.x
✓ pnpm            10.x
✓ Docker          28.x
✓ Git hooks       installed
✓ Baseline        v1.x
✓ Profiles        generic-php, laravel, docker
✓ Lockfiles       valid


Environment is ready.

Failures must be actionable:

✗ PHP version


Required: >= 8.4
Detected: 8.2


Install PHP 8.4+ and rerun:


./cmd/dev/doctor

doctor must not modify the environment.

## 7. cmd/dev/fix

Purpose:

Automatically apply safe deterministic fixes.

Possible operations:

PHP formatter
Prettier
ESLint --fix
Stylelint --fix
safe import/order fixes

The command must distinguish:

SAFE AUTO-FIX

from:

SEMANTIC / SECURITY CHANGE

Security findings must never be automatically rewritten unless the specific rule is explicitly classified as safe.

After modifications:

./cmd/dev/fix
↓
changed files
↓
./cmd/dev/check

The command should report exactly what it changed.

## 8. cmd/dev/check

This is the primary local validation command.

Default behavior:

detect changed repository surfaces
↓
select applicable profiles
↓
run appropriate checks
↓
aggregate results
↓
fail if required control fails

Typical checks:

format
syntax
lint
static analysis
relevant tests
architecture rules
fast security
secret scan
dependency consistency

It should avoid unnecessarily executing unrelated checks.

For example, a documentation-only change should not rebuild a Docker image.

## 9. cmd/dev/check --quick

Fast validation for interactive development.

Target:

seconds, not minutes.

Typical:

changed-file formatting
changed-file lint
syntax
fast static analysis
secret scan
cheap security checks

It must not run:

full test suite;
Docker build;
expensive integration tests;
mutation testing;
large dependency scans;
load tests.

The standard execution levels are `--quick`, `--full`, and `--ci`; `--ci` is the CI-equivalent mode.

## 10. cmd/dev/check --full

Complete local validation.

Typical:

format
lint
syntax
PHPStan
frontend analysis
tests
architecture tests
security
dependency audit
Docker checks
CI configuration validation

The exact set is profile-dependent.

This is the recommended command before opening a substantial PR.

Alongside `--quick` and `--full`, the third standard level `--ci` selects the CI-equivalent mode.

## 11. cmd/dev/test

Provides a unified test interface.

Examples:

./cmd/dev/test
./cmd/dev/test unit
./cmd/dev/test integration
./cmd/dev/test e2e
./cmd/dev/test --changed

The command resolves the project's test framework automatically.

Possible backends:

PHPUnit
Pest
Jest
Vitest
Playwright
other approved runners

The baseline does not require one framework globally.

## 12. cmd/dev/lint

Unified lint entrypoint.

Applicable tools are selected by profile:

PHP
├── PHP-CS-Fixer / Pint
├── PHPStan
└── custom architecture rules


JS/TS
├── ESLint
├── Prettier
└── Stylelint


Docker
└── Hadolint


GitHub Actions
└── actionlint


YAML
└── yamllint

lint should not duplicate orchestration logic already implemented by the baseline runner.

## 13. cmd/dev/security

Security-only local validation.

Typical controls:

secret scanning
Composer audit
npm/pnpm audit
SAST
security lint
Docker security checks where applicable
dependency policy
baseline security policy

Example:

Security check


✓ Secrets
✓ Composer dependencies
✓ JS dependencies
✓ PHP SAST
✓ JS SAST
✓ Docker policy


Security checks passed.

Security findings must never be silently converted to warnings.

## 14. Changed-Surface Detection

The tooling must understand which repository surfaces changed.

Examples:

*.php
→ PHP


composer.json
composer.lock
→ Composer/dependencies


resources/js/**
package.json
pnpm-lock.yaml
→ frontend


Dockerfile
docker/**
→ Docker


.github/**
→ GitHub Actions


database/**
→ database/integration tests


async/**
→ async runtime tests

This enables fast local execution.

The detection system must be conservative for security.

If applicability cannot be determined safely:

run the broader check.

Never skip a security check because detection is uncertain.

## 15. Dependency Graph

Checks should support dependencies between controls.

Example:

format
↓
lint
↓
static analysis
↓
unit tests
↓
integration tests

Security:

dependency manifest
↓
lockfile validation
↓
dependency audit

Docker:

Dockerfile
↓
Hadolint
↓
build
↓
Trivy
↓
SBOM

The runner should avoid repeating the same expensive preparation step.

## 16. Parallel Execution

Independent checks should execute in parallel where safe.

Example:

             ┌── PHPStan
             ├── secret scan
             ├── Composer audit
             ├── ESLint
             └── tests
                    │
                    ▼
                aggregation

The execution engine must respect declared dependencies.

Parallel execution must not introduce:

race conditions;
shared temporary-state corruption;
nondeterministic results.

## 17. Output

Default output should be concise.

Example:

DEV CHECK


✓ format
✓ syntax
✓ lint
✓ PHPStan
✓ tests
✓ secrets
✓ dependency security


PASSED — 7/7

On failure:

DEV CHECK


✓ format
✓ syntax
✗ PHPStan
✓ secrets
✓ dependency security


FAILED — 1 control failed


PHPStan:
src/User.php:42
Argument #1 must be string, int given.


Fix the code and rerun:


./cmd/dev/check

Verbose mode may expose tool output:

./cmd/dev/check --verbose

## 18. Machine-Readable Output

All commands should support machine-readable results where useful:

./cmd/dev/check --format=json
./cmd/dev/check --json

The canonical form is `--format=text|json|github`; `--json` is a documented alias for `--format=json`.

Supported output should eventually include:

json
text
github

JSON should contain:

command
baseline
profiles
controls
status
severity
duration
files
message
remediation

This is important for:

CI;
IDE integration;
AI agents;
dashboards;
automation.

## 19. Git Hooks

Hooks are stored in version control:

tools/git-hooks/
├── pre-commit
├── commit-msg
└── pre-push

They are installed automatically.

Git configuration should point to the repository-controlled hook location.

The hooks themselves should be thin wrappers around the common command system.

Example:

pre-commit
↓
./cmd/dev/check --quick

Do not duplicate validation logic inside hooks.

## 20. Pre-Commit

Pre-commit is optimized for developer velocity.

Default:

changed-file detection
↓
safe formatter
↓
syntax
↓
fast lint
↓
fast security
↓
secret scan

Target:

usually a few seconds.

The hook should avoid full-project operations.

If a required fast check cannot finish quickly enough, it belongs in pre-push or CI.

## 21. Commit Message Hook

commit-msg validates the project commit convention.

Supported prefixes:

feat
fix
refactor
perf
test
docs
build
ci
chore
security

Example:

security: harden dependency policy

This section is the single definition site of the commit convention; other documents must reference it instead of restating the prefixes.

The exact Conventional Commit grammar is configurable.

Commit-message validation must not be used as a substitute for code review.

## 22. Pre-Push

Pre-push performs checks that are too expensive for every commit but useful before sending work to CI.

Typical:

full relevant tests
PHPStan
dependency checks
security
frontend checks
architecture tests

It should still be optimized by changed surfaces.

## 23. Quick Commit

Standard command:

./cmd/git/quick-commit "fix: validate user input"

Workflow:

validate message
↓
inspect working tree
↓
detect changed files
↓
safe auto-fix
↓
quick checks
↓
secret scan
↓
security checks
↓
show final diff
↓
git add
↓
git commit

The command must not automatically commit unrelated existing changes.

## 24. Commit Safety

quick-commit must prevent accidental inclusion of unrelated modifications.

Before staging it should determine:

modified
added
deleted
untracked

If the requested scope is ambiguous, it should fail rather than silently stage everything.

A future explicit option may permit:

./cmd/git/quick-commit --all "..."

but this must be deliberate.

## 25. Forbidden Commit Bypasses

The standard tooling must never internally execute:

git commit --no-verify

It must not:

disable hooks;
modify Git configuration to bypass checks;
temporarily rename hooks;
suppress security findings;
modify policy to force success.

## 26. Explicit Escape Hatch

A technical bypass may exist for exceptional cases:

./cmd/git/commit --skip-checks

If supported, it must:

print a prominent warning;
require an explicit command-line flag;
record the bypass locally where practical;
never bypass CI;
never change repository policy;
never disable future checks.

Example:

WARNING


Local validation is being bypassed.


This does NOT bypass GitHub CI.
The commit may still be rejected by the remote gates.


Reason is required:

No silent bypass is allowed.

## 27. cmd/deps/*

Dependency commands provide a unified interface.

Audit
./cmd/deps/audit

Runs applicable:

Composer audit
npm/pnpm audit
dependency policy
lockfile validation
Outdated
./cmd/deps/outdated

Reports outdated dependencies without modifying them.

Update
./cmd/deps/update

Updates dependencies according to repository policy.

Updates must not automatically weaken security constraints.

Large or risky updates should be surfaced clearly.

## 28. cmd/docker/*
    Build
    ./cmd/docker/build

Provides the standard project Docker build.

Scan
./cmd/docker/scan

Runs applicable:

Dockerfile lint
image scan
dependency scan
secret checks
configuration checks
Shell
./cmd/docker/shell

Provides a standard development shell where applicable.

Production credentials must never be exposed merely because the developer invokes this command.

## 29. cmd/ci/*

These commands validate CI behavior locally where practical.

Validate
./cmd/ci/validate

Checks:

workflow syntax
actionlint
YAML
baseline integration
required configuration
Check
./cmd/ci/check

Runs the CI-equivalent local checks that are practical to execute locally.

It must clearly distinguish:

local-equivalent

from:

GitHub-only

## 30. Environment Isolation

The tooling should avoid depending on arbitrary globally installed versions.

Preferred order:

repository-pinned tool
↓
Composer/vendor
↓
package manager
↓
approved system tool

The doctor command reports where each tool comes from.

Example:

PHPStan:
vendor/bin/phpstan


ESLint:
node_modules/.bin/eslint


Trivy:
system installation

## 31. Tool Availability

If an optional tool is unavailable:

INFO: Docker profile inactive

is acceptable.

If a required tool is missing:

ERROR: required security tool unavailable

must fail the appropriate command.

Security-critical tools must never silently disappear from execution.

## 32. Auto-Installation

setup may install project-local dependencies.

It should not silently install arbitrary privileged system software.

System-level installation should either:

use an approved bootstrap mechanism;
provide an explicit installation command;
or report the missing dependency.

This prevents a malicious or compromised repository from turning:

./cmd/dev/setup

into arbitrary privileged system installation.

## 33. Network Access

Commands should declare whether network access is required.

Examples:

offline:
format
lint
syntax
local tests


network:
dependency audit
dependency update
some vulnerability databases
container pulls

Where possible:

./cmd/dev/check --offline

should execute all controls that do not require network access.

## 34. Caching

Caching is allowed only when it cannot compromise correctness.

Safe examples:

PHPStan cache
ESLint cache
dependency metadata
Docker build cache
test compilation

Security scans must respect cache invalidation.

A stale security result must never be presented as a fresh result.

## 35. Local Resource Limits

Heavy commands must respect developer machines.

The runner should support:

parallelism
memory limits where supported
timeouts
CPU limits where supported

Example:

./cmd/dev/check --jobs=8

Defaults should be automatically selected based on the environment.

The baseline must avoid turning a developer workstation into a resource-exhaustion victim.

## 36. Timeout Policy

Every external command should have a timeout.

Examples:

formatter
lint
test
security scanner
network operation
Docker operation

Timeout output must identify:

control
command
elapsed time
configured timeout

A timeout is a failure, not success.

## 37. Line-Ending Control

All repository text files MUST use LF (`\n`) line endings, never CRLF.

This is enforced via `.gitattributes` (`* text=auto eol=lf`).

`cmd/dev/check --quick` includes an LF check for changed files, and violations are auto-fixable by `cmd/dev/fix`.

Motivation: Windows/WSL cross-boundary incidents have corrupted files silently; a hard LF contract prevents recurrence.

## 38. Failure Recovery

The tooling should distinguish:

CHECK FAILED

from:

TOOL FAILED

Example:

PHPStan found an error

versus:

PHPStan could not start

The latter is infrastructure/tooling failure and should not be misreported as a code-quality finding.

## 39. AI-Agent Design

All developer commands must be usable non-interactively.

AI agents should be able to execute:

./cmd/dev/doctor
./cmd/dev/fix
./cmd/dev/check
./cmd/dev/security
./cmd/git/quick-commit "..."

Commands should:

return reliable exit codes;
avoid decorative-only output;
provide actionable errors;
support JSON;
avoid asking unnecessary questions;
never automatically weaken controls.

## 40. AI Failure Rules

If a check fails, an agent must:

inspect failure
↓
modify implementation
↓
rerun check

It must not:

add ignore
disable rule
lower threshold
remove test
skip hook
use --no-verify
modify CI

unless the change itself is explicitly required and approved as a policy change.

## 41. README Integration

Every repository generated from the baseline must contain a concise command section.

Required:

## Development commands


### Setup


./cmd/dev/setup


### Check


./cmd/dev/check


### Fix


./cmd/dev/fix


### Security


./cmd/dev/security


### Quick commit


./cmd/git/quick-commit "fix: description"


### Full validation


./cmd/dev/check --full


### Diagnostics


./cmd/dev/doctor

The README must describe the commands, not every underlying tool.

## 42. Command Discovery

A developer should be able to discover available commands with:

./cmd

or:

./cmd/help

if implemented.

Output:

Development
setup
doctor
check
fix
test
lint
security


Git
commit
quick-commit
prepush
branch


Dependencies
audit
outdated
update


Docker
build
scan
shell


CI
check
validate

## 43. Command Compatibility

Commands are part of the developer-facing API.

Breaking changes require:

baseline version change where appropriate;
migration notes;
updated README;
updated AGENTS.md;
CI validation.

Internal tools can be replaced without requiring developers to learn new commands.

## 44. Security Boundary

Developer tooling is not the final security boundary.

The security architecture is:

developer tooling
↓
fast feedback
↓
Git hooks
↓
CI authoritative gate
↓
release gate
↓
artifact verification
↓
runtime controls

Every layer adds protection.

No single local command is trusted as the sole enforcement mechanism.

## 45. Performance Requirements

The tooling itself must have performance budgets.

Target:

cmd/dev/check --quick
→ seconds


pre-commit
→ seconds


pre-push
→ tens of seconds where practical


full local check
→ acceptable for pre-PR execution


CI
→ parallelized

The exact budgets should be measured and tuned after implementation.

The tooling must not become so slow that developers habitually bypass it.

## 46. Acceptance Criteria

The developer tooling is complete when:

setup can bootstrap a supported repository;
doctor reliably diagnoses the environment;
check automatically detects applicable checks;
check --quick is fast enough for interactive use;
check --full provides a complete local gate;
fix performs only approved safe fixes;
security executes applicable security controls;
Git hooks are version-controlled;
hooks use the common runner;
quick-commit cannot accidentally commit unrelated work;
quick-commit cannot silently bypass checks;
commands work non-interactively;
commands provide stable exit codes;
commands support machine-readable output;
missing required tools fail clearly;
CI can reuse the same logical controls;
AI agents can operate the entire workflow without special undocumented commands.

## 47. Architectural Invariants
    DEV-01 — One orchestration engine

Commands must not contain duplicated control logic.

DEV-02 — Hooks are thin

Git hooks call the common command layer.

DEV-03 — Security cannot be silently bypassed

No command may disable mandatory security controls.

DEV-04 — CI remains authoritative

Passing local checks never guarantees merge.

DEV-05 — Safe fixes only

Automatic fixing must be explicitly classified as safe.

DEV-06 — Changed-surface optimization

Unrelated expensive checks should not run locally.

DEV-07 — Conservative security detection

Uncertainty must result in broader checking, not skipping.

DEV-08 — Deterministic results

The same repository state and baseline should produce equivalent results.

DEV-09 — Stable developer API

cmd/* commands are stable interfaces.

DEV-10 — AI-compatible

All standard commands must work non-interactively and expose reliable machine-readable results.

## 48. Final Developer Workflow

Normal development:

./cmd/dev/setup
↓
edit
↓
./cmd/dev/fix
↓
./cmd/dev/check --quick
↓
./cmd/git/quick-commit "..."
↓
push
↓
./cmd/dev/check --full    ← before PR when needed
↓
PR
↓
GitHub CI

For a simple change, the developer should effectively need only:

./cmd/dev/check
./cmd/git/quick-commit "fix: ..."

For a substantial change:

./cmd/dev/fix
./cmd/dev/check --full
./cmd/git/quick-commit "feat: ..."

The architectural objective is therefore:

The developer interacts with a small stable command surface; the baseline automatically determines what needs to happen underneath.
