# Refactoring Handoff (IDE vs Agent)

When a change is structurally a refactor, decide who drives based on scope and risk.

## Hand to PHPStorm when…

### Renaming a widely-used symbol
A class, method, property, or namespace referenced in many files. Examples from this project:
- Renaming `TgBot::$secret_token` → PHPStorm's **Shift+F6** updates the model, migrations references, all call sites, and (optionally) strings.
- Renaming a contract method on `OutboundQueueContract` → updates every implementation (`InMemoryOutboundQueue`, `LaravelQueueAdapter`, `RedisOutboundCache`, etc.) in one action.

The agent doing this by hand risks missing a reference (especially across `misc/BAGArt/*/src/` and `tests/`). PHPStorm's refactor is compile-checked.

### Moving a class or changing namespace
**Move Class (F6)** updates the file, the namespace, all `use` statements, and PSR-4 autoloading expectations. The agent would have to edit the file, every importer, and `composer.json` manually.

### Changing a method signature
**Change Signature (Ctrl+F6)** updates all callers. For example, adding a required parameter to `TgBotSetupFactory::createOutboundDaemonParts()` would break every caller; PHPStorm finds them all.

### Extracting an interface or superclass
**Extract Interface / Extract Superclass** generates the interface and offers to update type hints to use it. Useful when promoting a concrete dependency to a contract (the project's strict-contracts rule).

### Finding dead code before deletion
**Inspect Code → Unused declaration** surfaces methods/properties with no callers. Directly relevant to the project's "every public method must have a real caller" rule. The agent grepping for usages is less reliable than the IDE's static analysis.

## Keep with the agent when…

### Multi-file coordinated semantic changes
The change isn't a pure rename — it's a behavior shift across files. Example: "add a new middleware `IdempotencyMiddleware` between `RateLimitMiddleware` and the executor, wire it into the factory, add its test." PHPStorm can't compose that; the agent can.

### Scaffolding new code
`php artisan make:` + wiring migrations/factories/tests. The agent chains the commands and writes the glue.

### Cross-library changes
Edits that span `telegram-bot-lib`, `telegram-bot-management-lib`, and the host app, where each has its own conventions. The agent holds the conventions in context; PHPStorm sees files uniformly.

### Applying a pattern skillfully
"Make this daemon implement `ASKShutdownAware` correctly" — the agent applies the `async-kernel-development` rules; PHPStorm can't reason about correctness, only structure.

### Doc/comment generation
PHPDoc blocks, skill files, commit messages. The agent; not the IDE's lane.

## The handoff pattern

When you detect a PHPStorm-better task mid-flow, stop and hand off:

> "This is a wide rename of `X` across ~12 files. PHPStorm's **Shift+F6** will do it safely in one action — faster and more reliable than me editing each file. After you've renamed, I'll pick up the remaining semantic changes (e.g. updating the docblock / migration)."

Then wait for the user to confirm the rename is done before continuing. Don't race ahead editing files that the rename is about to touch.

## Verification after an IDE refactor

Once the user has refactored in PHPStorm:
1. Ask them to run the relevant test suite (`php artisan test --compact --filter=...`).
2. If green, the agent can proceed with follow-up changes.
3. If red, ask for the failure output — the agent diagnoses from there.

## What NOT to hand off

- **Pure logic bugs.** The IDE can't reason about why a Fiber stalls. The agent reads the code and reasons.
- **Design decisions.** "Should this be a capability interface or a base-contract method?" — the agent, using the domain skills.
- **Anything requiring doc lookup.** `search-docs` is the agent's tool.

## Synergy example

Task: "Rename `createOutboundDaemonParts` to `createOutboundComponents` and update docs."

1. **Agent** confirms the rename is desired (it's currently correct in code; the docs are wrong — so actually the fix is the other direction: update docs to match code). ← reasoning step, agent's lane.
2. If a real rename is wanted: **PHPStorm Shift+F6** on the method name → updates `TgBotSetupFactory.php`, all callers in `commands/*.php`, the test, and (with string mode) the doc references.
3. **Agent** updates `AGENTS.md` and the relevant skill `rules/*.md` to match. ← semantic doc edit, agent's lane.
4. **Agent** runs `vendor/bin/pint --dirty --format agent` + the outbound tests.

Each step routed to the cheaper, more reliable executor.
