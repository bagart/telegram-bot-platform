# Run Configs & Command Tooling

## The dev loop

The project's `composer.json` defines a `dev` script that runs Vite + PHP together:

```bash
composer run dev     # Vite dev server + PHP server, parallel
```

For backend-only work, the agent typically doesn't need this — use the targeted commands below.

## Testing

```bash
# Host app (Pest v4) — full suite
php artisan test --compact

# Filter to a single test
php artisan test --compact --filter=TgOutboundDaemonCommandTest

# A library's tests (run inside the lib dir)
cd misc/BAGArt/telegram-bot-lib && composer test
```

The host `test` composer script chains: `pint --test` → `php artisan test` → per-lib `composer test`. Run it for a full pre-commit check.

PHPStorm: the `.idea/phpunit.xml` run config runs Pest tests with one click; set breakpoints for Xdebug step-through.

## Formatting (Laravel Pint)

After any PHP edit, **always**:

```bash
vendor/bin/pint --dirty --format agent
```

- `--dirty` — only files with uncommitted changes (fast; doesn't scan the whole project).
- `--format agent` — agent-friendly output; fixes issues directly (do NOT use `--test` mode, which only reports).

Run this as the last step before finalizing any PHP change. For library PHP, run `pint` inside the lib dir.

## Artisan commands

Discover and inspect:
```bash
php artisan list                    # all commands
php artisan list tgbm               # the bot-management commands
php artisan route:list --path=tg    # tg_webhook routes
php artisan config:show tg-outbound-daemon  # read config
```

Always pass `--no-interaction` when the agent runs an artisan command.

Key project-specific commands:
| Command | Purpose |
|---|---|
| `tgbm:outbound-daemon --mode=single\|multi` | Run the outbound daemon |
| `tgbm:outbound-dlq --list\|--retry\|--purge` | DLQ operations |
| `tgbm:outbound-metrics` | Metrics daemon |
| `tgbm:monitor` | Inspect queue/DLQ/stats |
| `tgbm:migrate` | Run lib migrations |
| `bash misc/BAGArt/telegram-bot-lib/commands/actualize.sh [--full]` | Regenerate Telegram DTOs (NOT an artisan command) |

## Composer — the WSL constraint

> **`composer update` MUST run from WSL, not Git Bash.**

The `vendor/bagart/` symlinks point to `misc/BAGArt/`. Windows shells mishandle these symlinks; WSL handles them correctly. Symptom of getting it wrong: broken autoload, "class not found" for BAGArt namespaces.

For `composer require`/`remove` (dependency changes — which need approval per project rules), also use WSL.

For `composer install` (no resolution, just download), Git Bash is usually fine, but WSL is safer.

## Vite / frontend

If a frontend change isn't reflected, the user may need:
```bash
npm run build    # production build
npm run dev      # dev server (HMR)
composer run dev # both together
```

Ask before assuming — see the laravel-boost-guidelines note on Vite errors.

## Tinker (debugging)

```bash
# CORRECT — single quotes outside, double quotes for PHP strings
php artisan tinker --execute 'User::where("active", true)->count();'

# WRONG — shell expansion breaks the PHP string
php artisan tinker --execute "User::where('active', true)->count();"
```

Prefer existing artisan commands or tests over ad-hoc tinker. Don't create models in tinker without approval — use factories in tests.

## Boost tools (MCP)

When the Boost MCP server is available, prefer its tools over manual alternatives:
- `database-query` — read-only queries (instead of raw SQL in tinker).
- `database-schema` — inspect tables before writing migrations/models.
- `get-absolute-url` — resolve correct scheme/domain/port before sharing a URL.
- `browser-logs` — read recent browser errors (ignore old entries).
- `search-docs` — version-specific Laravel/Inertia/etc. docs. **Always use before code changes.**

## Database

`.idea/dataSources.xml` configures DB connections for PHPStorm's database tool. For schema inspection, prefer Boost's `database-schema` or PHPStorm's tool over reading migrations by hand.

## Recommended agent workflow

For a typical backend change:
1. `search-docs` for any Laravel/API uncertainty.
2. Read the target files (or ask the user to navigate in PHPStorm).
3. Make the edit.
4. `vendor/bin/pint --dirty --format agent`.
5. `php artisan test --compact --filter=<relevant test>`.
6. Report; if a rename/large refactor is involved, recommend the user verify with PHPStorm Find Usages.
