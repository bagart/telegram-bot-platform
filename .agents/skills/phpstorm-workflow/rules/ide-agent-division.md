# IDE vs Agent — Division of Labor

The goal: route each task to whoever does it faster, cheaper, and more reliably. PHPStorm is local, instant, and free (in token terms). The agent is good at generation, cross-file coordination, and doc lookup. Misrouting wastes tokens and time.

## PHPStorm is better at…

### Navigation & discovery
- **Find Usages (Alt+F7)** — all references to a symbol, instantly, across the whole project including `vendor/`. The agent would need many grep/read calls to approximate this.
- **Go to Declaration (Ctrl+B) / Implementation (Ctrl+Alt+B)** — jump to the definition or all implementations of an interface. For `OutboundQueueContract`, this surfaces every adapter in one keystroke.
- **Type Hierarchy (Ctrl+H)** — see what implements `ASKDaemonContract`, or what `TgOutboundDaemon` extends.
- **Call Hierarchy (Ctrl+Alt+H)** — trace who calls a method, recursively. Essential for "is this method still used?" before deletion.
- **Recent Files / Structure (Alt+7)** — overview of a class's members without scrolling.

### Refactoring
- **Rename (Shift+F6)** — safely renames a class/method/property/variable across all files, including strings where appropriate. The agent editing 15 files by hand is slower and riskier.
- **Change Signature** — refactor a method's parameters, updating all callers.
- **Extract Method / Interface / Class** — structured extractions with caller updates.
- **Move Class / Pull Members Up / Push Down** — hierarchy refactors.

### Inspection & analysis
- **Inspect Code** — project-wide analysis: unused code, unreachable code, type mismatches, PSR compliance. Configurable via `.idea/inspectionProfiles/`.
- **Structural Search & Replace** — find code by pattern, e.g. "all `catch (\Throwable $e)` blocks that don't rethrow". Far more precise than grep for semantic queries.
- **Dead code detection** — surfaces methods with no callers (relevant to the project's "no dead methods" rule).

### Debugging & execution
- **Run configurations** — `.idea/phpunit.xml` + the PHPUnit run config; run a single test or suite with one click.
- **Xdebug** — step-through debugging, breakpoints, watch expressions, stack inspection. Irreplaceable for diagnosing the async kernel's Fiber scheduling or shutdown phase bugs.
- **Profiler** — identify hot paths without instrumenting code.

### DB & Laravel-specific (Laravel Idea plugin)
- **Eloquent meta** — autocomplete on magic columns/relations; the `tg_bots` columns appear in completion.
- **Blade/Route inspection** — navigate routes, see which controller handles which path.
- **Query console** — `.idea/dataSources.xml` connects; run read queries without `tinker`.

## The agent is better at…

### Generation & bulk editing
- **Scaffolding** — `php artisan make:model --all`, then wire migrations/factories/tests in one pass.
- **Multi-file coordinated edits** — change a contract here, update 3 implementations there, fix the tests. The agent holds the whole change in context.
- **Boilerplate** — Pest tests following existing `describe()/it()` patterns; new middleware; new DTO mapper.

### Doc & version lookup
- **`search-docs`** — Boost's version-pinned doc search for Laravel/Inertia/Fortify/etc. The IDE's docs are generic; the agent gets the exact version's API.
- **Web search/fetch** — current Telegram Bot API changes, library release notes.

### Cross-cutting analysis
- **"Audit this for stability"** — the `highload-stability` checklist run against a change.
- **"Trace a task end-to-end"** — webhook → queue → pipeline → executor → DLQ, narrated.
- **Synthesizing a design** — propose a new daemon/middleware with the right contracts.

### Things the IDE can't do
- Writing commit messages, PR descriptions.
- Running shell commands (`artisan`, `composer`, `pint`).
- Reading runtime logs / browser logs (Boost tools).

## Decision shortcut

Ask: *"Can PHPStorm do this in one keystroke?"*
- **Yes** → defer to the IDE. Tell the user the action (e.g. "Shift+F6 rename `TgBot::$secret_token`").
- **No, it needs reading + reasoning across files** → agent.
- **No, it's generation** → agent.
- **No, it's a doc lookup** → agent with `search-docs`.

## Anti-patterns to avoid

- **The agent reading 15 files to "find all usages"** before a rename. Stop. Ask the user to `Alt+F7`.
- **The agent hand-renaming across files.** Error-prone and token-heavy. Defer to `Shift+F6`.
- **The agent writing an inspection script** to find dead methods. PHPStorm's "Inspect Code → Unused" already does this.
- **The agent guessing at Laravel API** when `search-docs` would give the exact version's signature.
