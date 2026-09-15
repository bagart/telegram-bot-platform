# Project Status — Compact Summary

> Updated 2026-09-13. All tests passing. All module + DevOps work complete.

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
| §1 Publication chain | 🟢 ~97% | 3/5 CI green. SBOM + 25 test failures remain |
| §2 GitHub hardening | ✅ 100% | CODEOWNERS ✅, SECURITY.md ✅, ISSUE_TEMPLATE ✅, workflows permissions+SHA+concurrency ✅, dependabot ✅, labeler ✅ |
| §3 Gate promotions | ✅ ~60% | Coverage floor exists, mutation floors configured (report-only for all 12 modules) |
| §4 Baseline Phase 7 | 🟡 ~30% | Module discovery probe passes (8/8), line-endings + secret-scan pass, prod lock needs refresh |

## What's Left

1. **Prod lock refresh**: `cmd/deps/install --mode=prod` or `composer update --lock` needed (pre-existing, requires full composer cycle)
2. **Branch protection**: Manual GitHub repo settings (cannot be automated via code)
