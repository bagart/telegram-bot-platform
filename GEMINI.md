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
- \@inertiajs/react (INERTIA_REACT) - v2
- react (REACT) - v19
- tailwindcss (TAILWINDCSS) - v4
- \@laravel/vite-plugin-wayfinder (WAYFINDER_VITE) - v0
- eslint (ESLINT) - v9
- prettier (PRETTIER) - v3

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `wayfinder-development` — Activates whenever referencing backend routes in frontend components. Use when importing from @/actions or @/routes, calling Laravel routes from TypeScript, or working with Wayfinder route functions.
- `pest-testing` — Use this skill for Pest PHP testing in Laravel projects only. Trigger whenever any test is being written, edited, fixed, or refactored — including fixing tests that broke after a code change, adding assertions, converting PHPUnit to Pest, adding datasets, and TDD workflows. Always activate when the user asks how to write something in Pest, mentions test files or directories (tests/Feature, tests/Unit, tests/Browser), or needs browser testing, smoke testing multiple pages for JS errors, or architecture tests. Covers: it()/expect() syntax, datasets, mocking, browser testing (visit/click/fill), smoke testing, arch(), Livewire component tests, RefreshDatabase, and all Pest 4 features. Do not use for factories, seeders, migrations, controllers, models, or non-test PHP code.
- `inertia-react-development` — Develops Inertia.js v2 React client-side applications. Activates when creating React pages, forms, or navigation; using <Link>, <Form>, useForm, or router; working with deferred props, prefetching, or polling; or when user mentions React with Inertia, React pages, React forms, or React navigation.
- `tailwindcss-development` — Always invoke when the user's message includes 'tailwind' in any form. Also invoke for: building responsive grid layouts (multi-column card grids, product grids), flex/grid page structures (dashboards with sidebars, fixed topbars, mobile-toggle navs), styling UI components (cards, tables, navbars, pricing sections, forms, inputs, badges), adding dark mode variants, fixing spacing or typography, and Tailwind v3/v4 work. The core use case: writing or fixing Tailwind utility classes in HTML templates (Blade, JSX, Vue). Skip for backend PHP logic, database queries, API routes, JavaScript with no HTML/CSS component, CSS file audits, build tool configuration, and vanilla CSS.
- `fortify-development` — Laravel Fortify headless authentication backend development. Activate when implementing authentication features including login, registration, password reset, email verification, two-factor authentication (2FA/TOTP), profile updates, headless auth, authentication scaffolding, or auth guards in Laravel applications.

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

- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan Commands

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`, `php artisan tinker --execute "..."`).
- Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.

## URLs

- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Debugging

- Use the `database-query` tool when you only need to read from the database.
- Use the `database-schema` tool to inspect table structure before writing migrations or models.
- To execute PHP code for debugging, run `php artisan tinker --execute "your code here"` directly.
- To read configuration values, read the config files directly or run `php artisan config:show [key]`.
- To inspect routes, run `php artisan route:list` directly.
- To check environment variables, read the `.env` file directly.

## Reading Browser Logs With the `browser-logs` Tool

- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)

- Boost comes with a powerful `search-docs` tool you should use before trying other approaches when working with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries at once. For example: `['rate limiting', 'routing rate limiting', 'routing']`. The most relevant results will be returned first.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.

## Constructors

- Use PHP 8 constructor property promotion in `__construct()`.
    - `public function __construct(public GitHub $github) { }`
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

## Type Declarations

- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<!-- Explicit Return Types and Method Params -->
```php
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
```

## Enums

- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

## Comments

- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless the logic is exceptionally complex.

## PHPDoc Blocks

- Add useful array shape type definitions when appropriate.

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

## Database

- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

### APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## Controllers & Validation

- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

## Authentication & Authorization

- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Queues

- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

## Configuration

- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== wayfinder/core rules ===

# Laravel Wayfinder

Wayfinder generates TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

- IMPORTANT: Activate `wayfinder-development` skill whenever referencing backend routes in frontend components.
- Invokable Controllers: `import StorePost from '@/actions/.../StorePostController'; StorePost()`.
- Parameter Binding: Detects route keys (`{post:slug}`) — `show({ slug: "my-post" })`.
- Query Merging: `show(1, { mergeQuery: { page: 2, sort: null } })` merges with current URL, `null` removes params.
- Inertia: Use `.form()` with `<Form>` component or `form.submit(store())` with useForm.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

=== laravel/fortify rules ===

# Laravel Fortify

- Fortify is a headless authentication backend that provides authentication routes and controllers for Laravel applications.
- IMPORTANT: Always use the `search-docs` tool for detailed Laravel Fortify patterns and documentation.
- IMPORTANT: Activate `developing-with-fortify` skill when working with Fortify authentication features.

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

<!-- Mirrored from AGENTS.md (lines 193+). Keep in sync. Canonical source: AGENTS.md. -->

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

- `POST /tg/` — `TgWebhookController::post` (token resolved from secret header, IP + secret validation)
- `POST /tg/webhook/{bot_id}` — `TgWebhookController::postByBotId` (token resolved from DB by `{bot_id}`, IP + secret + bot-id-resolver middleware)

## Routes

- All tg routes are in `routes/web.php` under `Route::prefix('tg')` group.
- Library service providers do NOT load routes — they are loaded from the main app.

## DTO Generation

- Run `bash misc/BAGArt/telegram-bot-lib/commands/actualize.sh [--full]` to regenerate Telegram API DTOs (it is a bash script, not an Artisan command).
- DTOs are generated to `misc/BAGArt/telegram-bot-lib/src/TgApi/`.

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

- Libraries are connected via `path` repositories — run `composer update` from WSL shell (not Git Bash).
- Symlinks in `vendor/bagart/` point to `misc/BAGArt/` — changes in libs are immediately visible.
