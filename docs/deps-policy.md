# Dependency Update Policy (03-security-and-supply-chain.md §22)

Cadence and ownership rules for composer/npm/base-image updates. Automation:
Dependabot (`.github/dependabot.yml`) + Dependency Review gate on PRs.

## Cadence

| Channel | Cadence | Owner | Gate |
|---|---|---|---|
| Security patches (composer audit / npm audit findings) | immediately, same day | platform-team | full CI; may bypass feature-freeze |
| Regular composer/npm updates | weekly batch (Monday) | platform-team | full CI + smoke (`cmd/dev/check --quick`) |
| BAGArt library bumps | with each tagged lib release (`cmd/release/lib`) | lib owner | app CI + cross-lib contract suite |
| PHP / Node major | quarterly evaluation window | platform-team | extended matrix (05 §69) |
| Docker base images | monthly; digest-pinned | platform-team | docker workflow Trivy gate |
| `bagart/telegram-devops-baseline` engine updates | with each tagged release; consumers bump via `composer update` + SHA re-pin of workflow callers | baseline owner | package selftest (`vendor/bin/baseline-selftest`) + consumer golden-run diff |
| `BAGArt/telegram-platform-workflows` bumps | one-line SHA-bump PR per consumer, scriptable | repo owners | central repo's own lint/pinning CI |

## Rules

1. **No direct edits to lockfiles by hand** — updates go through
   `cmd/deps/update` (runs composer/npm update) so plugin/lifecycle policy
   checks execute (`tools/baseline/composer-policy.php`).
2. Every dependabot PR passes the standard pipeline (03 §68): security,
   tests, build — no merge on red.
3. Major-version jumps of runtime or core libs get a dedicated PR with
   release notes summary and rollback note (previous artifact stays
   promotable, 11 §58).
4. Audit debt is not allowed to accumulate: `composer audit --no-dev` must be
   clean on develop; deviations need an allowlist entry with reason+expiry
   (same discipline as secret exceptions).
