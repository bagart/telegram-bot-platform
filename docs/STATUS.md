# Project Status — Compact Summary

> Updated 2026-09-12.

## Platform Modules

| Module | Package | Status | Notes |
|---|---|---|---|
| Module Engine | `telegram-platform-module` | ✅ 99% | Registry, activation, routing, capabilities, diagnostics, settings contracts, contribution system. Side-channels eliminated. |
| Multi-bot Management | `telegram-platform-management` | ✅ 75% | Models, commands, webhook routing, settings web renderer, i18n (5 locales), inline access enforcement |
| Menu Hub | `telegram-platform-menu` | ✅ 98% | Full Telegram Mini App: auth, chats, roles, assembler, SchemaForm, chunk loader, frontend SPA. i18n 5 locales. |
| Access Control | `telegram-platform-access` | ✅ 90% | Domain DTOs + InMemory + DatabaseAccessControl. GrantAccess/DenyAccess/RevokeAccess + events. GrantRepositoryContract + DB impl. 62 tests. |
| Audit | `telegram-platform-audit` | ✅ 65% | Domain DTOs, DB sink/query, prune command, correlation middleware, access events listener, module lifecycle listener, schedule. 53 tests. |
| Antispam | `tgbot-module-antispam` | ✅ Complete | AI, captcha, commands, counters, enforcement, rules, strikes, violations, web, appeals. i18n 5 locales. |
| Summarizer | `tgbot-module-summarizer` | ✅ Complete | LLM digests, in-chat admin panel, cron, settings, web UI. i18n 5 locales. |
| TTS | `tgbot-module-tts` | ✅ Complete | /voice command, private auto-speak, provider presets, SSRF guard, cron prune. i18n 5 locales. |
| Nettools | `tgbot-module-nettools` | ✅ MVP complete | 19 user commands, /portscan /dnsbl admin-gated, target memory, MCP probe. i18n 5 locales. |
| Proxy | `tgbot-module-proxy` | 🟡 20% | Domain model, parser, encryption, audit pipeline, checker, transport adapters, ProbeTools. ~75/94 plan tasks remaining. |
| Mafia Game | `tgbot-game-mafia` | 🟡 25% | Core scaffold: game logic, rooms, bots, i18n (5 locales). API-first redesign in progress (Phases 1-3 done, 4-5 pending). |

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

1. **Audit Phase 5**: Query/Read API (admin controller)
2. **Audit Phase 6**: Cross-module integration (management, menu, proxy)
3. **Engine 6.2**: CI workflow for module validation (no `.github/workflows/`)
4. **Mafia Phase 4–5**: DLQ, metrics, graceful shutdown, Mini App
5. **Proxy**: ~75 remaining tasks across parser, transport, checker, export, API
6. **DevOps §2**: GitHub hardening (manual)
