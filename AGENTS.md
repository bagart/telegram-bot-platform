<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.5
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v2
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/wayfinder (WAYFINDER) - v0
- larastan/larastan (LARASTAN) - v3
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- @inertiajs/react (INERTIA_REACT) - v2
- react (REACT) - v19
- tailwindcss (TAILWINDCSS) - v4
- @laravel/vite-plugin-wayfinder (WAYFINDER_VITE) - v0
- eslint (ESLINT) - v9
- prettier (PRETTIER) - v3

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.
- **NEVER run `git commit`, `git push`, or any other git write operation (`git add` included) — in any repository, including nested repos under `misc/BAGArt/*`. The user reviews all changes via `git diff` / `git status` themselves and commits manually. Make changes on disk only; leave the working tree for user review.**

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v2

- Use all Inertia features from v1 and v2. Check the documentation before making changes to ensure the correct approach.
- New features: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.
- Only run Pint on files you created or modified in the current task — never reformat untouched files (diff noise). When editing an existing file, fix style only in lines you are actively changing; leave unrelated style issues untouched.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

</laravel-boost-guidelines>

=== language rules ===

# Language Rules

- All code, comments, agent prompts, and documentation MUST be in English.
- Russian (or other languages) is ONLY allowed in:
  - `docs/_lang/ru/` — Russian translation of documentation
- Agent prompts must be in English. Communication with the LLM-developer can be in Russian.
- Comments must only explain non-obvious intent — never restate what the code already says.
- Do not use comments to describe "stages" or "steps" — split the method instead.
- All existing prompts, comments, and strings must be translated to English, with obvious ones removed.

=== project conventions ===

# Project Conventions

- When reading MD files, always append their content to the end of the conversation context.
- For the big picture of where docs/skills/code live, read `docs/INDEX.md`. For overloaded terms (ASK, DLQ, tickable, lease, etc.), read `docs/glossary.md`.
- **Skills canonical location:** edit custom skills in `.agents/skills/` only, then run `bash scripts/sync-skills.sh` to mirror the 6 BAGArt domain skills into `.claude/`, `.cursor/`, `.github/`, `.junie/skills/`. Run `bash scripts/sync-skills.sh --check` to verify they're in sync. Do not hand-edit the copies in those dirs.
- Development is primarily in `misc/`, avoid touching `app/` when possible.
- Telegram bot tokens are stored in DB (`tg_bots` table), not in `.env`.
- ALWAYS use LF line endings, never CRLF. Write all files with `\n` only. Generated code MUST be LF-only — this is enforced by `.gitattributes` (`* text=auto eol=lf`), which overrides any global `core.autocrlf=true`.
  - **Agent pitfalls (from real incidents):**
    - After ANY `git checkout` / `git stash apply` / `git show > file`, re-verify with `file <path>` or `grep -c $'\r' <path>` — on Windows W: drives CRLF can sneak in despite `.gitattributes`. Convert if needed.
    - When converting a file to LF programmatically, ALWAYS read fully into memory BEFORE opening for write. NEVER write `open(p,'wb').write(open(p,'rb').read()...)` — the write-open truncates the file before the nested read completes, destroying it.
    - `is_readable()` and PHP/Pint file writes are unreliable on the W: drive (WSL mount); prefer `is_file()` and expect Pint to be read-only here (use `pint --test` + manual fixes).
- Strict contracts only — no `method_exists`, `instanceof` duck-typing across library boundaries. If a caller needs a method, it MUST be declared in the interface/contract. Do not add dead methods; every public method must have a real caller.
- **Domain/config DTO style:** `final readonly` classes with explicitly typed, constructor-promoted properties and no setters; `JsonSerializable` + `SCHEMA_VERSION` constant + `fromJsonV1()` for anything persisted/serialized; enums for enumerations. Reference implementation: `misc/BAGArt/telegram-bot-lib/src/Outbound/DeadLetterEntry.php`.
- **Minimize env dependencies: everything in config files.** Settings live in `config/*.php` structures (and readonly config-DTOs built from them), not in environment variables. Env is reserved for secrets and connection points only (encryption keys, HMAC keys, DSN for Redis/Postgres). Config reads env once at the config layer; domain logic must use `config()` / injected DTOs and never call `getenv()` directly. Keep each module's env set minimal and documented in one place.

## Telegram Bot Platform Structure

- `misc/BAGArt/telegram-bot-lib` — pure Telegram Bot API library (no Laravel)
- `misc/BAGArt/telegram-bot-lib-basic` — basic webhook handlers, middleware, commands (works with pure PHP and Laravel)
- `misc/BAGArt/telegram-platform-management` — multi-bot management, models (`TgBot`, `TgBotOwner`, `TgModuleEnablement`), DB migrations
- `misc/BAGArt/tgbot-module-antispam` — anti-spam module (`TgModuleContract` plugin)
- `misc/BAGArt/tgbot-module-summarizer` — chat summarizer/digest module (`TgModuleContract` plugin; LLM digests + in-chat admin panel, cron via `summarizer:digests`)
- `misc/BAGArt/tgbot-module-tts` — text-to-speech module (`TgModuleContract` plugin; `/voice` command, private auto-speak, provider presets + custom JSON with SSRF guard, Track B multipart delivery through the core client, cron via `tts:prune`; docs: module `Readme.md`)
- `misc/BAGArt/tgbot-module-nettools` — nettools module (`TgModuleContract` plugin; auditor toolkit MVP shipped: 19 user commands + `/portscan` `/dnsbl` admin-gated, target memory, reco/report engines, MCP probe tool, circuit breakers; ops notes in its `Readme.md`)
- `misc/BAGArt/tgbot-game-mafia` — Mafia game module (`TgModuleContract` plugin; plan hub: `misc/BAGArt/tgbot-game-mafia/docs/tasks/mafia/index.md` — task registry; legacy master plan `todo.mafia.md` there is FROZEN, migration gated by `_refactor/migration-matrix.md`)
- `misc/BAGArt/tgbot-module-proxy` — Proxy Operations module (`BAGArt\ProxyOperations`; proxy inventory/quality/pools/gateway; bot + Telegram Mini App + web admin over one Application API)
- `misc/BAGArt/telegram-platform-module` — Telegram Module Engine (`BAGArt\TelegramModuleEngine`): declarative module registry from `config/tg_modules.php` (policy DTOs + `laravelProvider`), module boot + bootstrap takeover in dependency order, bot-scoped activation (PG `bot_module_activations`), PG routing table behind `RouteResolver`, capabilities/dependency graphs, diagnostics CLI `tg:modules:list|validate|diagnose`. Plan hub: `misc/BAGArt/telegram-platform-module/docs/architecture/` (00 overview + 07 roadmap with status).
- `misc/BAGArt/telegram-platform-access` — Chat Access Control module (`BAGArt\TelegramBotAccess`; pure-domain authz: `AccessControlContract`, `ChatRole`, `Grant`, `AccessDecision`; Platform Administration / Bot Administration / Chat Access Control terminology).
- `misc/BAGArt/telegram-platform-audit` — Audit module (`BAGArt\TelegramBotAudit`; append-only `AuditSinkContract`, `AuditEntry` DTO with `SCHEMA_VERSION`/`fromJsonV1`, in-memory sink for tests/hosts).

**Modules rule:** every Telegram platform module (feature/game plugin implementing `TgModuleContract`) is developed and stored in `misc/BAGArt/<name>-module/` together with the libs — never in a sibling directory outside the platform tree. The host consumes modules in one of two first-class modes:

- **dev mode** (default for development): root `composer.json` maps the module namespace PSR-4 directly into `misc/BAGArt/<name>-module/src` (+ tests via autoload-dev), keeps a path repository entry, and does **not** composer-require the package. Edits are immediately visible, no version bumps mid-refactor.
- **prod mode** (servers): `composer.prod.json` requires versioned `bagart/...-module` packages from VCS sources; install with `cmd/deps/install --mode=prod`. The prod lock must never reference path repositories or symlinked installs (servers ship without `misc/`).

Since the module-engine bootstrap takeover (phase 3), a module's Laravel provider is NOT listed in `bootstrap/providers.php` — it is declared as `laravelProvider` on the module's entry in `config/tg_modules.php` and registered by the engine in dependency order. `bootstrap/providers.php` holds only lib + engine + basic-lib + management + proxy-operations (non-`TgModuleContract` packages). The legacy `telegram.modules` / `modules_providers` / `modules_seeders` keys are retired from `config/telegram.php`; modules declare `seeders`/`routes` directly in `config/tg_modules.php` (the deprecated alias consumption in lib/engine remains for external consumers). Every module ships its own `phpunit.xml(.dist)` + a `composer test` script — self-testable inside the repo and root-launchable via a host `phpunit.xml` testsuite plus an entry in the root `composer test` chain (suites are Pest: run them with `vendor/bin/pest --testsuite <Suite>` / `artisan test`). `cmd/deps/check` enforces layout, wiring and manifest parity.

## Proxy Operations Module (tgbot-module-proxy)

Full plan: `misc/BAGArt/tgbot-module-proxy/docs/proxy-operations/plan.md` — read it before any work on the module.

Hard rules:

- **Multi-tenant everywhere.** `tenant_id` (workspace) is mandatory in ALL domain tables, queries, queues, caches; tenant resolution happens before any logic; server-side scoping is covered by tests. Model (decided 2026-08-26): tenant = platform user, 1 user = 1 workspace, Owner-only (role field reserved). No global records outside a tenant except system dictionaries and the shared raw-probe cache.
- **Scope: any proxy format EXCEPT VPN.** The parser rejects vless/vmess/trojan/ss/wireguard/openvpn with a clear error.
- **SSRF-safe checker:** fixed judges only; private/metadata denylist for IPv4+IPv6; resolve-then-connect (anti-DNS-rebinding). Reuse the `SsrfGuard` pattern from `tgbot-module-nettools`.
- **Secrets:** proxy credentials are never logged and never reach exceptions/Telegram output; masked by default; encrypted at rest.
- `Working ≠ Anonymous ≠ Safe ≠ Good` — orthogonal statuses/scores.
- Append-only observations, idempotency, tenant-scoped durable runtime — not "later".
- **Everything in Docker** (decided 2026-08-26): the module runs in the platform PHP container; any external program gets its own container; if an external program needs domain access — a simple proxy-API with a versioned additive-only contract instead of pulling it into PHP. Tor is a separate container (SOCKS5 :9050). Prefer third-party tools with web APIs.
- `verified_proxies` is a **projection, not a second model**: `ProxyEndpoint` is the single source of truth; one projector updates the projection on `AuditCompleted`; external containers are read-only.
- Admin settings are editable from both interfaces (web admin panel and Telegram `/settings` form) over one application layer, with audit.
- i18n: five languages from day one — RU, EN, FR, ES, ZH; all strings via keys.
- **MTProto proxies are a separate endpoint type** (not SOCKS): parse `mtproto://` / `tg://proxy` (hex/dd/+r/FakeTLS secrets), check via minimal handshake `req_pq_multi→resPQ`; SOCKS5/HTTP get `telegram_usable` via TCP+TLS to a Telegram DC. Details: plan.md §10.12 item 22.
- **List parser:** one grammar — the `ProxyOperations\Domain\Parsing` library; MVP is an internal application service (shared `ImportProxiesCommand` for bot/API/CLI/feeds); an HTTP parser-svc container is P2 once external consumers appear. The parser knows nothing about encryption. Details: plan.md §11.15 items 2–3.
- **Layer boundaries (plan.md §11 round 4 — win on conflicts):** the worker makes no domain decisions (`AuditTask` → probes → `AuditResult`, Redis only, no Postgres writes); Postgres = domain truth, Redis = runtime; raw probe observations are shared, tenant interpretation (health/score/lifecycle) is per-workspace; HMAC trust only for self-hosted judges; lifecycle = one state machine + orthogonal Testability/Quarantine statuses.

Stack: network layer only `bagart/php-async-kernel-client`; queues via Redis Streams through `bagart/php-async-kernel-client-redis`; the checker worker runs as a separate docker-compose container behind a strict contract (plan.md §10.11, task #81); free external services only (policy: plan.md §10.5); geo/ASN via free mmdb files downloaded install-time into storage (never committed); licensed sources plug in through the same contract. Frontend shared with the platform: React 19 + Inertia 2 + Tailwind 4 + Radix UI; Mini App uses `@telegram-apps/sdk-react` with `isVersionAtLeast` feature detection; Rich Messages (Bot API 10.x) with HTML fallback. Layout and conventions follow sibling modules (`tgbot-module-nettools`, `tgbot-module-antispam`). Verification: `composer test` mandatory before delivery, including negative tenant-scoping cases.

## Dependency Injection

- Laravel-facing classes in `Http/Laravel/` (controllers, middlewares, form requests) must NOT receive `TgBotSetupFactory` via auto-binding. Only final/concrete classes.
- `TgBotSetupFactory` is an internal detail of `TelegramBotServiceProvider` — it is used exclusively inside the provider to construct singletons.
- All dependencies for Laravel classes must be registered as singletons in `TelegramBotServiceProvider` and received via method/constructor injection.

## Webhook Endpoints

- `POST /tg/` — `TgWebhookController::post` (token resolved from secret header, IP + secret validation)
- `POST /tg/webhook/{bot_id}` — `TgWebhookController::postByBotId` (token resolved from DB by `{bot_id}`, IP + secret + bot-id-resolver middleware)

## Routes

- All tg routes are in `routes/web.php` under `Route::prefix('tg')` group.
- Library service providers do NOT load routes — they are loaded from the main app.

## DTO Generation

- Run `bash misc/BAGArt/telegram-bot-lib/commands/actualize.sh [--full]` to regenerate Telegram API DTOs (it is a bash script, not an Artisan command).
- DTOs are generated to `misc/BAGArt/telegram-bot-lib/src/TgApi/`.
- For anything related to the external Telegram API (methods, entities, types), always include a `@see https://core.telegram.org/bots/api#...` (or similar) link to the official documentation.
- All DTOs and Enums under `BAGArt\TelegramBot\TgApi` are readonly contracts; code touching Tg DTO/Enum must use `TgApiServices` and inject the DTO/Enum, not raw arrays.

## Daemon Shutdown & State Management (Async Kernel)

### Outbound Architecture

`telegram-bot-lib` is a **read-only library** — all daemon wiring must be explicit in CLI commands and scripts via `new TgOutboundDaemon(...)`. This is the primary extensibility point for customization without editing library code.

**Rules:**
- `TgBotSetup` does NOT contain daemons — it holds only readonly DTOs and contracts for processors (`tgSender`, `outboundStats`, `logger`, `processorRegistry`, etc.).
- Daemons are built explicitly by the caller: factory method `createOutboundDaemonParts()` returns shared components (`['queue','pipeline','circuitBreaker','stats','leaseRenewer']`), the caller picks what it needs and constructs `new TgOutboundDaemon(...)`.
- `TelegramBotServiceProvider` registers only contracts (`TgSenderContract`, `OutboundQueueContract`, `TgOutboundStats`) as singletons — never the daemon itself. Daemon is always built in the command.
- Cache, locker, and queue drivers should be read from Laravel config via `config()` when inside the framework. If the library's internal implementation doesn't match Laravel's driver (e.g. `InMemoryLocker` vs Laravel's cache-based locker), create a `Framework/Laravel/Laravel*Adapter` in the library.

### SKInterruptException

`SKInterruptException` always bubbles up. It does not resolve promises, and is NOT caught in middleware/pipeline. Pipeline code only catches business exceptions (`OutboundSkipException`, `OutboundBusinessErrorException`, `OutboundRetryException`), NOT `SKInterruptException`.

### Daemon::shutdown — strategy

Shutdown does not aim for a fast exit. The goal is seamless completion of in-flight work:

- Daemons implementing `ASKShutdownAware` drain in-flight work across the `STOPPING → DRAINING → FORCING` phases; `shutdown()` returns `false` until drained, so all in-memory tasks are completed even if it takes minutes. No new tasks are pulled once `prepareShutdown()` has run.
- Daemons without `ASKShutdownAware` — the current task completes, no new tasks are accepted.

### State storage rules

**In Redis — only readonly DTOs without behavior:**
- `OutboundTask`, `OutboundTaskState`, `DeadLetterEntry` (JSON)
- Metric counters (`incrementWithTtl`)

**Do NOT store in Redis:**
- Network connections (HTTP client, promises)
- Callbacks/closures
- Objects with behavior (services, middleware, executor)

**Callbacks:** always prefer classes. If a callback wraps logic, extract into a readonly class. Store only state (readonly properties) in Redis, never behavior.

**In-memory management:** every component with in-memory state must implement flush on shutdown (queue, cache, HTTP client, stats).

### Lazy Connections

Constructors MUST NOT connect to external services (Redis, TCP sockets, etc.). Connection must be deferred until the first actual use (lazy connect) or explicitly via `ASKWarmableContract::warm()`.

`AsyncKernel::addDaemon()` calls `warm()` automatically when the daemon or tickable implements `ASKWarmableContract`. This is the designated warmup hook — same role as `tickable` is for tick execution.

## Composer

- Libraries connect via `path` repositories — run composer operations from the WSL shell (not Git Bash). In dev mode vendor symlinks point into `misc/BAGArt/`, so lib changes are immediately visible.
- Dual manifests: `composer.json` (dev, canonical) + `composer.prod.json` (prod overlay; lock `composer.prod.lock`). Non-bagart deps must be identical across manifests — enforced by `cmd/deps/check`.
- All dependency operations go through `cmd/deps/{install,update,check,audit,outdated} --mode=dev|prod|both`. After any intentional composer change regenerate reviewed baselines (`tools/baseline/composer-policy.php --check=scripts --update`, then `tools/baseline/manifest.php --generate`).

## Baseline Tooling

- **Engine lives in `bagart/telegram-platform-devops-baseline`** (`misc/BAGArt/telegram-platform-devops-baseline`, path repo, `v0.1.0`; extracted from the host baseline 2026-08-25). Host entry points are shims: `cmd/dev/{check,security,fix}` and `cmd/baseline/drift-report` delegate to `vendor/bin/baseline-*`; every generic executor under `tools/baseline/*.php|sh` is a thin delegation stub. Policy/state JSONs (`secret-allowlist.json`, `test-budgets.json`, `test-quarantine.json`, `commit-policy.json`, `compat-matrix.json`, …) stay per-repo and override package defaults (resolver: consumer `tools/baseline/<name>` wins over `defaults/<name>`).
- Git hooks ship with the package: `core.hooksPath=vendor/bagart/telegram-platform-devops-baseline/hooks` (set by `cmd/dev/setup`; a fresh clone must `composer install` before its first hook-checked commit).
- Profiles are composer-deps evidence based; the host keeps `async-runtime`+`telegram` via `.baseline-profiles.json` because its libs are in-tree, not required.
- Central reusable CI workflows live in `BAGArt/telegram-platform-workflows` (local checkout `misc/BAGArt/telegram-platform-workflows`, push pending repo creation). Host `.github/workflows/*` switch to SHA-pinned callers after that repo is published.
- **Freeze (Phase 0):** baseline-affecting refactors in `cmd/lib`, package `controls/`, `hooks/` are frozen until consumer rollout completes; edits land in the package first, host stubs follow.
- Golden runs for parity checks: `.cache/baseline/golden-run-phase*.json`.
- Developer entry points live under `cmd/`: `cmd/dev/{setup,doctor,check,fix,test,lint,security,bench}`, `cmd/git/quick-commit`, `cmd/deps/*`, `cmd/ci/*`, `cmd/ops/*`, `cmd/release/{lib,promote}`, `cmd/baseline/*`, `cmd/help`. Prefer them over raw tool invocations. The devops-safe SDD set (`docs/tasks/devops-safe/01–11`) was implemented and then removed from the tree — recover it from git history when a `§N` reference needs context; the only live tracker is `docs/tasks/devops3.md` (consolidated undone work incl. module dual-mode and baseline-package RFCs).
- CLI contract: exit codes `0`–`5` (5 = policy failure), flags `--format=text|json|github` (`--json` alias), levels `--quick|--full|--ci`, `--offline`, `--resume`, `--verbose`, `--quiet`. Defined in the package's `lib/contract.sh`; single definition sites: exit codes + flags → 02 §3, commit prefixes → `defaults/commit-policy.json` (consumer override wins), health paths → `routes/web.php`, SAST targets/rules → `controls/semgrep-scan.sh`.
- Controls in `bin/baseline-check` and `bin/baseline-security` run through `lib/engine.sh` — declared dependencies + parallel waves (02 §15–§16). Register controls there; do not hand-roll sequential loops. Engine extras: `BASELINE_MAX_JOBS`, opt-in `BASELINE_CACHE=1`, per-control budgets `BASELINE_BUDGET_<ID>` / `BASELINE_CONTROL_BUDGET`.
- Baseline-owned files are manifest-tracked (`tools/baseline/MANIFEST.json`, regenerate with `php tools/baseline/manifest.php --generate`); drift via `cmd/baseline/drift-report`; production gate is `cmd/ops/readiness`.
- Git hooks are version-controlled in the package (`hooks/`) and activated via `core.hooksPath`. Never bypass with `--no-verify`; never disable a failing control — fix the cause or use the narrow allowlist (`tools/baseline/secret-allowlist.json`, requires reason + expiry; expired entries fail with exit 5).
- Line endings are LF-only, enforced by `.gitattributes` plus the package's `lf-check.php`; auto-fix via `cmd/dev/fix`.
- Dangerous ops require explicit confirmation flags: `ops/restore --confirm=database`, `ops/restart --confirm=restart`, `ops/replay --confirm=replay --count≤50`, `ops/deploy --confirm=deploy`, `ops/rollback --confirm=rollback`.
- CI workflows (`.github/workflows/`) are SHA-pinned, read-permissions by default, validated locally by `php tools/baseline/yaml-lint.php` and `actionlint` if installed.