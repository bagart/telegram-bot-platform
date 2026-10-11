# Host Module Integration — SDD

## Design

The module engine owns declarative registration and dependency-ordered provider boot. Module descriptors contribute commands, routes, schedules and frontend pages rather than relying on provider-side configuration mutations. Host page generation consumes those declarations.

The integration status document records bootstrap takeover, proxy onboarding, audit-stream integration, routing cleanup, page generation and CI/Docker integration as completed. These are historical implementation claims, not a fresh test or deployment certification.

## Retained Decisions

- Engine activation is the enablement source selected by host configuration. Legacy settings storage remains a separate compatibility dependency: switching activation does not migrate settings.
- Module validation has a dedicated CI workflow, `module-validation.yml`. Docker repository enumeration still requires maintenance when modules are added.
- Validation and doctor diagnostics are acceptance gates for module integration.
- Cutovers should remain reversible through configuration. Destructive storage changes require verified migration and rollback behavior.
- Package publication and production path resolution are separate acceptance concerns from successful development-mode boot.

## W-D4 — Web Activation and CLI Collision (2026-10-10)

### Web activation surface

- `management.modules.*` routes (`GET /modules`, `POST /modules/{botId}/{moduleId}/enable|disable`, middleware `web,auth,verified`) served by the management package's second controller, `ModuleActivationController`. Access copies the settings-screen sibling: per-request workspace-owner resolution via `BotOwnershipResolver` (unowned → 403), unknown module → 404, `Blocked`/`ConcurrentModification` → 422 with blocker list, success and already-in-state → redirect + flash (idempotent boolean, bot scope only).
- The single writer is `ModuleActivationService::enable|disable`; audit rows (`module.runtime.enabled|disabled`) and the engine route-cache refresh are inherited from the existing `BotModuleEnabled|Disabled` listeners — no new listener wiring. E2E proof: `management/tests/Http/ModuleActivationControllerTest.php` (enable → audit row with actor; blocked enable → 422 + no audit row; unowned → 403 + no audit row; `tg:modules:routes:check` reports no drift after a web enable; listener registration asserted post-W-D3).
- The surface is Q4-independent: it does not ride the retired Channel-B `settings/modules` pages, and the nav entry lives in `resources/js/components/app-sidebar.tsx`.
- Contract note: the controller injects the concrete `ModuleActivationService` — `ModuleActivationWriterContract` gains `enable|disable` only when a second non-service consumer appears.

### CLI collision table — resolution state (d4-design-pack §4.1)

- **#1 `tg:modules:list` (the only exact collision): resolved by port+delete** — the management command and its registration entry are gone; its JSON builder is ported to the engine command as `--format=text|json` + `--json` alias (shape `id, name, version, capabilities[], defaultEnabled, requiresModules, conflictsWith`), now covered by the first `--json` assertions in `TgModulesCommandsTest`.
- **#5–#8 `tg:modules:enable|disable` ↔ `tg:module:enable|disable` (near-duplicate pair): documented, kept** — class docblocks and `--help` descriptions cross-link each pair (bot-scoped CAS, 0/5 exits vs platform/chat scope, 0/1 exits); removal stays blocked by the dual-support boundary (edge E2).
- **#2/#4 `tg:modules:doctor` ↔ `tg:modules:diagnose` (scope twins): kept, cross-documented** — enablement-orphan/descriptor consistency vs registry/activations/routing diagnostics.
- **#3, #9–#11 single-owner rows unchanged; #12 `tgbm:*`/`tg:outbound:*` and menu `tg:menu:*` unaffected.** Built-in-family collision detection stays a later `CommandSignatureInspector` contract.

### Retirement wave W1 (doc drift + shadowed command)

- Doc-drift comments corrected to "`engine` is the shipped default; `legacy` = test-fixture support for unmigrated dev DBs": host `config/tg_modules.php`, engine provider `bindDispatchEnablement` docblock, menu provider driver comment.
- Package default flipped `'enablement_driver' => 'legacy'` → `'engine'` (`telegram-platform-module/config/tg_modules.php`) to agree with the host; tests that need legacy pin it explicitly (existing precedents in `tests/Pest.php`, `SettingsWriteTest`, `TgModuleToggleCommandTest`).
- Waves W2/W3 (items 1–8, doctor legacy branch, ~20 suite migrations) remain blocked by `legacy-driver-dual-support.md`; wave W4 (example testsuite) stays with W-D1 §G.
- `docs/module_integration.md` and this file name no deleted command, so their checklist/inventory rows needed no edits for item 9.

### Acceptance gates and live probes (2026-10-10, captured)

```text
php artisan tg:modules:validate         → exit 0: "Module registry OK: 9 module(s) validated."
php artisan tg:modules:doctor           → exit 0: "Module configuration is healthy: enablements, requirements and conflicts are consistent."
                                          (legacy section no-ops via hasTable guards — expected until W3; run with DB_CONNECTION=sqlite :memory: because the pgsql stack was unreachable from the shell)
php artisan tg:modules:diagnose --format=json → exit 0, valid JSON: 9 modules, "errors": []
php artisan route:list --path=modules   → management.modules.index|enable|disable registered (plus the menu tgapp toggle endpoints)
php artisan tg:modules:list --help      → --format[=FORMAT] [default: "text"] and --json shorthand present
php artisan list --raw | grep modules:list → exactly one row (engine description; no management duplicate)
```

## W-D1 — Adoption Tooling and Validation Repair (2026-10-09)

- **`modules:adopt {id}`** (host `app/Console/Commands/ModuleAdoptCommand.php`, M1 auto-discovery, neutral `modules:` prefix family): adoption is a 25-touch-point ordeal — the command splits it GENERATE 4 / VALIDATE 13 / DOCUMENT 8 (Q11). Flags `--check` (validate-only default), `--dry-run`, `--format=text|json` + `--json`, `--verbose`/`--quiet`. Exit codes follow the platform contract (0 clean / 1 assertion failed / 2 unknown id / 3 tool unavailable / 4 bootstrap failure / 5 policy). Output byte-compatible with `cmd/deps/check` consumers (`ok`/`err` text, `{exit_code, results[{control,status,message}]}` JSON).
- GENERATE half writes only the safe host artifacts: the `config/tg_modules.php` `TgModuleConfig` entry (key == descriptor id, refuse-overwrite) and the Inertia page wrapper `resources/js/pages/Settings/<X>.tsx`. VALIDATE half machine-checks the K20/K21/K22 wiring contract (8 reuse of existing `cmd/deps/check`/`tg:modules:validate` controls under same IDs, 13 new assertions, 1 doc-fact) — closing audit gap G2. DOCUMENT half prints a 12-step user-owned checklist (publish nested repo → locks → baselines → compose/Docker → docs rows → verify chain → routes sync); the command never runs composer, lock, or baseline mutations itself (Q11: validate + document, not execute). Test injection via swappable reader services (`AdoptRepository`, `ExternalRunner`), not path flags.
- **`module-validation.yml` repair (R1–R11):** the venue was fatally red (8/8 failing runs: no composer install, `property_exists` on an array, silent `continue-on-error`, dead PSR-4 loop, dead trigger globs, real validator never wired). Repaired to the nightly sequence (checkout → setup-php + composer:v2 → job env pins incl. `APP_KEY`/`TG_MODULE_ENABLED_example`/sqlite → `cmd/deps/install --mode=prod --dev` → `rewrite-vendor-paths.php` → `php artisan tg:modules:validate` blocking with `::error::` annotations, no `continue-on-error`, no hard-coded suite list, no `|| true`). Triggers: push (main only) + pull_request on manifest/lock/workflow paths; dead `misc/**` globs dropped. MANIFEST regenerated. R10: the repair ships no namespace rule (W-Q2's) and no baseline-package edit.
- **Blocking semantics (Q12):** "blocking" for the R3 venue = red job on path-matching runs only — the repo has rulesets `[]` and `main` unprotected; configuring a GitHub ruleset/required check is the operational follow-up that turns the red job into a merge gate. Never registered in `bin/baseline-check` (K25/K24/K29/K28).
- **Duplicate-command rule** (Q5 edge 11) → module-engine.md `DeclaredCommandInspector` section.
- **Example template + Docker REPOS:** `tgbot-module-example` repaired to pass every gate it skipped (own `tests/`, phpunit, `test` script, root `composer test` chain entry, host testsuite row). `docker/common/php-fpm/Dockerfile` REPOS reconciled 18 → 20 (added `ask-queue` + `tgbot-module-example`).
- Doc fixes: scaffold stale next-steps (`bootstrap/providers.php` + `telegram.modules_providers` → `config/tg_modules.php` entry + `modules:adopt`), unsatisfiable `^1.0` constraint → `*`, false proxy/bootstrap claims in `AGENTS.md`/`platform-prompt.md` (proxy is the documented single module exemption in `bootstrap/providers.php` AND declared via `laravelProvider`), broken `docs/INDEX.md` cross-refs, module-authoring playbook rewritten around `modules:adopt`.

## W-D2 — Settings Channels Closure (2026-10-09)

- **Q4 decided option 1: Channel A survives as the single settings contribution channel; Channel B (`settingsScreens`) is retired via B2.** Options 2/4 excluded on evidence (0 identical field sets across channels, 56 declared fields, 1 partial overlap); option 3 (fund config-layer write+read) rejected by the owner. W-D2 deletes nothing — the entire deletion surface (5 config blocks + 6 imports, `SettingsScreenController` 466 L, 3 routes, 8 frontend files, 3 test deletions / 2 edits, 13 optional engine DTOs) is handed to B2.
- W-D2's own code work (branch-independent): **D2-b menu-dependency rule** — Ui-capable descriptors must declare `requiresModules: menu` (hub id `menu` exempt). Two halves: boot-warning exemption (`MISSING_MENU_DEPENDENCY` false-positive on `menu` itself fixed) + build-time `RegistryErrorCode` emission in `ModuleRegistryBuilder` descriptor-validation region. Day-one green (7/8 Ui descriptors already declare it). Rule lives in the builder, not in `TgModulesValidateCommand` (stays file-disjoint from W-Q2). Recorded in module-engine.md Model section.
- Handoff pre-verifications (B1–B6): orphaned imports enumerated (all six `Settings*` imports at `:51-56` die with the blocks), G6 post-cut simulation green (`tg:modules:validate` exit 0 on the cut shape), G8 approval package drafted (3 test files / 865 L / 31 tests + 2 edits), G3 baseline re-grep NO_BASELINE_HITS, edge 12 closed by W-D4 design decision 1.
- No new registrar verb, no channel fold. `webUi`-pattern mapping in the design pack retained as reference only; dead §4.11 registry stays buried. Platform-level config write+read reopens only via a NEW question.

## Web UI Catalog Key Convention (B1, 2026-10-08)

- **Option M: catalogs adopt module-prefixed keys.** Channel-A Mini Apps showed raw translation keys in all 5 locales because manifests declare `t:<module>.<key>` while catalogs were keyed bare — `LocaleResolver` strips `t:` and misses every lookup (381k `TRANSLATION_KEY_UNRESOLVED` hits). Under M the catalogs are rewritten to `t:`-prefixed-module keys (`antispam.*`, `nettools.*`, `summarizer.*`, `tts.*`) so keys match the resolver and refiner convention — data-only, consumers (`MenuCatalog::compileLocalized`, `LocaleResolver`) untouched (that is why M beat R, which would flip menu fixtures, and K, which overlapped W-D1's scaffold file).
- Blast: 4 package catalog files, 0 existing-test flips (recorded baselines re-run: menu fixtures 32 passed / 2924 assertions; proxy+antispam 13 passed). Host regression test `MenuUiTranslationResolutionTest` pins all 6 registrants' manifests resolve in all 5 locales with zero findings. Per-module `*WebUiTranslationKeysTest` files enforce 1:1 manifest↔catalog correspondence.
- stt sub-item: only `TRANSLATION_KEY_INVALID_PREFIX` (literal-English labels), not unresolved — stays optional follow-up. Mafia/proxy normalization skipped (mafia diff would not stay data-only; proxy unregistered, W-Q3's). Platform-wide key-correspondence validator rule stays W-D1/W-Q2 tooling scope.

## Channel-B Settings Deletion (B2, 2026-10-09)

- Executed the retire path: deleted `resources/js/pages/settings/modules/{index,show}.tsx` + 5 `Settings/*.tsx` wrappers, `SettingsScreenController.php`, the 3 `management.settings.*` routes (file kept — `loadRoutesFrom()` consumes it, now 0 routes), all 5 `settingsScreens` config blocks + the 6 orphaned imports (646 → 373 lines, −273). Wayfinder actions regenerated away.
- Engine DTOs RETAINED (measured, not assumed): deleting `SettingsScreenContribution` yields 4 new `class.notFound` errors in the frozen `TgModuleConfig`/`TgModuleDefinition` slots' PHPDoc; G3 level-5 clean would break. `JsonConfigFileAccess`/`ConfigFileAccessContract` stay dormant (zero callers).
- G8-approved test deletions executed: 3 files / 865 L / 31 tests (`SettingsScreenControllerTest` 450/17, `SettingsScreensTest` 342/10, `SettingsScreenContributionTest` 73/4) + 2 edits (`BootSequenceTest`, `ModuleActivationReaderTest`). Engine suite 250 → 234 (−16). G3: NO_BASELINE_HITS across the whole deletion surface; scoped level-5 comparison 0 new findings.
- `docs/config-boundary.md` Layer-2 rewritten to RETIRED (Q4 option 1): zero-reader/zero-writer status, dormant contract classes, historical-only precedence/mapping sections.
- G6 kept green: `php artisan tg:modules:validate` exit 0 after the cut, `remaining_settings_screens=0`. G2 coverage re-measure deferred to set closure (before-number 31.8 % pcov / 32.1 % xdebug).



[Module integration](module_integration.md) tracks legacy settings-binding retirement and Docker repository-list maintenance. `STATUS.md` is a historical summary; unresolved or conflicting completion claims require verification, not deletion.
