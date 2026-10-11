# Project Status — Compact Summary

> Updated 2026-10-11. **Batch push executed 2026-10-11** (all 21 nested repos + host `261db42`, Q10 closed); **W-Q2 flip done** (callback rule blocking, task file deleted — `docs/tasks/` holds only B1); **both locks refreshed** (`cmd/deps/check` EXIT 0 — `lock-tip-fresh:prod` green). CI fixes shipped same day: semgrep dynamic-require (AdoptRepository allowlist), eslint `import/internal-regex` (wayfinder-generated outputs), CI `TgModuleConfig` stub + pin `a22dacb`. Full `composer test` green: root **5338 passed**, 0 failed (all 16 package suites). Execution wave 1 (2026-10-08/09) closed: 13 task files executed — audit seam (W-D3, 108 dispatch sites), settings Channel-B retirement (W-D2/B2/B3a), web activation surface (W-D4), adoption tooling (W-D1), callback namespacing (W-Q2), proxy Mini App restore (W-Q3), proxy quota enforcement (B3), catalog key convention (B1), antispam/nettools report ownership (B4/B4b), addBot dead path (B5), prod-lock tip-freshness control (`lock-tip-fresh:prod` — first real catch: stale `bagart/async-kernel`). SDD compressed (15 files), 12 task files deleted; 1 remains (B1 post-merge verify — needs deploy + prod logs). **Remaining structural CI reds (pre-existing): Tests workflow (never green in 60 runs — stub strategy vs real Feature suite), prod-install `provider-wiring` in no-misc CI, Docker GHCR org package permission.**

## Platform Modules

| Module | Package | Status | Tests | Notes |
|---|---|---|---|---|
| Module Engine | `telegram-platform-module` | ✅ 99% | — | Registry, activation, routing, capabilities, diagnostics, settings contracts, contribution system. CI workflow. |
| Multi-bot Management | `telegram-platform-management` | ✅ 80% | 8 | Models, commands, webhook routing, settings web renderer, i18n (5 locales), inline access enforcement, domain events |
| Menu Hub | `telegram-platform-menu` | ✅ 98% | — | Full Telegram Mini App: auth, chats, roles, assembler, SchemaForm, chunk loader, frontend SPA. i18n 5 locales. |
| Access Control | `telegram-platform-access` | ✅ 90% | 62 | Domain DTOs + InMemory + DatabaseAccessControl. GrantAccess/DenyAccess/RevokeAccess + events. GrantRepositoryContract + DB impl. |
| Audit | `telegram-platform-audit` | ✅ 90% | 68 | Domain DTOs, DB sink/query, prune command, correlation middleware, cross-module event listeners, admin controller, AuditRecording trait. AuditHealthProbe + AuditMetricsCollector. |
| Antispam | `tgbot-module-antispam` | ✅ 100% | — | AI, captcha, commands, counters, enforcement, rules, strikes, violations, web, appeals. i18n 5 locales. |
| Summarizer | `tgbot-module-summarizer` | ✅ 100% | — | LLM digests, in-chat admin panel, cron, settings, web UI. i18n 5 locales. |
| TTS | `tgbot-module-tts` | ✅ 100% | — | /voice command, private auto-speak, provider presets, SSRF guard, cron prune. i18n 5 locales. |
| Nettools | `tgbot-module-nettools` | ✅ 100% | 205 | 19 user commands, /portscan /dnsbl admin-gated, target memory, MCP probe. i18n 5 locales. |
| Proxy | `tgbot-module-proxy` | ✅ 100% | 1152 | Full lifecycle: import, audit, health, pools, lease, export (7 formats). Bot wizards, feed sync, backup/PITR, SLO benchmark, health endpoints, incident engine, decision log, gateway API. 5 langs, 31 migrations. |
| Mafia Game | `tgbot-game-mafia` | ✅ 95% | 115 | Core + Redis + Eloquent + Notes + MessageTracker + DLQ + Metrics + Graceful shutdown + Mini App (game board, night/vote UI, spectator). All phases done. |

## Host Integration (`module_integration.md`)

| Phase | Status | What |
|---|---|---|
| Phase 0–1 | ✅ Done | Engine bootstrap takeover, contracts shipped |
| Phase 2 | ✅ Done | Proxy onboarding, audit streams consumer, frontend side-channels |
| Phase 3 | ✅ Done | Nettools migration fix, legacy placeholders, route loading cleanup |
| Phase 4 | ✅ Done (except 4.4) | Enablement driver flip, seeder migration, activation reader test |
| Phase 5 | ✅ Done | Manifest-driven page generation, side-channel elimination |
| Phase 6 | ✅ Done | Docker/CI cleanup, dynamic inertia config, CI workflow |

## DevOps

| Section | Status | Notes |
|---|---|---|
| §1 Publication chain | 🟢 ~97% | 3/5 CI green; composer-SBOM deferred — image SBOM (workspace scan + buildkit attestation) covers release artifact (`docs/adr/ADR-002-prod-lock-nightly-and-repo-state.md` decision) |
| §2 GitHub hardening | ✅ 100% | CODEOWNERS ✅, SECURITY.md ✅, ISSUE_TEMPLATE ✅, workflows permissions+SHA+concurrency ✅, dependabot ✅, labeler ✅ |
| §3 Gate promotions | ✅ 100% | Mutation gate **enforced** for `bagart/async-kernel` (floor 97, measured 100.00%, direct control + `--baseline`), hooksPath repaired, 4 dead shims deleted; nightly CI redesigned to vendor-install and **implemented** (Q10–Q17 resolved: full `composer.prod.json` + `--dev` install + `cmd/ci/rewrite-vendor-paths.php` + stubs removed + APP_KEY/example job env; e2e worktree sim green); **Q15-A batch executed 2026-10-02** (nested repos pushed, ask-queue published + strip→map in rewrite); **promotion COMPLETE 2026-10-03** — 3 consecutive green all-blocking nightly runs (37114626742, 37115186051, 37115739626): coverage **blocking at floor 30** (measured 31.8% pcov / 32.1% xdebug, `docs/adr/ADR-003-baseline-and-quality-gates.md`), mutation **blocking at floor 97** (924 mutations, 100.00%), `continue-on-error` dropped on both jobs; job fixes: `pcov.directory=.`, coverage redis + asset build, phpunit `<source>` → `vendor/bagart` rewrite, mutation `--path`; pin `c13903f`; SDD `docs/sdd/mutation-gate-nightly.md` |
| §4 Baseline Phase 7 | 🟡 ~40% | Module discovery probe passes (9/9), line-endings + secret-scan pass; new `lock-tip-fresh:prod` control active (nightly tip-vs-lock, `docs/adr/ADR-002-prod-lock-nightly-and-repo-state.md`) and currently red on stale `bagart/async-kernel` lock — refresh pending (user); shim retirement deferred (`docs/adr/ADR-003-baseline-and-quality-gates.md`) |

## What's Left

1. ~~**Prod lock refresh**~~ **DONE 2026-10-11** (latest): `cmd/deps/update --mode=both` refreshed both locks after the nested batch-push — `lock-fresh:dev` + `lock-fresh:prod` + `lock-tip-fresh:prod` all green (`cmd/deps/check` EXIT 0). Note: the prod leg needs GitHub auth — export `COMPOSER_AUTH` from `gh auth token` when running it locally.
2. **Branch protection**: Manual GitHub repo settings (cannot be automated via code)
3. **B1 post-merge verification (user-owned)**: deploy + prod `[menu.refine]` log traffic → observable effect → delete `docs/tasks/b1-catalog-key-convention.md` (sole remaining task file). W-Q2 flip + nested batch-push + lock refresh CLOSED 2026-10-11
4. **Structural CI reds (pre-existing, need design/owner)**: Tests workflow (never green in 60 runs — stub strategy cannot run the real Feature suite; needs nightly-style vendor-install redesign), prod-install `provider-wiring` unmappable in no-misc CI, Docker GHCR push denied on org package creation (org setting)
