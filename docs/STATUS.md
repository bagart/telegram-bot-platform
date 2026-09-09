# Project Status — Compact Summary

> Auto-generated 2026-09-09. Replaces all `docs/tasks/` files.

## Platform Modules

| Module | Package | Status | Notes |
|---|---|---|---|
| Module Engine | `telegram-platform-module` | ✅ Phases 0–6 | Registry, activation, routing, capabilities, diagnostics, settings contracts, contribution system |
| Multi-bot Management | `telegram-platform-management` | ✅ Core done | Models, commands, webhook routing, settings web renderer (generic Inertia form) |
| Menu Hub | `telegram-platform-menu` | ✅ All 25 tasks | Full Telegram Mini App: auth, chats, roles, assembler, SchemaForm, chunk loader, frontend SPA |
| Access Control | `telegram-platform-access` | 🟡 DTOs only | Domain DTOs + InMemory impl. GrantEffect, SubjectContext, persistent storage, CLI, processor integration — pending |
| Audit | `telegram-platform-audit` | ✅ Contracts done | AuditEntry DTO, AuditSinkContract, InMemoryAuditSink, DatabaseAuditSink, migration, prune command |
| Antispam | `tgbot-module-antispam` | ✅ Complete | AI, captcha, commands, counters, enforcement, rules, strikes, violations, web, appeals |
| Summarizer | `tgbot-module-summarizer` | ✅ Complete | LLM digests, in-chat admin panel, cron, settings, web UI |
| TTS | `tgbot-module-tts` | ✅ Complete | /voice command, private auto-speak, provider presets, SSRF guard, cron prune |
| Nettools | `tgbot-module-nettools` | ✅ MVP complete | 19 user commands, /portscan /dnsbl admin-gated, target memory, MCP probe |
| Proxy | `tgbot-module-proxy` | 🟡 Skeleton | Parser, checker, domain, encryption, models, tenancy — no TgModuleContract onboarding yet |
| Mafia Game | `tgbot-game-mafia` | 🟡 Core scaffold | GameSnapshot, NightResolver, RoleCatalog, rooms, bots, presenters, processors — API-first redesign pending (80+ tasks) |

## Host Integration (`module_integration.md`)

| Phase | Status | What |
|---|---|---|
| Phase 0–1 | ✅ Done | Engine bootstrap takeover, contracts shipped |
| Phase 2 | 🔴 Pending | Proxy onboarding, audit streams consumer, frontend side-channels |
| Phase 3 | 🔴 Pending | Nettools migration fix, legacy placeholders, route loading cleanup |
| Phase 4 | 🔴 Pending | Enablement driver flip `legacy → engine`, seeder migration |
| Phase 5 | 🔴 Pending | Manifest-driven page generation, delete host module pages |
| Phase 6 | 🔴 Pending | Docker/CI cleanup, doctor in CI, zero module refs outside config |

## DevOps (`devops3.md`)

| Section | Status | Notes |
|---|---|---|
| §1 Publication chain | 🟢 ~97% | Workflows repo tagged v0.1.0, 3/5 CI green. SBOM + 25 test failures remain |
| §2 GitHub hardening | 🔴 0% | Needs manual GitHub settings access |
| §3 Gate promotions | 🟡 ~40% | Coverage floor exists, mutation floor not set |
| §4 Baseline Phase 7 | 🔴 ~10% | Only cmd/lib audit done |
| §5 Blocked items | 🔴 0% | Kernel telemetry, test debt, infra — external triggers |

## Web Settings Renderer (Management)

- `SettingsScreenController` — index (bot selector + screen list), show (dynamic form), update (save + mtime concurrency)
- Routes: `GET/PUT /modules/settings[/{botId}/{screenId}]`
- React pages: `settings/modules/index.tsx`, `settings/modules/show.tsx`
- Maps `SettingsFieldType` → React controls (int/float/string/text/bool/enum)
- **No module has `settingsScreens` configured yet** — nothing to configure until modules register screens

## Key Architecture Decisions

- **Storage model**: Settings = config files (JSON/YML). DB = runtime data only
- **Module registration**: `config/tg_modules.php` → engine resolves → boot in dependency order
- **Settings contract**: `SettingsDescriptor` (typed PHP-DTOs) → `ConfigFileAccessContract` (JSON read/write)
- **Contribution system**: `MenuContributionResource` + `SettingsScreenContribution` → `ContributionResolver` → `MenuAssembler`
- **Access filtering**: `AccessControlContract::decide()` applied before rendering

## What's Left (Priority Order)

1. **Management**: Access-level enforcement, WebScreenBinding escape hatch, i18n (5 languages)
2. **Access Module Phase 1**: GrantEffect enum, SubjectContext, ActorContext, domain events
3. **Module Integration Phase 2–6**: Proxy onboarding, frontend decoupling, enablement flip
4. **DevOps §2**: GitHub hardening (manual)
5. **Mafia API-first redesign**: 80+ tasks across 12 phases (massive, long-term)
