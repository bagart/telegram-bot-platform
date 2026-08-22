# 00 — Remediation Plan & Parallel Execution Orchestrator

**Status:** Implemented
**Updated:** 2026-08-22
**Scope:** all documents in `docs/tasks/devops-safe/`
**Implementation state:** docs remediation complete (all waves below done); baseline implemented in repo — see commits `9caa2ef` (SDD set) and `696fd7f` (tooling, hooks, security, health/metrics, CI, ops/DR). Live entry points are documented in `AGENTS.md` § Baseline Tooling and `cmd/help`.
**Role:** coordination only. All executable work is described in self-contained briefs under `tasks/`. This file defines the execution model, canonical contracts, and status. An agent dispatched to a brief needs **only that brief + the files it lists** — never the whole set.

---

## 1. Execution model

Work is partitioned **by document**, not by phase: one worker owns exactly one file, so all W2 tasks run in parallel with zero write conflicts.

```text
W0  owner decision (git tracking)            — human, blocks only final commit
W1  renames/renumber                         — 1 agent, mechanical, ~5 min
W2  per-document rework                      — 11 agents, FULLY PARALLEL
    W2-doc-01 … W2-doc-09, W2-doc-10 (new), W2-doc-11
W3  finalize: index, ID registry, verify     — 1 agent, after ALL of W2
```

Dispatch rules:

1. Give each agent exactly one brief path (`tasks/W?-*.md`). The brief lists its allowed reads and its single write target.
2. W1 must complete before any W2 brief starts (briefs reference new filenames).
3. W3 must start only after every W2 brief is done.
4. Every brief ends with a Definition-of-Done checklist; the worker reports it back.

## 2. Status board

| Task | Wave | Target file | Status |
|---|---|---|---|
| `tasks/W0-git-tracking.md` | W0 | `docs/tasks/.gitignore` | done — owner chose **option B** (local-only, no git change) |
| `tasks/W1-renames.md` | W1 | 5 renames + 1 renumber | done |
| `tasks/W2-doc-01.md` | W2 | `01-architecture-and-baseline.md` | done |
| `tasks/W2-doc-02.md` | W2 | `02-developer-tooling.md` | done |
| `tasks/W2-doc-03.md` | W2 | `03-security-and-supply-chain.md` | done |
| `tasks/W2-doc-04.md` | W2 | `04-qa-and-testing.md` | done |
| `tasks/W2-doc-05.md` | W2 | `05-ci-cd-and-release.md` | done |
| `tasks/W2-doc-06.md` | W2 | `06-runtime-operations.md` | done |
| `tasks/W2-doc-07.md` | W2 | `07-resilience-and-disaster-recovery.md` | done |
| `tasks/W2-doc-08.md` | W2 | `08-developer-experience-and-ai.md` | done |
| `tasks/W2-doc-09.md` | W2 | `09-observability-and-performance.md` | done |
| `tasks/W2-doc-10.md` | W2 | `10-telegram-platform-and-libraries.md` (new) | done |
| `tasks/W2-doc-11.md` | W2 | `11-implementation-and-rollout.md` | done |
| `tasks/W3-finalize.md` | W3 | `01` §33 + registry + verification | done — all 10 checks pass |

## 3. File map (result of W1)

| Current | New |
|---|---|
| `01-architecture-and-baseline.md` | unchanged |
| `02-developer-tooling.md` | unchanged |
| `03-security-and-supply-chain.md` | unchanged |
| `04-qa-and-testing.md` | unchanged (content re-scoped) |
| `05-performance-and-load.md` (CI/CD content) | `05-ci-cd-and-release.md` |
| `06-sre-and-observability.md` (runtime content) | `06-runtime-operations.md` |
| `07-resilience-and-disaster-recovery.md` | unchanged |
| `08-telegram-platform-and-libraries.md` (DX content) | `08-developer-experience-and-ai.md` |
| `09-github-ci-cd-and-release.md` (observability content) | `09-observability-and-performance.md` |
| — | `10-telegram-platform-and-libraries.md` (**new**) |
| `10-implementation-and-rollout.md` | `11-implementation-and-rollout.md` |

Specs are `01`–`10`; implementation is `11`.

## 4. Canonical contracts (single source of truth)

Authoritative copy lives here; briefs may duplicate values inline but must not redefine semantics.

**Exit codes** (defined in `02`): `0` success · `1` validation failure · `2` invalid command/configuration · `3` missing required environment/tool · `4` infrastructure/tool execution failure · `5` baseline/policy failure.

**Command flags** (defined in `02`): `--format=text|json|github` (alias `--json`) · levels `--quick|--full|--ci` · `--verbose` · `--quiet` · `--help`.

**Health endpoints** (defined in `06`): `/health/live` · `/health/ready` · `/health` (deep diagnostics, authenticated).

**Commit prefixes** (defined in `02`): `feat fix refactor perf test docs build ci chore security`.

**Invariant ID prefixes:** `INV-`(01) `DEV-`(02) `SP-`+`SEC-`(03) `QA-`(04) `CICD-`(05) `OPS-`(06) `DR-`(07) `DX-`(08) `OBS-`(09) `TG-`(10). Full registry is built in W3 into `01` appendix.

**Single-home topics:** graceful shutdown → `06`; correlation IDs → `09`; rollback → `05`; alert classes → `09`; commit conventions → `02`; health endpoints → `06`; backup/DR → `07`; testing strategy → `04`. Other docs reference, never re-specify.

**Metadata header template** (top of every doc):

```text
<NN> — <Title>
Status: Draft
Version: 0.2
Owner: platform-team
Updated: 2026-08-21
Depends on: <correct filenames>
Implementation: 11-implementation-and-rollout.md
```

## 5. Findings index (audit traceability)

| ID | One-line finding | Handled in |
|---|---|---|
| F-01 | `docs/tasks/.gitignore` ignores the whole set — untracked | W0 |
| F-02 | Filenames ≠ content (05, 08, 09); missing Telegram/perf SDDs | W1, W2-doc-10 |
| F-03 | No real "Telegram platform" and "Performance/load" specs | W2-doc-10, W2-doc-09 |
| F-04 | Exit codes contradict (02 vs 08) | §4, W2-doc-02, W2-doc-08 |
| F-05 | `--format=json` vs `--json` contradiction | §4, W2-doc-02, W2-doc-08 |
| F-06 | Invariant ID collisions (SRE in 04+09; SEC in 03) | §4, W2-doc-03/04/09 |
| F-07 | Broken refs: depends-on, `./security/check`, `install-hooks` | W2-doc-05/04/11 |
| F-08 | Docs 03–10 mostly Russian (rule: English) | all W2 |
| F-09 | Markdown structure damaged in 02–10 | all W2 |
| F-10 | 04 bloated into mini-SRE, duplicates 05–09 | W2-doc-04 |
| F-11 | Health paths defined 3 ways | §4, W2-doc-06 |
| F-12 | Cross-cutting topics specified in many docs | §4 single-home, all W2 |
| F-13 | 02 ↔ 08 ~60% duplication | W2-doc-08 |
| F-14 | RabbitMQ not in stack (Redis + Postgres) | W2-doc-04 |
| F-15 | Publishing ignores Composer path-repo workflow | W2-doc-05 |
| F-16 | Exception vs REQUIRED precedence unclear | W2-doc-01 |
| F-17 | No LF/CRLF control despite WSL incidents | W2-doc-02 |
| F-18 | No GitHub Actions cost guard | W2-doc-05 |
| F-19 | Shallow Telegram secret controls (tg_bots tokens etc.) | W2-doc-10, W2-doc-03 |
| F-20 | No metadata / reading order / traceability / glossary links | §4 template, W2-doc-01, W3 |

## 6. Global acceptance criteria

- Filenames match titles; `01` §33 lists the real set; zero stale references.
- Each contract has exactly one definition site; no duplicate invariant IDs; registry complete in `01` appendix.
- `04` contains only QA/testing; runtime/telemetry live in `06`/`09`.
- `10-telegram-platform-and-libraries.md` exists with profiles + token-leak controls.
- LF/CRLF control and Actions cost guard specified.
- All docs English, valid Markdown, metadata headers present.
- Set tracked in git (per W0 decision).

Verification commands are in `tasks/W3-finalize.md`.
