03 — Security & Supply Chain

Status: Draft
Version: 0.2
Owner: platform-team
Updated: 2026-08-21
Depends on: 01-architecture-and-baseline.md, 02-developer-tooling.md
Implementation: 11-implementation-and-rollout.md

## 1. Purpose

This component must provide:

maximally automated protection of code, dependencies, CI/CD and artifacts with minimal developer attention.

The core model:

source
↓
secrets
↓
dependencies
↓
SAST
↓
configuration
↓
Docker
↓
SBOM
↓
provenance
↓
signing
↓
immutable artifact
↓
verified release

Security controls must be built into the lifecycle, not exist as a separate manual process.

## 2. Security Principles

**Secure by default** — A new repository receives security controls automatically.

**Defense in depth** — A single scanner is never considered sufficient.

For example:

developer hook
+
local secret scan
+
GitHub secret scanning
+
push protection
+
CI scan

**CI is authoritative** — A local bypass must not allow an unsafe change to reach a protected branch.

**Security tools are replaceable** — Policy defines the requirement:

"secrets must be detected"

not:

"TruffleHog must always be used"

Tools may be replaced without changing the architecture.

**Fail closed for critical controls** — If a mandatory security control cannot be reliably executed, the corresponding authoritative gate must end in failure.

Forbidden:

scanner unavailable → skip → success

Acceptable:

scanner unavailable → infrastructure failure → CI failed

## 3. Security Control Model

Each security control has:

id
category
severity
applicability
runner
gate
policy
failure mode
exception support

For example:

id: secrets.repository
category: secrets
severity: critical
required: true
runner: secret-scanner

Results are normalized independently of the specific tool.

## 4. Security Categories

Minimal set:

Secrets
Dependencies / SCA
SAST
Security configuration
Docker / container
Supply chain
CI/CD
Artifacts
Runtime configuration
Repository security

## 5. Secrets

Secret protection must operate at multiple levels.

Developer
↓
Git hook
↓
CI
↓
GitHub Secret Scanning
↓
Push Protection
↓
Artifact scan

## 6. Secret Scanner

Local and CI secret scanning must use an approved scanner, for example:

TruffleHog

But the architectural contract must be named:

secret scanner

not TruffleHog.

The scanner must look for:

API keys;
access tokens;
passwords;
private keys;
cloud credentials;
database credentials;
Telegram bot tokens (including tokens persisted in the platform database — see 10-telegram-platform-and-libraries.md);
JWT secrets;
webhook secrets;
signing keys;
service credentials.

## 7. Git History

The check must cover not only the current tree.

For CI security scan:

HEAD
+
relevant history

For a repository security audit:

full Git history

The goal:

detect a secret that has already been committed and then removed.

Removing a secret from the current file does not count as remediation.

## 8. Secret Remediation

When a secret is detected, the system must report:

secret detected
repository
file
commit
line/range where possible
secret type

And remediation:

1. revoke credential
2. rotate credential
3. remove from repository/history if necessary
4. verify no remaining copies

The system must not attempt to automatically publish or send the detected secret.

## 9. False Positives

False positives must be resolved through a controlled allowlist.

Forbidden:

ignore-all

Allowed:

allow:
rule: known-test-token
path: tests/fixtures/**
reason: synthetic credential
expires: ...

Where possible, the allowlist should be as narrow as possible:

path
rule
fingerprint
line/range

## 10. Generated Artifacts

Secret scanning must check:

source
configuration examples
generated files where committed
frontend bundles
Docker context
release artifacts where applicable

Especially frontend:

src/
↓
build
↓
dist/
↓
secret scan

Because a secret can end up in the production bundle even if the source file is already safe.

## 11. .gitignore Is Not Security

.gitignore reduces the probability of an accidental commit but is not a security control.

Therefore:

.gitignore
+
secret scanning
+
push protection

are each mandatory independently of one another.

## 12. GitHub Secret Security

For platform repositories, the following must be used:

GitHub Secret Scanning;
Push Protection;
repository/org-level secret policies where available.

GitHub controls are an additional enforcement layer and do not replace the local scanner.

## 13. PHP Dependency Security

For Composer:

composer.json
composer.lock

are checked for:

lock consistency;
vulnerable dependencies;
abandoned/risky packages where detectable;
suspicious dependency changes;
plugin policy;
version constraints;
transitive dependency impact.

The primary audit:

composer audit

## 14. Composer Lockfile

Production repositories must use a lockfile where the repository type requires reproducible application dependencies.

For libraries:

composer.lock

may have project-specific policy depending on package conventions, but dependency resolution must remain reproducible in CI.

Lockfile changes are security-sensitive.

PRs changing:

composer.json
composer.lock

must automatically trigger dependency/security checks.

## 15. Composer Plugin Policy

Composer plugins can execute arbitrary PHP code during package installation.

Therefore plugin installation must be explicitly controlled.

Use Composer's plugin allowlist mechanism.

Unknown plugin:

FAIL

unless explicitly approved.

The baseline should detect unexpected additions to the plugin allowlist.

## 16. Composer Lifecycle Scripts

Package lifecycle scripts can execute code during installation/update.

The baseline must:

identify lifecycle scripts;
report newly introduced scripts;
apply repository policy;
avoid silently allowing dangerous behavior.

For high-risk dependency changes:

dependency change
↓
policy evaluation
↓
security review if required

## 17. JavaScript Dependencies

For npm/pnpm:

package.json
lockfile

must be validated.

Checks include:

vulnerability audit;
lockfile integrity;
unexpected dependency changes;
lifecycle scripts;
dependency graph;
package provenance where available.

Commands may include:

npm audit

or:

pnpm audit

depending on the repository.

## 18. Package Manager Lockfile Policy

Repositories must declare one authoritative package manager.

Example:

pnpm

Then:

pnpm-lock.yaml

is authoritative.

Unexpected competing lockfiles:

package-lock.json
yarn.lock
pnpm-lock.yaml

should fail or warn according to policy.

This prevents different environments from resolving different dependency trees.

## 19. Dependency Change Detection

A dependency PR should automatically identify:

direct dependencies added
direct dependencies removed
versions changed
transitive dependencies changed
scripts changed
license changes
security advisories

The PR should provide a clear summary.

Example:

Dependency impact


+ package-x 2.1 → 3.0
+ 14 transitive packages
+ install script introduced
+ 0 known critical vulnerabilities

## 20. Dependency Review

GitHub Dependency Review should be enabled for applicable repositories.

It should detect:

vulnerable additions;
vulnerable upgrades;
dependency risk;
license policy violations where configured.

Dependency Review complements package-manager audits.

## 21. Dependabot / Renovate

Dependency updates should be automated.

Preferred workflow:

new dependency release
↓
automated PR
↓
CI
↓
security scan
↓
tests
↓
merge

Automation should group low-risk updates where practical.

Security updates should receive higher priority.

## 22. Dependency Update Policy

Automated updates must never:

bypass CI;
disable security checks;
modify security policy;
introduce unreviewed package-manager changes.

Major updates may require explicit review.

## 23. SAST

SAST is required for supported source surfaces.

Primary implementation may use:

Semgrep

with:

p/php
p/security

and platform-specific rules.

Again, the architectural control is:

SAST

not:

Semgrep

## 24. Custom SAST Rules

The platform should maintain custom rules for recurring platform-specific risks.

Examples:

unsafe command execution
unsafe deserialization
dynamic SQL
unsafe filesystem access
dangerous process execution
unvalidated redirects
unsafe Telegram API usage
secret exposure
unsafe async lifecycle handling

Rules should be versioned with the baseline.

## 25. PHP Static Analysis

PHPStan is a quality and security-adjacent control.

It should detect:

invalid types;
impossible states;
incorrect API usage;
nullability errors;
unreachable code;
contract violations.

The platform should establish a minimum level per repository class.

Existing technical debt should not be hidden by globally lowering the level.

Migration may use controlled baselines where necessary, but new violations must not be silently accepted.

## 26. Laravel Security

Laravel repositories additionally use Laravel-specific security controls.

Where applicable:

Laravel Enlightn
Laravel-specific static/security rules
framework configuration validation

Generic PHP repositories must not require Laravel security tooling.

## 27. Security-Sensitive PHP APIs

The baseline should identify risky APIs and patterns, including where applicable:

eval
exec
system
shell_exec
passthru
proc_open
popen
unserialize
dynamic includes
unsafe filesystem operations
unsafe command construction

The objective is not to ban every API blindly.

Policy should distinguish:

FORBIDDEN
REQUIRES REVIEW
ALLOWED

For platform internals, some low-level APIs may be legitimate.

Example:

Async Kernel
↓
proc_open

may be required.

Such use must be explicitly covered by the appropriate profile and tests rather than globally suppressed.

## 28. Security Configuration

Security checks should also cover configuration.

Examples:

debug mode
production environment
unsafe CORS
weak cookies
missing secure flags
unsafe headers
insecure TLS configuration
exposed management endpoints

Framework-specific configuration checks belong in profiles.

## 29. Docker Security

Docker security has four stages:

Dockerfile
↓
build context
↓
image
↓
artifact

Each stage has controls.

## 30. Dockerfile

Use:

Hadolint

and custom Docker security policy.

Check for:

dangerous commands;
unnecessary privileges;
unpinned dependencies;
secrets in layers;
root execution;
unnecessary packages;
unsafe shell usage;
excessive image size;
missing health configuration where required.

## 31. Docker Build Context

.dockerignore is mandatory where Docker is used.

The baseline should verify that sensitive files are not accidentally sent to Docker build context.

Examples:

.env
.git
private keys
local credentials
development secrets

The scanner should detect obvious violations.

## 32. Docker Secrets

Never embed secrets in:

ENV
ARG
COPY
RUN

where they can become part of the image or build metadata.

Build-time secrets must use approved secret mechanisms.

Runtime secrets should be injected at runtime.

## 33. Base Images

Production base images should be pinned.

Preferred:

image@sha256:<digest>

rather than:

php:8.5

Mutable tags may be acceptable for explicitly defined development environments, but release builds should resolve to immutable identities.

## 34. Image Scanning

After build:

docker build
↓
Trivy

Scan:

OS packages;
language dependencies where supported;
configuration;
secrets;
known vulnerabilities.

Critical/high vulnerabilities should follow the centralized severity policy.

## 35. SBOM

Every production artifact should have an SBOM.

The SBOM should describe:

OS packages
PHP packages
JS packages
application dependencies
image layers where applicable

Preferred standardized formats:

CycloneDX
SPDX

The exact format is platform policy.

## 36. Provenance

Builds must produce provenance describing:

source repository
commit
workflow
builder
baseline
build inputs
artifact identity

The goal is to answer:

Where did this artifact come from?

## 37. Artifact Signing

Production artifacts must be signed.

For container images:

build
↓
scan
↓
SBOM
↓
provenance
↓
sign

Consumers/deployment systems should verify the signature before deployment where technically supported.

## 38. Immutable Artifacts

Production deployment must reference immutable artifacts.

Allowed:

app@sha256:<digest>

or an immutable version tied to a verified digest.

Forbidden as a production identity:

latest

Mutable aliases may exist for developer convenience but must not be used by release automation.

## 39. CI Security

GitHub Actions are part of the supply chain.

Every workflow must follow:

minimum permissions
+
trusted actions
+
pinned references
+
safe secrets handling
+
protected workflow changes

## 40. GitHub Actions Pinning

Third-party Actions should be pinned to immutable commit SHAs.

Bad:

uses: vendor/action@v4

Preferred:

uses: vendor/action@<commit-sha>

Version comments may document the human-readable release.

## 41. Workflow Permissions

Default workflow permissions should be minimal.

Avoid:

permissions: write-all

Use only required permissions.

Example:

permissions:
contents: read

Additional permissions must be explicitly justified.

## 42. Secrets in Untrusted Workflows

Secrets must not be exposed to untrusted PR code.

Particular care is required with:

pull_request_target

and workflows that checkout attacker-controlled code while possessing write credentials.

The baseline must detect dangerous patterns where practical.

## 43. OIDC

Long-lived cloud credentials should be avoided.

Where supported:

GitHub OIDC

should be preferred.

Credentials should be:

short-lived;
scoped;
audience restricted;
environment restricted.

## 44. Repository Security

Required repository protections include:

CODEOWNERS
protected branches/rulesets
PR review
required CI
secret scanning
push protection
dependency automation

Security-sensitive files must receive ownership protection.

Examples:

.github/workflows/
security/
Dockerfile
docker/
composer.json
composer.lock
package.json
lockfiles
AGENTS.md
SECURITY.md
baseline configuration

## 45. Security-Sensitive Changes

The following changes should automatically receive elevated security classification:

authentication
authorization
secrets
crypto
dependency changes
Docker
CI workflows
deployment
network boundaries
database access
command execution
filesystem permissions
Telegram bot credentials
async process lifecycle

Such PRs should require appropriate review according to repository policy.

## 46. AI-Generated Code

AI-assisted development is allowed.

AI-generated code is subject to exactly the same security gates as human-written code.

No special trust is granted to:

AI
LLM
agent
generated patch

PR metadata may record AI assistance for transparency, but:

AI origin never reduces security requirements.

## 47. Security Review Triggers

The system should automatically flag PRs that modify:

.github/**
security/**
Dockerfile
docker/**
composer.json
composer.lock
package.json
lockfiles
authentication
authorization
crypto
deployment
secrets

for elevated review.

## 48. Security Exceptions

Exceptions are controlled by the baseline architecture.

Each exception requires:

control
reason
scope
owner
created_at
expires_at

Example:

exception:
control: sast.php.command-execution
scope: src/ProcessRunner.php
reason: "Required by Async Kernel process supervisor"
owner: platform-team
expires_at: 2026-12-01

The exception must not disable unrelated rules.

## 49. Exception Expiration

Expired exceptions must fail CI.

Example:

SECURITY FAILURE


Expired exception:
sast.php.command-execution


Expired:
2026-08-01


Action:
remove the exception or renew it with explicit approval.

This prevents temporary workarounds from becoming permanent vulnerabilities.

## 50. Severity Policy

Default policy:

critical → block
high     → block
medium   → policy-dependent
low      → report
info     → report

Exceptions require explicit policy.

A tool's native severity must be normalized into the baseline severity model.

## 51. Security Gate Modes

Security controls may operate in:

observe
warn
enforce

This is important for rollout of existing repositories.

Observe

Collect findings without blocking.

Warn

Visible failure/warning but optionally non-blocking.

Enforce

Failure blocks the gate.

New repositories should normally start in enforce.

Legacy repositories may use controlled migration.

## 52. Baseline Migration

Migration should follow:

install
↓
observe
↓
classify existing findings
↓
fix / approved exceptions
↓
warn
↓
enforce

Existing vulnerabilities must not be hidden by creating permanent exceptions.

## 53. Security Reporting

Every security run should produce:

baseline
profiles
controls
findings
severity
exceptions
duration
tool versions

CI should retain machine-readable security results where appropriate.

## 54. Tool Versioning

Security tools must be versioned or otherwise reproducibly resolved.

The system should record:

tool
version
baseline
configuration
ruleset version

A security result without knowing which scanner/ruleset generated it is insufficient for reliable auditability.

## 55. Security Cache Policy

Caching vulnerability databases is permitted.

However:

stale security data

must be detectable.

CI should refresh security intelligence according to an explicit policy.

A cached result must never falsely imply:

"This was checked against today's vulnerability database."

if it was not.

## 56. Security Supply Chain

The security system itself is part of the supply chain.

Therefore:

scanner
rules
baseline
workflow
action
container

must also be protected.

A compromised security scanner could otherwise become a mechanism for hiding vulnerabilities.

## 57. Security Tool Isolation

Where practical, complex security tools should run in controlled environments:

container
pinned binary
Composer dependency
Node dependency

The method must be reproducible.

Untrusted source code must not automatically receive unnecessary host privileges during security scanning.

## 58. No Security-by-Ignore

Forbidden patterns:

disable rule
ignore directory globally
lower severity globally
skip scanner

without explicit policy.

A false positive must be handled narrowly.

Bad:

exclude src/**

Good:

exclude exact rule + exact path + reason + expiry

## 59. Platform-Specific Security

The Telegram platform requires additional controls.

Examples:

Telegram bot tokens
webhook secrets
Telegram API credentials
rate limits
user input
callback data
file downloads
external URLs
message parsing
command execution
queue payloads

Platform-specific controls (bot tokens stored in DB `tg_bots`, webhook secrets, endpoint hardening, token-leak prevention) are specified in `10-telegram-platform-and-libraries.md`; this document defines only the generic controls.

## 60. Async Kernel Security

The Async Kernel profile must consider:

process spawning
Fiber lifecycle
resource cleanup
timeouts
cancellation
FD exhaustion
memory exhaustion
worker isolation
IPC
socket handling
DNS
TLS

Security rules must distinguish legitimate runtime primitives from application misuse.

## 61. Async TG Security

The async Telegram library additionally requires:

TLS verification
certificate handling
URL validation
Telegram endpoint validation
redirect policy
request limits
response size limits
timeouts
retry safety
credential isolation

Transport optimizations must never disable TLS verification or security controls merely for performance.

## 62. Security and Performance

Security controls must not create unacceptable runtime overhead.

However:

performance is not a justification for removing a mandatory security control.

Optimization should instead use:

caching;
parallel scans;
changed-surface detection;
CI parallelism;
incremental analysis.

## 63. Security and Developer Experience

The ideal path is:

developer writes code
↓
quick local feedback
↓
automatic safe fixes
↓
security scan
↓
commit
↓
CI

The developer should rarely need to manually invoke individual security tools.

## 64. Acceptance Criteria

Security & Supply Chain is complete when:

secrets are scanned locally and in CI;
Git history can be audited;
GitHub Secret Scanning/Push Protection are enabled where supported;
Composer dependencies are audited;
JS dependencies are audited;
dependency changes are automatically detected;
Composer plugins are controlled;
lifecycle scripts are governed;
SAST is enabled;
PHP/Laravel security controls are profile-based;
Dockerfiles are scanned;
Docker images are scanned;
build context is protected;
production images use immutable identity;
SBOM is generated;
provenance is generated;
production artifacts are signed;
CI workflows are hardened;
third-party Actions are pinned;
GitHub permissions are minimal;
OIDC is used instead of long-lived cloud credentials where applicable;
security-sensitive files are protected by CODEOWNERS;
exceptions are explicit and expire;
severity is normalized;
CI is authoritative;
security tooling itself is reproducible;
findings are actionable;
no security control can be silently disabled.

## 65. Architectural Invariants

### SEC-01 — No secrets in source

Secrets must never be intentionally committed.

### SEC-02 — .gitignore is not a security boundary

Scanning remains mandatory.

### SEC-03 — No silent scanner failure

Unavailable mandatory security controls fail the authoritative gate.

### SEC-04 — CI enforcement

Local bypass cannot bypass protected CI.

### SEC-05 — Immutable production identity

Production artifacts must be addressable immutably.

### SEC-06 — Explicit exceptions

Every exception has scope, reason, owner and expiry.

### SEC-07 — Narrow suppression

Security findings may not be suppressed globally to solve local problems.

### SEC-08 — Dependencies are code

Dependencies receive security treatment equivalent to source changes.

### SEC-09 — CI is production code

GitHub workflows are security-sensitive source.

### SEC-10 — Security tooling is supply chain

Scanners, rules and workflows themselves must be trusted and versioned.

### SEC-11 — Framework-specific security is profile-based

Generic PHP baseline must remain framework-agnostic.

### SEC-12 — Security controls are policy-driven

Tools implement controls; they do not define policy.

## 66. Final Security Architecture

    SOURCE
    │
    ┌────────────────┼────────────────┐
    ▼                ▼                ▼
    SECRETS         DEPENDENCIES        SAST
    │                │                │
    └────────────────┼────────────────┘
    ▼
    CONFIGURATION
    │
    ▼
    DOCKER
    │
    ▼
    BUILD
    │
    ┌────────┴────────┐
    ▼                 ▼
    SBOM          PROVENANCE
    │                 │
    └────────┬────────┘
    ▼
    SIGNING
    │
    ▼
    IMMUTABLE ARTIFACT
    │
    ▼
    RELEASE GATE
    │
    ▼
    DEPLOYMENT
    │
    ▼
    ARTIFACT VERIFY

The main principle of this component:

Security controls must be automatic, multi-layered and policy-driven. The developer should not have to remember which checks are needed; the baseline must define and run them itself.

And a second one:

Inability to execute a mandatory security check is a failure, not permission to skip it.
