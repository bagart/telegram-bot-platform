---
paths:
  - 'misc/BAGArt/**/src/**'
---

# Src

## Never write CI/nightly stub classes into the real source tree
Incident 2026-09-30 12:03: nightly-stub content (7 minimal one-liner Settings DTOs) was written into the real `telegram-platform-module/src/Settings/`, wiping `SettingsField::validate()` and `SettingsDescriptor::field()` (uncommitted work) — full gate caught it only via pint + 11 test failures. Write stubs only into a `git worktree` copy or inline in workflow YAML. Also: `pint --dirty` skips untracked and nested-repo files — rely on the full `composer test` lint gate (pint --parallel --test) before delivery.
