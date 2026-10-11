# SDD: Mutation Gate Enforcement + Nightly CI Repair

> Status: DONE for gate enforcement; nightly vendor redesign in progress (steps tracked in task file)
> Date: 2026-09-29 (gate) / 2026-10-01 (CI strategy decisions)
> Task: docs/tasks/wave-c-mutation-gate.md

## What Was Built

The mutation gate is alive again: `cmd/dev/mutate` invokes the package control directly with an explicit `--baseline` and a numeric floor guard, the async-kernel MSI floor is enforced at 97 (measured 100.00%), and nightly CI's `cmd/dev/mutate` step now prints `enforced floor: 97` and can evaluate it. `core.hooksPath` points at the real package hooks directory, four zero-caller dead shims are deleted, and the shared nightly workflow carried class stubs (temporary — superseded by the vendor-install redesign below).

## Procedure (Q7 C amended)

1. Fix lookup: `cmd/dev/mutate:37` → `vendor/bagart/telegram-platform-devops-baseline/controls/mutation-gate.php --baseline="$REPO_ROOT/tools/baseline/mutation-baseline.json"`; assert non-empty numeric floor (`^[0-9]+(\.[0-9]+)?$`), otherwise fail loud.
2. Measure once locally: `XDEBUG_MODE=coverage vendor/bin/pest --testsuite AsyncKernelLib --mutate --everything --covered-only` (parallel only).
3. Set `msi_floor = floor(score − 3.0)` for `bagart/async-kernel` only (`mutation-baseline.json`); other 11 stay 0; regenerate `tools/baseline/MANIFEST.json`.
4. Nightly runs with the enforced floor; after 3 green runs (manual checkpoint) → drop `continue-on-error`.

## Files

- `cmd/dev/mutate` — direct package control invocation, `--baseline`, numeric guard, `--parallel --processes=${MUTATE_PROCESSES:-4}` (24 workers OOM-kill a 23GB box).
- `tools/baseline/mutation-baseline.json` — `bagart/async-kernel.msi_floor = 97.0` (measured 100.00%: 2953 mutations / 52 files / 254 tests, 2026-09-29).
- `tests/Unit/Baseline/MutationGateTest.php` — subprocess CLI test: floor output non-empty numeric, bad floor → non-zero exit.
- `composer.prod.json` (Q16-A) — mirrored `require-dev` (16) + `autoload-dev` (19 psr-4) + `devops-baseline` VCS repo (18 repos); `composer.prod.lock` refreshed WITH dev packages (both lock-fresh green).
- `cmd/deps/install` — `--dev` (prod-only, default `--no-dev` for servers), `--ignore-platform-req=*` passthrough, guard rejecting `--dev` outside prod mode.
- `cmd/ci/rewrite-vendor-paths.php` (Q12-A) — CI-only rewrite of `misc/BAGArt/<dir>` → `vendor/<pkg>` in 7 files (`phpunit.xml`, `tests/Pest.php`, `composer.prod.json`, `config/tg_modules.php`, `config/inertia.php`, `app/Console/Commands/TgSpawnDaemonCommand.php`, `package.json`); 18-entry map with 3 naming exceptions (kernel-lib→async-kernel, client→ask-client, client-redis→ask-client-redis); longest-prefix-first replace; optional AskQueueLib strip (Q17-B, "nothing to strip" pre-batch); residual-check fails on unmapped refs (only `tgbot-module-example` comment allowed); verifies every target package exists in vendor; ends with `COMPOSER=composer.prod.json composer dump-autoload --optimize`; refuses to run when `vendor/bagart/async-kernel` is a symlink or `misc/BAGArt/telegram-bot-lib/src` exists.
- `misc/BAGArt/telegram-platform-workflows/.github/workflows/nightly.yml` — stub blocks deleted (×3), installs → `bash cmd/deps/install --mode=prod --dev --ignore-platform-req=ext-xhprof`, rewrite step ×3, job env `APP_KEY` (fresh `package:discover` boots the app before `.env` exists; proxy `ConfigKekProvider` needs material → `fallback_to_app_key`) and `TG_MODULE_ENABLED_example=false` (Example class absent from prod autoload), `php artisan key:generate` dropped (its replace pattern only matches the key as present in the `.env` file — always errors when APP_KEY comes from process env).
- `git config core.hooksPath` → `vendor/bagart/telegram-platform-devops-baseline/hooks`.
- Deleted: `tools/baseline/{changed-surface.php,cycle-smoke.sh,engine-smoke.sh,semgrep-scan.sh}` (zero callers, manifest regenerated: 118 files).

## Architecture Decisions

- **Host-side fix, not package (Phase 0 freeze)** — the root-cause `realpath($argv[0])` guard lives in frozen `controls/mutation-gate.php`; explicit `--baseline` bypasses the shim path entirely, package fix deferred to post-rollout.
- **Nightly-only, not in `bin/baseline-check`** (Q7) — local check time budget; enforcement happens where mutation already runs.
- **Floor = measured − 3.0** — tolerance for coverage drift; `covered_code_msi_floor` stays 0 (no consumer).

## Nightly CI strategy — decisions (2026-10-01, consolidated in `docs/adr/ADR-001-execution-posture-and-set-closure.md` + `docs/adr/ADR-002-prod-lock-nightly-and-repo-state.md`)

- **Q10 (`nightly-ci-nested-repos`) → vendor-install.** `misc/` is dev-only; CI does NOT clone nested repos (cloning rejected). Fresh install from `composer.prod.json` (public VCS repos) puts every package — including `devops-baseline` — into `vendor/bagart/*`, giving real testsuites and controls. The temporary Settings/TgModuleConfig stub blocks get deleted.
- **Q12 (`nightly-vendor-test-execution`) → option A: ephemeral path rewrite.** In the CI checkout only: `misc/BAGArt/<dir>` → `vendor/<package>` in `phpunit.xml`, `tests/Pest.php`, and the active manifest's `autoload-dev`, then `COMPOSER=composer.prod.json composer dump-autoload`. Dev mode untouched (its `vendor/bagart/*` are symlinks into misc — rewriting would double-run). Dir→package table with the three naming exceptions (`php-async-kernel-lib`→`async-kernel`, `php-async-kernel-client`→`ask-client`, `php-async-kernel-client-redis`→`ask-client-redis`); fail loud on unmapped phpunit references.
- **Q13 (`nightly-private-proxy-access`) → option A: public.** `bagart/tgbot-module-proxy` made PUBLIC after a clean history/tree secret audit (3 enum-name false positives).
- **Q14 (`nightly-prod-lock-vs-tip`) → option A: pinned lock.** `composer.prod.lock` refreshed with dev packages; both `lock-fresh:dev` and `lock-fresh:prod` green. Servers and CI install the pinned lock; bumps only via `cmd/deps/update --mode=prod`.
- **Q16 (`prod-manifest-dev-tooling`) → option A: full manifest.** `composer.prod.json` mirrors dev `require-dev` (16 entries) + `autoload-dev` (19 psr-4) + the `devops-baseline` VCS repo (18 repos total); `cmd/deps/install --mode=prod` passes `--no-dev` by default, `--dev` (valid only with prod mode) installs dev tooling for CI.
- **Q15 (`nested-repos-git-sync`) → C now, A later.** Agent pushed only the three rename-blocking commits (antispam/module/access); a full per-repo inventory with user batch review follows — prerequisite for step 5.
- **Q17 (`ask-queue-versioning`) → OPEN.** `misc/BAGArt/ask-queue` is unversioned (no `.git`, host-gitignored) and absent from every origin; the rewrite script must strip the `AskQueueLib` testsuite/`BAGArt\AskQueue\Tests\` autoload entry in CI until it is published.

## Tests

- `tests/Unit/Baseline/MutationGateTest.php` — 5 passed (incl. subprocess CLI floor test).
- Baseline suite: 89 passed after shim deletion; manifest `--verify` in sync.
- Mutation run: Score 100.00% ≥ 97 floor, gate exits 0 with `enforced floor: 97`.
- `cmd/deps/check`: all controls green (both `lock-fresh:dev`/`:prod` after Q13/Q14).
- **Vendor-install e2e (worktree sim 2026-10-01):** `cmd/deps/install --mode=prod --dev` EXIT=0 → rewrite EXIT=0 (`package:discover` DONE, 12371 autoload classes) → `AsyncKernelLib` **242 passed** from `vendor/bagart/*`; Unit non-baseline 64 passed. Worktree-only artifact: 25 `Unit\Baseline` failures (engine root-lookup wants a `.git` directory; worktrees have a `.git` file — real CI checkouts pass).
- **Q15-A batch findings (why Feature/ModuleEngine are red pre-batch):**
  - `telegram-platform-audit` +6 unpushed → `BAGArt\TelegramBotAudit\Laravel\Middleware\CorrelationMiddleware` missing in vendor (46 `Target class does not exist` failures).
  - `telegram-platform-module` origin own-suite red: `ResolvedSetting` readonly double-assignment (fix dirty, uncommitted) + `DatabaseSettingsStorage` `module_settings` array binding (308-line dirty rewrite).
  - ahead still: audit +6, menu +3, bot-lib +1; behind: kernel-lib −1, kernel-client −1; all 19 dirty; workflows repo dirty with these nightly.yml edits (SHA-pinned caller must be bumped).

## Known Limitations (frozen package side — Q8, record only)

- **Silent no-op controls:** `controls/{baseline-config,mutation-gate,sec-invariants,telemetry}.php` `realpath($argv[0])` guard returns empty output exit 0 when invoked through a shim — the original dead-gate root cause. Host works around it (direct call + `--baseline`); package fix lands post-freeze.
- **Unguarded constants:** controls define `EXIT_OK` etc. without `defined()` guards → ErrorException under Laravel's paratest error handler. Acceptable standalone, hostile in-host.
- **`sec-invariants` blind to `uses:` reusable workflows** — direct run scores 9/12 and masks `cmd/baseline/acceptance:40`. Package-side detection fix deferred.
- **Nightly test jobs stay red until nested work is synced** — tests now execute from `vendor/bagart/*`, but CI tests committed HEADs: host + origins until the Q15-A batch lands (audit/menu/bot-lib ahead, kernel-lib/kernel-client behind, all 19 dirty). The `AskQueueLib` suite is stripped in CI while Q17 is open. `continue-on-error` removal (step 5) blocked on Q17 + Q15-A + 3 green runs.
- Full shim retirement (22 remaining) deferred — Q8 Decision.

## Prod-lock tip-freshness control (2026-10-08)

`composer.prod.lock` pins **commit refs** for every `bagart/*` package, so any pushed commit inside a prod-dependency package makes the lock stale — not just a `composer.json` edit. Two RESOLVED questions left this hole open: Q13 made the set-boundary refresh conditional and checklist-based ("`cmd/deps/check` has no tip-freshness control … the checklist entry is the only guard until one is added"), and `prod-lock-tag-hijack` recorded that a future `cmd/release/lib` tag + `prefer-stable` re-arms the trap (next resolution pins the new stable tag instead of the branch tip). Nothing detected a lagging lock: `lock-fresh:prod` only runs `composer validate --check-lock` + purity; CI re-validates only on manifest/lock/`cmd/deps/**` paths; nested-package pushes change none of those — a stale pin reached the pinned nightly undetected (the 2026-10-02 failure class K18 was resolved to prevent).

**Prerequisite repair (discovered while scoping):** `cmd/deps/check` never called `deps_parse_mode`, so the only automated caller (`.github/workflows/prod-install.yml` → `bash cmd/deps/check --mode=prod --format=github`) died with `Unknown option` (exit 2) — neither `lock-fresh:*` nor the new control ever executed in CI. Fixed: `deps_parse_mode both "$@"` before `contract_parse_args`; `MODE` honored for the lock cluster only (`prod` skips `lock-fresh:dev` + `lock-tip-fresh:prod`; `dev` skips the prod pair; default `both` = today's local behavior).

**The control `lock-tip-fresh:prod`:** extracted into a standalone, fixture-testable program `cmd/deps/lock-tip.php` (sibling controls use inline `php -r`; this one needs fixtures). Enumerates both `packages` and `packages-dev` of `composer.prod.lock` (20 `bagart/*` entries incl. the baseline package in `packages-dev`); derives the remote from the lock entry's own `source.url` normalized to HTTPS — no local `misc/` checkout and no credentials required, which is what makes it work in CI's clean checkout; derives the branch from `version` (`dev-main` → `main`); tip via `git ls-remote refs/heads/<branch>`. Three verdicts: **fresh** (reference == origin tip) → pass; **stale** → fail with remediation command; **hijack** (version not `dev-`, a stable-tag pin reintroduced by a future release tag under `prefer-stable`) → policy finding. Tip-source injection for tests (`--tips <json>` map, absent packages → `skipped`). `--offline` → `skipped "--offline: origin tips not queried"` (never fabricates staleness from local refs). Unresolvable remote → `skipped` with reason (never a false failure); `git` absent → exit 3.

**Exit-code composition (CLI contract 0–5):** plain stale → exit 1 via `pass_or_fail`/`FAILS++` (same class as `lock-fresh:*`); hijack-only → exit 5 (`EX_POLICY`), composing `report_finish "$(( FAILS > 0 ? EX_CHECK : (POLICY_FAILS > 0 ? EX_POLICY : EX_OK) ))"` exactly as `cmd/ops/readiness` (check failures dominate policy failures). No new exit codes, no new flags.

**Tag rule documented** at the two places that can act on it: `docs/deps-policy.md` Rules entry 5 ("after any `cmd/release/lib` tag, refresh `composer.prod.lock` immediately") and a post-tag reminder (real + dry-run branches) printed by `cmd/release/lib` after the annotated-tag push — the only remaining re-arm path.

**Tests:** `tests/Unit/Deps/LockTipFreshnessTest.php` (7 cases via `Symfony\Component\Process`): fresh/stale/hijack fixtures (0/1/5), `--tips` map with missing package → skipped, unreachable host → skipped, `--mode=prod` parsing regression (prerequisite A pin), `--offline` skip. Fixtures under `tests/fixtures/deps/`. Q13 residual and the tag-hijack follow-up are closed by this control; the checklist refresh stays the primary guard (the control is its detector, not a replacement).
