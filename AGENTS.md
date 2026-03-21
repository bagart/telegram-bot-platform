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
- Development is primarily in `misc/`, avoid touching `app/` when possible.
- Telegram bot tokens are stored in DB (`tg_bots` table), not in `.env`.
- ALWAYS use LF line endings, never CRLF. Write all files with `\n` only.
- Strict contracts only — no `method_exists`, `instanceof` duck-typing across library boundaries. If a caller needs a method, it MUST be declared in the interface/contract. Do not add dead methods; every public method must have a real caller.

## Telegram Bot Platform Structure

- `misc/BAGArt/telegram-bot-lib` — pure Telegram Bot API library (no Laravel)
- `misc/BAGArt/telegram-bot-basic-lib` — basic webhook handlers, middleware, commands (works with pure PHP and Laravel)
- `misc/BAGArt/telegram-bot-management-lib` — multi-bot management, models (`TgBot`, `TgBotOwner`, `TgBotModule`), DB migrations

## Dependency Injection

- Laravel-facing classes in `Http/Laravel/` (controllers, middlewares, form requests) must NOT receive `TgBotSetupFactory` via auto-binding. Only final/concrete classes.
- `TgBotSetupFactory` is an internal detail of `TelegramBotServiceProvider` — it is used exclusively inside the provider to construct singletons.
- All dependencies for Laravel classes must be registered as singletons in `TelegramBotServiceProvider` and received via method/constructor injection.

## Webhook Endpoints

- `POST /tg/{token}` — WebhookController (token from URL, no middleware)
- `POST /tg/example/{token}` — TgWebhookExample (token from URL)
- `POST /tg/webhook/{bot_uuid}` — TgWebhookExample (token from DB, IP + secret token middleware)

## Routes

- All tg routes are in `routes/web.php` under `Route::prefix('tg')` group.
- Library service providers do NOT load routes — they are loaded from the main app.

## DTO Generation

- Run `php artisan tg:dev:dto:actualize --full --debug` to regenerate Telegram API DTOs.
- DTOs are generated to `misc/BAGArt/telegram-bot-basic-lib/src/TgApi/`.

## Daemon Shutdown & State Management (Async Kernel)

### Outbound Architecture

`telegram-bot-lib` is a **read-only library** — all daemon wiring must be explicit in CLI commands and scripts via `new TgOutboundDaemon(...)`. This is the primary extensibility point for customization without editing library code.

**Rules:**
- `TgBotSetup` does NOT contain daemons — it holds only readonly DTOs and contracts for processors (`tgSender`, `outboundStats`, `logger`, `processorRegistry`, etc.).
- Daemons are built explicitly by the caller: factory method `createOutboundComponents()` returns shared components (queue, stats, sender, daemon), the caller picks what it needs and constructs `new TgOutboundDaemon(...)`.
- `TelegramBotServiceProvider` registers only contracts (`TgSenderContract`, `OutboundQueueContract`, `TgOutboundStats`) as singletons — never the daemon itself. Daemon is always built in the command.
- Cache, locker, and queue drivers should be read from Laravel config via `config()` when inside the framework. If the library's internal implementation doesn't match Laravel's driver (e.g. `InMemoryLocker` vs Laravel's cache-based locker), create a `Framework/Laravel/Laravel*Adapter` in the library.

### SKInterruptException

`SKInterruptException` always bubbles up. It does not resolve promises, and is NOT caught in middleware/pipeline. Pipeline code only catches business exceptions (`OutboundSkipException`, `OutboundBusinessErrorException`, `OutboundRetryException`), NOT `SKInterruptException`.

### Daemon::shutdown — strategy

Shutdown does not aim for a fast exit. The goal is seamless completion of in-flight work:

- If the queue implements `ASKShutdownToCompleteContract` — all in-memory tasks are completed even if it takes minutes. No new tasks are pulled from the queue.
- Without the contract — the current task completes, no new tasks are accepted.

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

- Libraries are connected via `path` repositories — run `composer update` from WSL shell (not Git Bash).
- Symlinks in `vendor/bagart/` point to `misc/BAGArt/` — changes in libs are immediately visible.
