# Project Status — Compact Summary

> Updated 2026-10-02. Full `composer test` green: root 5202 passed + all 16 package suites (EXIT 0). Q15-A batch executed: 19 nested repos committed+pushed, `bagart/ask-queue` published & wired (dev path repo + PSR-4, prod VCS + require, both locks fresh), workflows repo pushed + host SHA pin bumped.

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
| §1 Publication chain | 🟢 ~97% | 3/5 CI green; composer-SBOM deferred — image SBOM (workspace scan + buildkit attestation) covers release artifact (`docs/questions/composer-sbom.md` decision) |
| §2 GitHub hardening | ✅ 100% | CODEOWNERS ✅, SECURITY.md ✅, ISSUE_TEMPLATE ✅, workflows permissions+SHA+concurrency ✅, dependabot ✅, labeler ✅ |
| §3 Gate promotions | ✅ ~95% | Mutation gate **enforced** for `bagart/async-kernel` (floor 97, measured 100.00%, direct control + `--baseline`), hooksPath repaired, 4 dead shims deleted; nightly CI redesigned to vendor-install and **implemented** (Q10–Q17 resolved: full `composer.prod.json` + `--dev` install + `cmd/ci/rewrite-vendor-paths.php` + stubs removed + APP_KEY/example job env; e2e worktree sim green); **Q15-A batch executed 2026-10-02** (nested repos pushed, ask-queue published + strip→map in rewrite, host SHA pin `a46e420`) — **remaining: 3 green nightly runs → `continue-on-error` flip → coverage `--min=55`; SDD `docs/sdd/mutation-gate-nightly.md`** |
| §4 Baseline Phase 7 | 🟡 ~30% | Module discovery probe passes (9/9), line-endings + secret-scan pass, prod lock needs refresh; shim retirement deferred (`docs/questions/baseline-phase7-shim-retirement.md`) |

## What's Left

1. ~~**Prod lock refresh**~~ **DONE 2026-10-02**: `cmd/deps/update --mode=both` refreshed both locks — `lock-fresh:dev` + `lock-fresh:prod` green (`cmd/deps/check` EXIT 0)
2. **Branch protection**: Manual GitHub repo settings (cannot be automated via code)
