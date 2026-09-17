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
5. COMPRESS — сжимаю суть в docs/SDD-<topic>.md (без кода, только декларативно)
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
5. **SDD compression** — after implementation, compress the essence into `<module>/docs/SDD-<topic>.md` (declarative, no code). This is MANDATORY — every completed task must leave an SDD trace.
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
| Domain Core | `docs/SDD-DOMAIN.md` | After identity, lifecycle, failure taxonomy |
| Parser | `docs/SDD-PARSER.md` | After parser grammar, import pipeline |
| Transport | `docs/SDD-TRANSPORT.md` | After adapters, DNS, resource governor |
| Audit Pipeline | `docs/SDD-AUDIT.md` | After job lifecycle, health evaluation, events |
| Cache | `docs/SDD-CACHE.md` | After probe cache, cache-aware planner |
| Pools & Lease | `docs/SDD-POOLS.md` | After pool model, selection, lease |
| Worker | `docs/SDD-WORKER.md` | After worker daemon, execution pipeline |
| Bot Interface | `docs/SDD-BOT.md` | After bot commands, wizard flows |
| Frontend | `docs/SDD-FRONTEND.md` | After Mini App, Web Admin pages |
| API | `docs/SDD-API.md` | After REST API, gateway API |
| Security | `docs/SDD-SECURITY.md` | After encryption, SSRF, credential delivery |
| Production | `docs/SDD-PRODUCTION.md` | After backup, benchmarking, alerting, health |
| Post-MVP | `docs/SDD-POSTMVP.md` | After incident engine, decision log, feed sync |

### Documentation Structure

Each plugin/module owns its own docs:

```
misc/BAGArt/<module>/docs/
├── tasks/           ← active plans with checklists (only UNDONE items)
│   └── <topic>.md
├── SDD-<topic>.md   ← compressed essence (thesis-style, no code)
├── adr/             ← Architecture Decision Records (optional)
└── architecture.md  ← reference architecture (optional, legacy)
```

### Documentation Rules

- `<module>/docs/tasks/<topic>.md` — active plans with checklists (temporary, deleted after completion)
- `<module>/docs/SDD-<topic>.md` — compressed essence (thesis-style, no code) — permanent
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
| Async Kernel | `php-async-kernel-lib/` | ✅ stable | Fiber scheduler, daemons, tickables, shutdown |
| ASK Client | `php-async-kernel-client/` | ✅ stable | HTTP transports, lockers, queue adapters |
| ASK Redis | `php-async-kernel-client-redis/` | ✅ stable | Redis fiber client, locks, queues, DLQ |
| Bot Lib | `telegram-bot-lib/` | ✅ stable | ~450 DTOs, API client, outbound pipeline |
| Bot Lib Basic | `telegram-bot-lib-basic/` | ✅ stable | CLI commands: poller, chatting, webhook |
| Platform Module | `telegram-platform-module/` | ✅ 99% | Module engine: registry, activation, routing |
| Platform Management | `telegram-platform-management/` | ✅ 80% | Multi-bot DB models, webhook routing |
| Platform Access | `telegram-platform-access/` | ✅ 100% | 98 tests, all bugs fixed |
| Platform Audit | `telegram-platform-audit/` | ✅ 100% | Audit sink, DTOs, DB impl, hash chain |
| Platform Menu | `telegram-platform-menu/` | ✅ 98% | Web Menu hub, Mini App SPA |

### Platform Modules (TgModuleContract plugins)

| Module | Path | Status | Tests | Remaining |
|---|---|---|---|---|
| Antispam | `tgbot-module-antispam/` | ✅ 100% | — | — |
| Summarizer | `tgbot-module-summarizer/` | ✅ 100% | — | — |
| TTS | `tgbot-module-tts/` | ✅ 100% | — | — |
| Nettools | `tgbot-module-nettools/` | ✅ 100% | 205 | — |
| Proxy | `tgbot-module-proxy/` | ✅ 100% | 1152 | W1c deferred |
| Mafia Game | `tgbot-game-mafia/` | ✅ 95% | 115 | Phase 5 (Mini App) — may be partial |

### DevOps

| Section | Status | Notes |
|---|---|---|
| Publication chain | 🟢 ~97% | 3/5 CI green |
| GitHub hardening | ✅ 100% | All checks pass |
| Gate promotions | ✅ ~60% | Mutation floors report-only |
| Baseline Phase 7 | 🟡 ~30% | Module discovery probe 8/8 |

### What's Left

1. **Prod lock refresh**: `cmd/deps/install --mode=prod`
2. **Branch protection**: Manual GitHub settings

---

## Module Documentation Index

| Module | Tasks | SDD | ADR | Architecture |
|---|---|---|---|---|
| tgbot-module-proxy | — | `docs/sdd.md` | `docs/adr/ADR-001-stage0-contracts.md` | — |
| telegram-platform-module | Architecture roadmap caveats | `docs/SDD-module-engine.md` | — | `docs/architecture/` |
| telegram-platform-access | — | `docs/SDD-access-control.md` | — | `docs/architecture.md` |
| tgbot-game-mafia | `docs/redesign-plan.md` | `docs/SDD-mafia-game.md` | — | — |
| telegram-bot-lib-basic | — | `docs/SDD-basic-lib.md` | — | `docs/EN/processing.md`, `docs/RU/processing.md` |
| tgbot-module-nettools | — | `docs/SDD-nettools.md` | — | `Readme.md` (root) |
| tgbot-module-antispam | — | `docs/SDD-antispam.md` | — | — |
| tgbot-module-summarizer | — | `docs/SDD-summarizer.md` | — | — |
| tgbot-module-tts | README conditional follow-up | `docs/SDD-tts.md` | — | — |
| telegram-platform-management | Completion status unverified | `docs/SDD-management.md` | — | — |
| telegram-platform-menu | SDD open/unverified follow-ups | `docs/SDD-menu.md` | — | — |
| telegram-platform-audit | — | `docs/SDD-audit.md` | — | — |
| Host integration | `docs/module_integration.md` | `docs/SDD-module-integration.md` | — | — |

> Task-directory READMEs are conventions, not active tasks. Absence of a task file does not establish completion; check module READMEs and architecture caveats. Documentation cleanup preserved deferred and unverified work without changing implementation.

---

## Roadmap

### tgbot-module-proxy

**Goal:** Full proxy lifecycle management — import, audit, health, pools, lease, export.

Completed core, post-MVP and worker decisions are consolidated in [Proxy SDD](misc/BAGArt/tgbot-module-proxy/docs/sdd.md). Completed checklists are retired; historical completion percentages above are not fresh verification results.

### Deferred (no timeline)

- [ ] Multi-region fleet (R10).
- [ ] Standalone parser-svc (R13).
- [ ] W1c-EXEC: ExternalBinaryExecutor — deferred because current tools are PHP-native.

### Remaining Integration and Acceptance

- [ ] Resolve legacy settings-storage dependencies before retiring enablement bindings; see [remaining integration work](docs/module_integration.md).
- [ ] Complete or verify Mafia quickplay, rematch and Mini App requirements in [the remaining redesign plan](misc/BAGArt/tgbot-game-mafia/docs/redesign-plan.md).
- [ ] Verify Menu publication, settings writer and cross-module integration status recorded in [Menu SDD](misc/BAGArt/telegram-platform-menu/docs/SDD-menu.md).
- [ ] Reconcile engine production-path and rollout caveats in its architecture roadmap with the host integration status before marking production rollout complete.

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
