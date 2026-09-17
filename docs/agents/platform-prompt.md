doc1
# Platform Agent System Prompt (Telegram Bot Platform)

> Purpose: compact, fully local operating contract for any AI agent working in this repo. No external docs/wikis — everything resolves inside the repository. Complements AGENTS.md; on conflict AGENTS.md wins.

## 0. Read order (always, in this order)

1. dev-ai.md — current state, roadmap, workflow law
2. AGENTS.md — conventions (Boost block + project tail)
3. docs/INDEX.md — navigation map
4. docs/glossary.md — term disambiguation (ASK, DLQ, lease…)
Stop exploring once the task is located; do not bulk-read sources.

## 1. Task lifecycle (strict, every change)

```
DELTA → PLAN → IMPLEMENT → VERIFY → SDD → DOCS → CLEANUP
```

- DELTA: git status/diff before any write. No code without a task file.
- PLAN: <module>/docs/tasks/<topic>.md — UNDONE items only.
- VERIFY: run the touched module tests first (module composer test), then host.
- SDD: compress immediately after implementation (declarative, no code) into SDD-<topic>.md; mandatory trace.
- CLEANUP: completed task files are deleted; a 100%-done file must not exist.
- Footer every reply: [platform|<module>] <task> — <N>% | <phase>

## 2. Package topology (dev vs prod)

- Host composer.json maps BAGArt\* namespaces PSR-4 straight into misc/BAGArt/<pkg>/src (dev mode, symlinks via path repos). misc/ is a dev checkout for exactly the package under work — never assume it exists in production.
- Prod: composer.prod.json requires versioned bagart/* from VCS; servers have no misc/. Install: cmd/deps/install --mode=prod. Checks: cmd/deps/check.
- Consequence: any new module needs (a) PSR-4 in host composer autoload, (b) version bump + composer.prod.json entry, (c) baseline regen. Non-bagart deps must match across both manifests.
- Never run composer/git write ops inside misc/* nested repos; host repo rules apply.

## 3. Module system (how code reaches runtime)

- Feature modules implement TgModuleContract: static descriptor() (pure metadata) + register(TgModuleRegistrar) (idempotent component declarations).
- Engine (telegram-platform-module) is the single bootstrap authority: config/tg_modules.php declares TgModuleConfig per module id (enabled, provider, laravelProvider, seeders, commands, schedule, routes, httpRoutes, routeMiddleware, frontendPages, pageGenerators, settingsScreens). strict=false collects errors; enablement_driver=engine routes activation through bot_module_activations.
- bootstrap/providers.php holds ONLY non-module providers: Bot lib, Engine, Basic, Management, ProxyOperations, Audit, Access + app/fortify. A module's Laravel provider is never listed there; declaring it in tg_modules.php is the wiring.
- Bot-scoped activation: platform enabled=true ≠ per-bot enabled; chat→bot→platform inheritance chain resolves enablement and settings (fail policy per descriptor).

## 4. Runtime surfaces (fixed entrypoints)

- Webhooks (host routes/web.php, prefix /tg): POST /tg/ (secret-header token) and POST /tg/tg_webhook/{bot_id} (DB token); both behind TgIpValidatorMiddleware + TgSecretValidatorMiddleware (+ TgBotIdResolverMiddleware). Tokens/secrets live in tg_bots table, never .env.
- Health: /health/live, /health/ready, /health, /health/metrics (host HealthController).
- Outbound: daemons are built explicitly via new TgOutboundDaemon(...) inside CLI commands (tgbm:outbound-daemon et al.); factory createOutboundDaemonParts() supplies shared parts. Redis is state-only (readonly DTOs); no behavior objects.
- Diagnostics: tg:modules:list|validate|diagnose; tgbm:monitor; MCP server tg-ops (tgbm:mcp).

## 5. Hard rules (non-negotiable)

- Strict contracts across library borders: no method_exists/instanceof duck-typing; capability checks only via declared *Contract interfaces.
- DTOs: final readonly, constructor-promoted, SCHEMA_VERSION + fromJsonV1() for persisted state; enums for enumerations.
- Config over env: settings in config/*.php (+ readonly DTOs); env only for secrets/DSN. Module settings runtime overrides via SettingsStorageContract, canonical values stay in config files.
- Storage split: config files = settings; DB = runtime data (audit, history, activation). Redis = ephemeral runtime only.
- Multi-tenant: tenant_id mandatory in domain tables/queries (proxy module law).
- Secrets: never logged, masked by default, encrypted at rest; Telegram bot tokens in DB.
- Lazy connections: constructors never open Redis/TCP; ASKWarmableContract::warm() is the hook. SKInterruptException always bubbles; never catch in middleware.
- LF endings only; PHP 8.5 strict_types; Pint on touched files; English in all code/comments.

## 6. Verification ladder (cheapest sufficient check)

1. Touched module suite: cd <module> && vendor/bin/pest --testsuite <Suite> (or composer test).
2. Host chain only for cross-package work: composer test / php artisan test --compact.
3. Static: vendor/bin/pint --dirty --format agent (PHP), npm run lint:check + types:check (TS touched).
4. cmd/dev/check for full gate before delivery; never bypass hooks.
No verification scripts/tinker where tests exist.

## 7. Doc economy (anti-sprawl law)

- One meaning = one file. Single source of truth; link, never duplicate (AGENTS.md ← CLAUDE/GEMINI mirrors).
- Environments beat prose: do not restate composer scripts, route lists, config keys — read them from source at need.
- Docs in docs/agents/ + per-module docs/ only. Tasks are ephemeral; SDD is the durable trace. ADR only for irreversible, contested or costly-to-reverse decisions (one page: context/decision/consequences/status).
- Kill criteria: stale >2 cycles, duplicated elsewhere, or restates environment → delete or fold. Prefer editing the canonical file over adding a new one.

## 8. Resource discipline (context budget)

- Progressive disclosure: read the 4 anchors, then only the exact skill/rule file for the branch touched (see platform-map.md). Never scan whole modules.
- Skills carry domain depth (async-kernel, outbound-pipeline, multi-bot-management, highload-stability…): load the one matching the domain, not all.
- Prefer targeted grep over file reads; prefer schema/test evidence over re-derivation; one webless policy — everything resolves locally.
- Large refactors: PHPStorm structural tools first (phpstorm-workflow skill); agent verifies after.

## 9. Escalation & safety

- Ask only global questions (scope, product direction); execute silently when dev-ai.md roadmap is unambiguous.
- Dangerous ops (ops/restore|restart|replay|deploy|rollback) require explicit --confirm flags; no git write ops ever (user commits).
- Frontend build issues (Vite manifest) → suggest npm run build / composer run dev; do not debug blind.
- Never delete tests; never edit generated TgApi DTOs by hand (use actualize.sh; skill telegram-dto-generation).

## 10. Module playbook (adding a module, condensed)

1. Scaffold in misc/BAGArt/<name>-module/ (composer.json + phpunit.xml + composer test script; TgModuleContract).
2. Host wiring: PSR-4 autoload, path repo, config/tg_modules.php TgModuleConfig (key = descriptor id).
3. Declare routes/commands/schedule/httpRoutes/frontendPages in the config entry — not in providers.
4. Tests: module suite + host phpunit.xml testsuite entry + root composer test chain.
5. Prod path: bump version, add to composer.prod.json require, regen lock, cmd/deps/check, baseline regen.

## 11. Vocabulary anchors (short forms used everywhere)

ASK = async kernel umbrella; Tg = telegram domain prefix; Outbound = send pipeline; DLQ = dead letters; lease = visibility timeout; tickable/warmable/shutdown-aware = daemon hooks; enablement = per-bot activation row; descriptor = module metadata card. Full disambiguation: docs/glossary.md.

---
End of prompt. Operate from this file + the map; deepen only on demand.
