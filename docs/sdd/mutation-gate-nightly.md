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

## Nightly CI strategy — decisions (2026-10-01, `docs/questions/`)

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
