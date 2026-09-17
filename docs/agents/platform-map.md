# Platform Map — Services, Logic, Views (index)

> Companion to platform-prompt.md §0. One-line-per-service index; deep-dive only on demand. Verified against code 2026-09-17 (bootstrap/providers.php, config/tg_modules.php, routes/web.php).

## Services (packages → responsibility)

| Package (misc/BAGArt/) | Namespace | Owns |
|---|---|---|
| php-async-kernel-lib | BAGArt\AsyncKernel | Fiber scheduler, daemon lifecycle, shutdown phases, tickable/warmable contracts |
| php-async-kernel-client(-redis) | BAGArt\ASKClient(Redis) | HTTP transports, lockers, queues, DLQ, Redis primitives |
| telegram-bot-lib | BAGArt\TelegramBot | ~450 TgApi DTOs, API caller, outbound pipeline, TgModuleContract, registries |
| telegram-bot-lib-basic | BAGArt\TelegramBotBasic | CLI daemons: poller, chatting, webhook |
| telegram-platform-management | BAGArt\TelegramBotManagement | tg_bots models, webhook HTTP layer, tgbm:* CLI, tg-ops MCP |
| telegram-platform-module | BAGArt\TelegramModuleEngine | Module engine: registry, activation, routing, schedule, settings, diagnostics |
| telegram-platform-access / -audit | BAGArt\TelegramBotAccess / Audit | authz contracts / append-only audit sink |
| telegram-platform-menu | BAGArt\TelegramBotMenu | Web Menu hub, Mini App SPA, menu:* CLI |
| tgbot-module-* (antispam, summarizer, stt, tts, nettools, proxy) | BAGArt\... | TgModuleContract plugins (feature modules) |
| tgbot-game-mafia | BAGArt\TelegramBotMafia | Mafia game module |
| telegram-platform-devops-baseline | bagart/...-baseline | DevOps engine: controls, hooks, cmd/* shims |

## Logic flows (where behavior lives)

| Flow | Path | Anchor files |
|---|---|---|
| Inbound update | webhook → middleware → lib processing | routes/web.php:24-39; lib Http/Laravel, Processing/ |
| Module boot | engine bootstrap in dependency order | bootstrap/providers.php:6-21; config/tg_modules.php; engine Registry/, Activation/ |
| Outbound send | OutboundTask → queue → middleware → executor → DLQ | lib Outbound/; skills outbound-pipeline-development |
| Enablement/settings | chat→bot→platform inheritance | lib Modules/ contracts; engine Settings/, Tenancy/ |
| Bot commands | RouteDeclaration table → flat registry → processor | config/tg_modules.php routes; lib Module registries |
| Cron | TgModuleSchedule entries → schedule-overrides | engine Schedule/ |
| Web/Mini App | Inertia pages, menu tgapp routes, module frontendPages | telegram-platform-menu routes/tgapp.php; module resources/js/pages |

## Views (UI surfaces)

| Surface | Location | Generator |
|---|---|---|
| Web admin (Inertia+React) | resources/js/pages (host) + module frontendPages | npm run build; menu:pages generators |
| Mini App | telegram-platform-menu routes/tgapp.php + SPA | @telegram-apps/sdk feature detection |
| Bot chat UI | module processors + in-chat panels | processors per RouteDeclaration |
| Ops UI | deploy/monitoring (Prometheus/Grafana) | runbooks/alert-*.md |

## Pointer table (branch → file)

| If working on… | Open |
|---|---|
| Daemon/kernel code | .agents/skills/async-kernel-development |
| Queue/middleware/DLQ | .agents/skills/outbound-pipeline-development |
| Webhooks/TgBot models | .agents/skills/multi-bot-management |
| Reliability review | .agents/skills/highload-stability |
| Telegram API DTOs | .agents/skills/telegram-dto-generation |
| React/Inertia pages | .agents/skills/inertia-react-development |
| Package internals (any lib/module) | `<pkg>/docs/INDEX.md` → `SDD-*.md` (17 packages indexed, code-grounded 2026-09-17) |
| Term ambiguity | docs/glossary.md |
| Status/roadmap | dev-ai.md, docs/STATUS.md |
