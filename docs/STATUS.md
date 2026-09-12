# Project Status — Compact Summary

> Updated 2026-09-12. All tests passing.

## Platform Modules

| Module | Package | Status | Tests | Notes |
|---|---|---|---|---|
| Module Engine | `telegram-platform-module` | ✅ 99% | — | Registry, activation, routing, capabilities, diagnostics, settings contracts, contribution system. Side-channels eliminated. |
| Multi-bot Management | `telegram-platform-management` | ✅ 80% | 8 | Models, commands, webhook routing, settings web renderer, i18n (5 locales), inline access enforcement, domain events |
| Menu Hub | `telegram-platform-menu` | ✅ 98% | — | Full Telegram Mini App: auth, chats, roles, assembler, SchemaForm, chunk loader, frontend SPA. i18n 5 locales. |
| Access Control | `telegram-platform-access` | ✅ 90% | 62 | Domain DTOs + InMemory + DatabaseAccessControl. GrantAccess/DenyAccess/RevokeAccess + events. GrantRepositoryContract + DB impl. |
| Audit | `telegram-platform-audit` | ✅ 85% | 65 | Domain DTOs, DB sink/query, prune command, correlation middleware, cross-module event listeners, admin controller, AuditRecording trait. |
| Antispam | `tgbot-module-antispam` | ✅ 100% | — | AI, captcha, commands, counters, enforcement, rules, strikes, violations, web, appeals. i18n 5 locales. |
| Summarizer | `tgbot-module-summarizer` | ✅ 100% | — | LLM digests, in-chat admin panel, cron, settings, web UI. i18n 5 locales. |
| TTS | `tgbot-module-tts` | ✅ 100% | — | /voice command, private auto-speak, provider presets, SSRF guard, cron prune. i18n 5 locales. |
| Nettools | `tgbot-module-nettools` | ✅ 100% | 205 | 19 user commands, /portscan /dnsbl admin-gated, target memory, MCP probe. i18n 5 locales. |
| Proxy | `tgbot-module-proxy` | 🟡 65% | 122 | Domain (90 files), Transport (44), Audit (41), Models (25), Parser, Encryption, Checker (13), Tool (22). Missing: Http controllers, i18n, Mini App. |
| Mafia Game | `tgbot-game-mafia` | 🟡 75% | 115 | Core + Redis + Eloquent + Notes + MessageTracker + DLQ + Metrics + Graceful shutdown. Phases 1-4 done. Missing: Phase 5 (Mini App). |

## Host Integration (`module_integration.md`)

| Phase | Status | What |
|---|---|---|
| Phase 0–1 | ✅ Done | Engine bootstrap takeover, contracts shipped |
| Phase 2 | ✅ Done | Proxy onboarding, audit streams consumer, frontend side-channels |
| Phase 3 | ✅ Done | Nettools migration fix, legacy placeholders, route loading cleanup |
| Phase 4 | ✅ Done (except 4.4) | Enablement driver flip, seeder migration, activation reader test |
| Phase 5 | ✅ Done | Manifest-driven page generation, side-channel elimination |
| Phase 6 | ✅ Done (except 6.2) | Docker/CI cleanup, dynamic inertia config |

## DevOps

| Section | Status | Notes |
|---|---|---|
| §1 Publication chain | 🟢 ~97% | 3/5 CI green. SBOM + 25 test failures remain |
| §2 GitHub hardening | 🔴 0% | Needs manual GitHub settings access |
| §3 Gate promotions | 🟡 ~40% | Coverage floor exists, mutation floor not set |
| §4 Baseline Phase 7 | 🔴 ~10% | Only cmd/lib audit done |
| §5 Blocked items | 🔴 0% | Kernel telemetry, test debt, infra — external triggers |

## What's Left (Priority Order)

1. **Mafia Phase 5**: Mini App (Game Board, Night Action UI, Vote UI, Spectator Mode)
2. **Proxy HTTP controllers**: Inertia page controllers, web admin CRUD (dashboard, inventory, pools, settings)
3. **Proxy i18n**: 5 languages (RU, EN, FR, ES, ZH)
4. **Proxy Mini App**: @telegram-apps/sdk-react shell, initData auth, MainButton
5. **Engine 6.2**: CI workflow for module validation
6. **DevOps §2**: GitHub hardening (manual)
