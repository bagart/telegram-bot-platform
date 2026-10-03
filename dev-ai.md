# dev-ai.md — Active Development Plan

> **Always read this file first.** This is the source of truth for current work.

## Workflow Rules

### ОБЯЗАТЕЛЬНЫЙ ЦИКЛ (для каждого изменения)

```
0. DELTA — git status + git diff, определить что уже сделано
1. ANALYZE — прочитать dev-ai.md, найти [ ] задачи в roadmap
2. PLAN — создать/обновить docs/tasks/<topic>.md
3. IMPLEMENT — пишу код/тесты
4. VERIFY — запускаю тесты, lint, проверяю git diff
5. COMPRESS — сжимаю суть в docs/sdd/<topic>.md (без кода, только декларативно)
6. UPDATE DOCS — обновляю README, пользовательскую документацию
7. CLEANUP — удаляю выполненные подзадачи из tasks/, если всё — удаляю файл
```

**Нарушение цикла = технический долг. Всегда проходи шаги 0-7 перед завершением.**

### ЗАПРЕЩЕНО

- **ЗАПРЕЩЕНО спрашивать "какую задачу делать"** если roadmap содержит `[ ]` задачи — делать все невыполненные
- **ЗАПРЕЩЕНО начинать implementation без DELTA анализа** — сначала `git status`/`git diff` чтобы понять текущее состояние
- **ЗАПРЕЩЕНО пропускать шаги цикла** — каждый шаг обязателен
- **ЗАПРЕЩЕНО писать код без task-файла** — если нет `docs/tasks/<topic>.md`, создай план сначала
- **ЗАПРЕЩЕНО оставлять task-файл на 100% готовности** — если всё выполнено, файл должен отсутствовать

### Response Footer (mandatory)

Every response MUST end with a status line:

```
[platform|<module-name>] <task-name> — <N>% ready | planning
```

### Task Lifecycle (strict)

```
DELTA → PLAN → IMPLEMENT → VERIFY → SDD → UPDATE DOCS → CLEANUP
```

1. **DELTA** — `git status` + `git diff`, understand current state before any changes
2. **PLAN** — write to `<module>/docs/tasks/<topic>.md` (only UNDONE items)
3. **Implementation** — execute plan, mark tasks complete in `docs/tasks/`
4. **Verification** — run tests, review `git diff`, check code quality
5. **SDD compression** — after implementation, compress the essence into `<module>/docs/sdd/<topic>.md` (declarative, no code). This is MANDATORY — every completed task must leave an SDD trace.
6. **Update user docs** — update README, docs, examples
7. **Cleanup** — remove completed items from task file; if all done, DELETE the file

### Enforcement Rules (MANDATORY)

- **Task file MUST exist BEFORE any `edit`/`write`/`bash` that modifies source code.**
- **Task files contain ONLY undone items.** Checked items are deleted immediately.
- **If 100% done — task file must NOT exist.** Verify: `ls docs/tasks/` shows no completed task files.
- **If the user asks to "just do X"** — still create the task file first. The workflow is not optional.

### SDD File Naming

| Topic | SDD File | When to create |
|-------|----------|----------------|
| Domain Core | `docs/sdd/domain.md` | After identity, lifecycle, failure taxonomy |
| Parser | `docs/sdd/parser.md` | After parser grammar, import pipeline |
| Transport | `docs/sdd/transport.md` | After adapters, DNS, resource governor |
| Audit Pipeline | `docs/sdd/audit.md` | After job lifecycle, health evaluation, events |
| Cache | `docs/sdd/cache.md` | After probe cache, cache-aware planner |
| Pools & Lease | `docs/sdd/pools.md` | After pool model, selection, lease |
| Worker | `docs/sdd/worker.md` | After worker daemon, execution pipeline |
| Bot Interface | `docs/sdd/bot.md` | After bot commands, wizard flows |
| Frontend | `docs/sdd/frontend.md` | After Mini App, Web Admin pages |
| API | `docs/sdd/api.md` | After REST API, gateway API |
| Security | `docs/sdd/security.md` | After encryption, SSRF, credential delivery |
| Production | `docs/sdd/production.md` | After backup, benchmarking, alerting, health |
| Post-MVP | `docs/sdd/postmvp.md` | After incident engine, decision log, feed sync |

### Documentation Structure

Each plugin/module owns its own docs:

```
misc/BAGArt/<module>/docs/
├── tasks/           ← active plans with checklists (only UNDONE items)
│   └── <topic>.md
├── sdd/<topic>.md   ← compressed essence (thesis-style, no code)
├── adr/             ← Architecture Decision Records (optional)
└── architecture.md  ← reference architecture (optional, legacy)
```

### Documentation Rules

- `<module>/docs/tasks/<topic>.md` — active plans with checklists (temporary, deleted after completion)
- `<module>/docs/sdd/<topic>.md` — compressed essence (thesis-style, no code) — permanent
- `dev-ai.md` (this file) — current state and roadmap (always read first)
- **Scope includes the entire `misc/` tree**, including nested repositories: its documentation, roadmaps, task files, source code, and tests are part of the current platform scope. A platform-wide task or completeness audit must cover every package under `misc/`, not only the host docs or `docs/tasks/`. Distinguish documented backlog from source-verified gaps and stale completion markers.
- **Task files contain ONLY undone items.** Done items are removed immediately.
- **Old tasks → compress into SDD, then DELETE from `docs/tasks/`** — task files are ephemeral
- **Every completed task MUST have a corresponding SDD entry.** If SDD doesn't exist — create it. If it exists — append the new decisions/rationale.
- Reference architecture docs (e.g. `architecture.md`) stay as-is — they are not tasks

### Compression Style

After implementation, SDD gets:
- What was done (declarative)
- Key decisions and rationale
- Output files and purpose
- Architecture diagrams (text)
- NO implementation details, NO code

**Timing:** SDD compression happens IMMEDIATELY after task implementation, before cleanup. Do not defer SDD updates.

---

## Current State

### Platform Libraries

| Package | Path | Status | Notes |
|---|---|---|---|
| Async Kernel | `php-async-kernel-lib/` | ✅ stable | Fiber scheduler, daemons, tickables, shutdown, all M/L issues resolved |
| ASK Client | `php-async-kernel-client/` | ✅ stable | HTTP transports, lockers, queue adapters |
| ASK Redis | `php-async-kernel-client-redis/` | ✅ stable | Redis fiber client, locks, queues, DLQ |
| Bot Lib | `telegram-bot-lib/` | ✅ stable | ~450 DTOs, API client, outbound pipeline |
| Bot Lib Basic | `telegram-bot-lib-basic/` | ✅ stable | CLI commands: poller, chatting, webhook |
| Platform Module | `telegram-platform-module/` | ✅ 100% | Module engine: registry, activation, routing, prod-mode paths |
| Platform Management | `telegram-platform-management/` | ✅ 100% | 327 tests — multi-bot DB models, webhook routing, workspaces/entities/bot catalog, all commands tested |
| Platform Access | `telegram-platform-access/` | ✅ 100% | 147 tests, workspace scope + T0/T1/T2 catalog |
| Platform Audit | `telegram-platform-audit/` | ✅ 100% | Audit sink, DTOs, DB impl, hash chain |
| Platform Menu | `telegram-platform-menu/` | ✅ 100% | — | Web Menu hub, Mini App SPA, TabBar, Wave 4 tests |

### Platform Modules (TgModuleContract plugins)

| Module | Path | Status | Tests | Remaining |
|---|---|---|---|---|
| Antispam | `tgbot-module-antispam/` | ✅ 100% | — | — |
| Summarizer | `tgbot-module-summarizer/` | ✅ 100% | — | — |
| TTS | `tgbot-module-tts/` | ✅ 100% | — | v0.2.0: locale expansion, stale config cleanup, dead code removal |
| Nettools | `tgbot-module-nettools/` | ✅ 100% | 205 | — |
| Proxy | `tgbot-module-proxy/` | ✅ 100% | 1152 | W1c deferred |
| Mafia Game | `tgbot-game-mafia/` | ✅ 100% | 171 | Quickplay, rematch, Mini App acceptance, T2 game.initiate gate |

### DevOps

| Section | Status | Notes |
|---|---|---|
| Publication chain | 🟢 ~97% | 3/5 CI green; composer-SBOM deferred (image SBOM covers release artifact) |
| GitHub hardening | ✅ 100% | All checks pass |
| Gate promotions | ✅ 100% | Mutation gate **enforced** for `bagart/async-kernel` (floor 97, measured 100.00%, direct control + `--baseline`), hooksPath repaired, 4 dead shims deleted; nightly CI = vendor-install redesign **implemented** (Q10–Q17 resolved; `composer.prod.json` full manifest + `--dev` install + `cmd/ci/rewrite-vendor-paths.php` + nightly stubs removed; e2e sim green); **Q15-A batch executed 2026-10-02** (19 nested repos pushed, `bagart/ask-queue` published + wired, rewrite strip→map); **promotion COMPLETE 2026-10-03** — 3 consecutive green all-blocking nightly runs (37114626742, 37115186051, 37115739626): coverage **blocking at floor 30** (measured 31.8% pcov / 32.1% xdebug, `docs/questions/coverage-min-threshold.md`), mutation **blocking at floor 97** (924 mutations, 100.00%), `continue-on-error` dropped on both jobs; job fixes: `pcov.directory=.`, coverage redis + asset build, phpunit `<source>` → `vendor/bagart` rewrite, mutation `--path`; pin `c13903f`; SDD `docs/sdd/mutation-gate-nightly.md` |
| Baseline Phase 7 | 🟡 ~30% | Module discovery probe 9/9; shim retirement deferred (`docs/questions/baseline-phase7-shim-retirement.md`) |

### What's Left

1. ~~**Prod lock refresh**: `cmd/deps/update --mode=prod` (stale lock; needs git auth for private `tgbot-module-proxy` repo)~~ **DONE 2026-10-02:** both locks refreshed via `cmd/deps/update --mode=both`; `cmd/deps/check` EXIT 0 (`lock-fresh:dev`, `lock-fresh:prod`, `deps-mode-purity`, `deps-parity` all green; prod lock pure — 0 path refs, `bagart/ask-queue` resolved as VCS zip).
2. **Branch protection**: Manual GitHub settings
3. ~~**Menu TabBar**: Pure stub — needs click handlers, active state, navigation, backend integration~~ **DONE (2026-09-20):** TabBar wired with `activeKey`/`onTabSelect`, `<button>` elements, `.tg-tab-active` CSS, navigation via controller, hidden during overlays/chunks.
4. ~~**Menu Wave 4**: Permission tests, cache invalidation~~ **DONE (2026-09-20):** MutationRoleGate, ModulePermissionMasks, cross-module isolation, settings invalidation chain — 28 new tests across 4 files.
5. ~~**Management missing 20%**: 4 untested commands (Audit, Poller, Migrate, McpStart)~~ **DONE (2026-09-20):** 15 new tests across 4 files (Audit: 8, Poller: 2, Migrate: 2, McpStart: 3).
6. ~~**Full test-suite verification**: Cross-package test run~~ **DONE (2026-09-21):** Queue adapter contract split fixed (`AtomicCacheContract` moved to `AskQueue\Contracts`, 12 duplicate contracts deleted from `ASKClient\Contracts\Queue`). Inertia `FileViewFinder` TypeError fixed (missing `page_paths`/`page_extensions` keys in `config/inertia.php`). Verified: TTS 120 pass, Management 25 pass, syntax clean, pint clean. **Re-verified (2026-09-28):** root `vendor/bin/pest` FULL run = **5139 passed / 0 failed** (12 deprecated, 10 warnings, 3 risky, 22 skipped — all pre-existing categories). Fixed en route: TelegramBotExample PSR-4 autoload + example registry entry (45), legacy-enablement Pest bootstrap pattern for lib/antispam/summarizer/stt/tts suites, Inertia module `page_paths` glob, baseline engine autoload fallback + MANIFEST regen (13), Redis DSN/protocol-decoder product bugs (24), `amphp/http-client` dev-dep mirror (1), stale fixtures/assertions in basic-lib/redis/stt tests, Prompts cross-suite fallback static reset in `WebhookCommandTest`. **Gate green (2026-09-29):** full `composer test` = **EXIT 0** (root 5142 passed + all 16 package suites green, pint/policy/lock-dev/manifest checks pass). Fixed en route: broken package `test` scripts — phpunit→pest (nettools/stt/summarizer), host-suite delegation for lib-basic/management/tts/redis (`composer exec pest --colors` / missing own pest / stale vendor), summarizer `phpunit.xml` sqlite env, `SettingsScreensTest` config path `dirname(__DIR__, 6)` + `Application` swap shim (4 formerly-silent skips now real & passing). Known blocked: `lock-fresh:prod` only (tgbot-module-proxy VCS repo unreachable; all other `cmd/deps/check` controls pass).
7. ~~**Legacy settings-storage**: Chat-level scoping still blocks full tg_module_enablements retirement~~ **DONE (2026-09-20):** `EngineSettingsWriter` created (writes via `DatabaseSettingsStorage` + `ModuleActivationService`); conditional binding in `TelegramBotMenuServiceProvider`; `MenuInstallCommand` and `MenuUninstallCommand` migrated to `SettingsWriterContract`; chat-level scoping dropped (Option B: bot-level only). **DONE (2026-09-21):** Data migration + table drop migrations created (`2026_09_20_000001_*` and `2026_09_20_000002_*` in management). Task file deleted — remaining work documented in SDD. **DONE (2026-09-29, Wave A "settings-migration-engine"):** the remaining gap closed — `ModuleSettingsContract` extended to 3 methods implemented on BOTH drivers (legacy thin impl + `EngineSettingsAdapter`), engine read gains chat→bot inheritance, engine dispatch honors `{chatId}:__enabled__` (via `ModuleActivationReader::isEffectivelyEnabledForChat`), 8 legacy call sites retargeted (antispam ×3, summarizer ×2, tts, stt, `TgModulesDoctorCommand`), stale `INTENTIONAL BRIDGE` comment removed, engine seam test `tests/Feature/ModuleSettingsEngineModeTest` added (pins kept per `docs/questions/module-suite-engine-pinning.md` decision). Full gate re-verified: `composer test` EXIT 0, root **5180 passed**. SDD: `docs/sdd/settings-migration-contract.md` (+ index row). Decisions: `docs/questions/settings-*`, `engine-*`, `module-suite-engine-pinning`, `legacy-driver-dual-support` (all RESOLVED).
8. **Open task files (registered, `docs/tasks/`):** **none — closed 2026-10-03, directory holds only `adr/`**. **`wave-c-mutation-gate.md` + `q15a-batch-inventory.md` CLOSED 2026-10-03** (2a+2b 2026-10-01: full prod manifest + `--dev` install + `cmd/ci/rewrite-vendor-paths.php` + nightly redesign, e2e sim green; 2c Q15-A batch 2026-10-02: all 19 nested repos committed+pushed incl. conflict resolutions, `bagart/ask-queue` published → github.com/bagart/ask-queue + dev path-repo/PSR-4 + prod VCS/`require @dev` + both locks regenerated + rewrite strip→map (Q17-A), F3 baseline committed, F4 workflows pushed, F6 junk dropped, host §3.1–§3.4 committed; final 2026-10-03: host push → **3 green all-blocking nightly runs 37114626742 / 37115186051 / 37115739626** → `continue-on-error` dropped on coverage+mutation, coverage floor **30** (measured 31.8% pcov / 32.1% xdebug — `docs/questions/coverage-min-threshold.md`, decision A), mutation floor 97 @ 924 mutations / 100.00%, job fixes (`pcov.directory=.`, coverage redis+asset build, phpunit `<source>` → `vendor/bagart` rewrite, mutation `--path=vendor/bagart/async-kernel/src`), pin `c13903f`, STATUS §3 → 100%, SDD `docs/sdd/mutation-gate-nightly.md`, **both task files deleted**). **`summarizer-one-flag-optin` CLOSED 2026-10-01** (Q11 E→A: one `isEnabled()` source of truth, chat-default OFF via descriptor `defaultChatEnabled`, gate → `isEnabled()`, DTO `enabled` dropped, web field removed, suite → engine driver; lib `ModuleEnablementContract::isEnabled` gained nullable chatId + selector bot-scope dispatch for commands/chat-member — decisions in `docs/questions/summarizer-{chat-default-mechanism,dispatch-exemption,suite-driver,web-enabled-field}.md`; en-route antispam `moduleId()` import fix; `composer test` EXIT 0; SDD `misc/BAGArt/tgbot-module-summarizer/docs/sdd/summarizer.md` § One-Flag Opt-In; task file deleted). Wave A (`settings-migration-engine`) CLOSED 2026-09-29 → SDD `docs/sdd/settings-migration-contract.md`; Wave B (docs cleanup) CLOSED same day; Wave C steps 1–4, 6–8 + VERIFY + SDD done same day → SDD `docs/sdd/mutation-gate-nightly.md` (CI decisions Q10–Q17 folded in 2026-10-01).

---

## Module Documentation Index

| Module | Tasks | SDD | ADR | Architecture |
|---|---|---|---|---|
| tgbot-module-proxy | — | `docs/sdd/README.md` | `docs/adr/ADR-001-stage0-contracts.md` | — |
| telegram-platform-module | Architecture roadmap caveats | `docs/sdd/module-engine.md` | — | `docs/architecture/` |
| telegram-platform-access | — | `docs/sdd/access-control.md` | — | `docs/architecture.md` |
| tgbot-game-mafia | `docs/redesign-plan.md` | `docs/sdd/mafia-game.md` | — | — |
| telegram-bot-lib-basic | — | `docs/sdd/basic-lib.md` | — | `docs/EN/processing.md`, `docs/RU/processing.md` |
| tgbot-module-nettools | — | `docs/sdd/nettools.md` | — | `Readme.md` (root) |
| tgbot-module-antispam | — | `docs/sdd/antispam.md` | — | — |
| tgbot-module-summarizer | — | `docs/sdd/summarizer.md` | — | — |
| tgbot-module-tts | — | `docs/sdd/tts.md` | — | — |
| telegram-platform-management | — | `docs/sdd/management.md` | — | — |
| telegram-platform-menu | — | `docs/sdd/menu.md` | — | — |
| telegram-platform-audit | — | `docs/sdd/audit.md` | — | — |
| Host integration | `docs/module_integration.md` | `docs/sdd/module-integration.md` | — | — |

> Task-directory READMEs are conventions, not active tasks. Absence of a task file does not establish completion; check module READMEs and architecture caveats. Documentation cleanup preserved deferred and unverified work without changing implementation.

---

## Roadmap

### tgbot-module-proxy

**Goal:** Full proxy lifecycle management — import, audit, health, pools, lease, export.

Completed core, post-MVP and worker decisions are consolidated in [Proxy SDD](misc/BAGArt/tgbot-module-proxy/docs/sdd/README.md). Completed checklists are retired; historical completion percentages above are not fresh verification results.

### Management: workspaces & mirrored admin rights

- [x] Management: workspaces & mirrored admin rights — **DONE (2026-09-28).** `management-admin-rbac` workstream; ADR-001…004 + decisions D1–D15 permanent in `docs/tasks/adr/` (task file deleted after completion). **Phase 1 (identity) DONE (2026-09-25):** `users.email`/`password` nullable + `telegram_id` fillable/cast; `TelegramIdentityService` (race-safe provision, link, lockout-guarded unlink, `IdentityException`); menu `tg_login_challenges` + `LoginChallengeService` (sha256 at rest, TTL 10 min, single-use); `/login <code>` bot processor (private-chat only, rate-limited, no platform URLs); `/tg-auth/challenge` store/show/complete + `DELETE /tg-auth/telegram` (throttled); device-code login page (2s poll → session) with break-glass `/login/email`; profile `telegramId` prop + link/unlink surface; proxy `WorkspaceResolver` raw-insert bug fixed via identity service. **Phases 2–6 DONE (2026-09-26…28):** workspace-owned `tg_entities` registry (`EntityLinkService`, `EntityAddVerifier` + `HttpChatAdministratorProbe`); permission layer activated — `access_grants` workspace scope, `PermissionCatalog` T0/T1/T2 (T1 never platform-grantable), `tg_chat_role_grants` data migration, menu `AccessGrantRoleStore` bridge + `TierContext` provenance flag, `menu:t1:reconcile` daily T1-sync layer; `tg_bots` ecosystem (`class`/`availability`/encrypted `external_token`, `BotCatalogService`, `ConnectedBotRoster`); `tg_bot_owners` retired as a rights source (`BotOwnershipResolver` → menu `WorkspaceBotOwnerSource`); Telegram bot-DM surfaces `/invite` `/join` `/wsadmin` + entity-add + i18n (54 keys × 5 locales); T2 gates in mafia/antispam; superadmin web `/platform/workspaces|bots` (Menu-hosted, `menu.superadmins` gate, 52 keys × 5 locales). Verification: host `tests/Feature/Rbac` 63 green (isolation matrix, 36-row deny dataset, T1-mirror revocation) + management 327, access 147, menu-unit 277, menu-feature 275, mafia 171, audit 103. **SDD:** `misc/BAGArt/telegram-platform-management/docs/sdd/management.md`, `misc/BAGArt/telegram-platform-menu/docs/sdd/menu.md`, `misc/BAGArt/telegram-platform-access/docs/sdd/access-control.md` (+ short bullets in the mafia/antispam SDDs).

### Platform Configuration & Modularity

- [x] Module enable/disable via env vars: [module-enablement-toggle task](docs/tasks/module-enablement-toggle.md) — operators toggle modules without editing PHP. **DONE (2026-09-18):** env('TG_MODULE_ENABLED_<key>') + tg:modules:status + diagnose override detection + 6 tests.
- [x] Config boundary documentation: **DONE (2026-09-20):** `docs/config-boundary.md` created — 3-layer model diagram, resolution chain, module mapping, worked examples. Architectural tests deferred (standalone follow-up).
- [x] Expand settingsScreens: [settings-screens-expansion task](docs/tasks/settings-screens-expansion.md) — connect antispam, summarizer, tts, nettools to the settings system. **DONE (2026-09-18):** 4 modules connected (23 fields total) + 4 TSX pages + 4 tests.
- [x] Prod-mode path resolution: **DONE (2026-09-20):** `sourcePath` property on TgModuleDescriptor/Config/Definition; ModuleRegistryBuilder resolves relative paths; 11 new tests; tg:modules:validate passes.

### Module Engine Bug Fixes

- [x] Fix settings storage data loss: [fix-settings-storage-data-loss task](docs/tasks/fix-settings-storage-data-loss.md) — P0: set() destroys existing settings, all() misses multi-module data. **DONE (2026-09-18):** Atomic JSONB operations, driver-aware, 5 new tests.
- [x] Fix activation race conditions: [fix-activation-race-conditions task](docs/tasks/fix-activation-race-conditions.md) — P1: INSERT race, TOCTOU, non-atomic RouteTableSync. **DONE (2026-09-18):** All 3 bugs fixed + 3 tests.
- [x] Module engine P2 hardening: [module-engine-hardening-p2 task](docs/tasks/module-engine-hardening-p2.md) — 16 medium-severity issues (memo leak, stale cache, missing validation, etc.). **DONE (2026-09-18):** All 16 fixed.
- [x] Module engine test coverage: [module-engine-test-coverage-gaps task](docs/tasks/module-engine-test-coverage-gaps.md) — 15 test gaps (chat-level settings, concurrent writes, arch tests, boot integration). **DONE (2026-09-18):** 57 new tests, 444 assertions.

### Async Kernel Library

- [x] Fix async kernel issues: [async-kernel-issues task](misc/BAGArt/php-async-kernel-lib/docs/tasks/async-kernel-issues.md) — 25 issues. **DONE (2026-09-18):** Phase 1: C3–C5, H1–H2. **DONE (2026-09-19):** H3–H6. **DONE (2026-09-20):** M1–M9 (tests: ASKPromiseComprehensive 40 tests, InMemoryCacheCoverage 34 tests, CacheLockerToctouRace 4 tests; fixes: M6 result() throws, M7 dead code removed, M9 stale deadline, L4 todo.md ref removed). L1-L5 verified as already handled.

### Ask-Client (php-async-kernel-client)

- [x] Ask-Client phases 1–4: correctness fixes, ask-queue extraction, connection pool, DX improvements. **DONE (2026-09-18).** Sub-tasks **DONE (2026-09-19):**
  - [x] Consumer namespace migration — 4 files migrated from `ASKClient\Contracts\Queue\*` to `AskQueue\Contracts\*`.
  - [x] Connection pool metrics — `ConnectionPool::getMetrics()` added with per-host breakdown.
  - [x] Timeout diagnostics — `failConnection()` now enriches `ASKNetworkException` with timing context. Logging deferred (no PSR-3 in ask-client).
  - [x] HTTP transport registry tests — 11 tests covering register/has/get/make/types/chaining.
  - [x] ASKRateLimiter tests — 8 tests covering token bucket, flood wait, reset, key independence.

### Deferred (no timeline)

- [ ] Multi-region fleet (R10).
- [ ] Standalone parser-svc (R13).
- [ ] W1c-EXEC: ExternalBinaryExecutor — deferred because current tools are PHP-native.

### Remaining Integration and Acceptance

- [x] Work the platform-wide backlog recorded on 2026-09-17: [platform backlog task](docs/tasks/platform-backlog.md). **DONE (2026-09-20):** RESTART_DAEMON no-op fixed (AsyncKernel.php), ProxyWalArchive heredoc fixed, AuditCompletedProjectionConsumer now invokes VerifiedProxyProjector. Audit tx already correct. Remaining: verification-only items (Management 80%, Menu TabBar, full test-suite).
- [x] Recover documentation accuracy and navigation: [documentation recovery task](docs/tasks/documentation-recovery.md). **DONE (2026-09-20):** SDD-tts reference fixed (TTS docs/INDEX.md), doc1 residue removed (platform-prompt.md). Deferred recovery items remain open.
- [x] Resolve legacy settings-storage dependencies before retiring enablement bindings; see [remaining integration work](docs/module_integration.md). **DONE (2026-09-20):** `EngineSettingsAdapter` created for `bot_module_activations`; legacy `TgModuleEnablementService` skipped when engine driver active; Phase 4.4 partially resolved (chat-level scoping still blocks full migration).
- [x] Complete or verify Mafia quickplay, rematch and Mini App requirements in [the remaining redesign plan](misc/BAGArt/tgbot-game-mafia/docs/redesign-plan.md). **DONE (2026-09-20):** Rematch acceptance verified (coordinator + callback complete). Mini App: spectator delay fix (removed no-op deadlineAt manipulation), coordinator response toast wired (castNight/castVote/skipNight), 8 regression tests added.
- [x] Verify Menu publication, settings writer and cross-module integration status recorded in [Menu SDD](misc/BAGArt/telegram-platform-menu/docs/sdd/menu.md). **DONE (2026-09-20):** Wave 3 complete — `EngineModuleEnablementSource` replaces legacy `DatabaseModuleEnablementSource`, N+1 memoization in `MenuAssembler`, DI audit clean, i18n verified complete.
- [x] Execute Menu contract improvements: **DONE (2026-09-20):** Wave 3 complete — performance (memoization), enablement resolver (engine adapter), DI audit (clean), i18n (verified). Wave 4 (permission tests, invalidation) remains deferred.
- [x] Reconcile engine production-path and rollout caveats in its architecture roadmap with the host integration status before marking production rollout complete. **DONE (2026-09-20):** `sourcePath` resolves prod-mode paths; `07-mvp-roadmap.md` caveat acknowledged as resolved.

---

## Architecture Notes

### Module System

- All Telegram feature modules implement `TgModuleContract` (plugin pattern)
- Module engine (`telegram-platform-module`) handles: registry, activation, routing, capabilities, diagnostics
- Modules live in `misc/BAGArt/<module-name>/` (path-repo, dev mode)
- Host autoloader regen needed when adding new PSR-4 namespaces

### Key Conventions

- PHP 8.5, `declare(strict_types=1)`, LF line endings
- DTOs: `final readonly` classes, constructor-promoted, no setters
- Tests: Pest 4, `tests/Unit/` + `tests/Feature/`
- Multi-tenant: `tenant_id` mandatory in all domain tables
- Secrets: never logged, encrypted at rest, masked by default
- **CLI scripts directory: `cmd/`** (not `commands/`). All library modules use `cmd/` for CLI scripts, daemons, benchmarks, and examples. Root-level shim scripts in `cmd/` point into library `cmd/` dirs.
- **Parallel multi-agent development:** Before starting any non-trivial task, analyze the task, plan an efficient parallel multi-agent execution strategy, and use it. Goal: minimize context window per agent. Split independent work across parallel agents; reserve sequential execution only for strictly dependent steps.
