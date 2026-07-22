---
name: phpstorm-workflow
description: "Apply when coordinating work between the agent and PHPStorm (the user's primary IDE) to avoid duplicating IDE work and reduce token spend. Trigger when the user mentions PHPStorm, PhpStorm, .idea, refactor, Find Usages, Rename, Navigate, inspection, run configuration, Xdebug, Laravel Idea plugin, or asks 'how should I do this in the IDE'. Also trigger proactively before large multi-file refactors, symbol renames, or usage searches where PHPStorm's structural tools are faster and cheaper than the agent reading N files. Covers: division of labor (IDE vs agent), existing .idea/ configs, run configs, pint formatting flow, composer/artisan command workflows, WSL constraints. Do NOT use this skill to actually perform IDE actions — use it to recommend them and to keep the agent out of the IDE's lane."
license: MIT
metadata:
  author: BAGArt
---

# PHPStorm Workflow

The user's primary IDE is **PHPStorm** with the **Laravel Idea** plugin. This skill's purpose is to keep the agent and the IDE in their respective lanes: PHPStorm is faster and cheaper (free, instant) at navigation, refactoring, and inspection; the agent is better at cross-file edits, generation, and doc lookup. Doing IDE work via the agent wastes tokens and is slower.

## Core Principle: Don't duplicate the IDE

Before reading 10 files to "understand usages" or editing 15 files for a rename, **ask the user to do it in PHPStorm**. One IDE action replaces many agent tool calls.

## Existing Project Configuration

The `.idea/` directory is already configured. Reference these rather than recreating:

| File | Purpose |
|---|---|
| `.idea/php.xml` | PHP 8.5 language level, CLI interpreter, include paths |
| `.idea/phpunit.xml` | PHPUnit/Pest run config |
| `.idea/laravel-idea.xml` | Laravel Idea plugin config (Eloquent meta, Blade, routes) |
| `.idea/jsonSchemas.xml` | JSON schema mappings (e.g. `tg-bots-api.json`) |
| `.idea/inspectionProfiles/` | Inspection settings |
| `.idea/codeStyles/` | Code style (4-space indent, matches `.editorconfig`) |
| `.idea/dataSources.xml` | DB connections for the Laravel Idea query tools |
| `.editorconfig` | LF line endings, 4-space indent, UTF-8, trim trailing whitespace |
| `pint.json` | Laravel Pint formatter config |

`.editorconfig` enforces **LF line endings** — the agent must also write LF only (per project conventions).

## Rule Files

| If coordinating… | Read this first |
|---|---|
| What PHPStorm does better vs what the agent does better | `rules/ide-agent-division.md` |
| Run configs, `composer run dev`, `php artisan test`, pint, WSL `composer update` | `rules/run-configs-and-tooling.md` |
| When to hand a refactor/rename to the IDE instead of editing files | `rules/refactoring-handoff.md` |

## Quick Reference — When to defer to PHPStorm

| Task | Do in PHPStorm | Reason |
|---|---|---|
| Find all usages of a symbol | ✅ Find Usages (Alt+F7) | Instant, free, complete |
| Rename a class/method/property | ✅ Rename (Shift+F6) | Safe refactor across all files |
| Navigate to a symbol's definition | ✅ Go to Declaration (Ctrl+B) | Faster than grep |
| See a class hierarchy | ✅ Type Hierarchy (Ctrl+H) | Structured view |
| Run a single test with coverage | ✅ Run config + Xdebug | Step-through debugging |
| Inspect for code smells | ✅ Inspect Code | Project-wide, configurable |
| Structural search (e.g. "all `catch(Throwable)` without rethrow") | ✅ Structural Search | Pattern-based, exact |
| Edit one file | Either | Comparable |
| Edit 5+ coordinated files | Agent | Bulk edits are the agent's strength |
| Generate boilerplate (migration + model + factory + test) | Agent (via `artisan make:`) | The agent chains the commands |
| Look up Laravel/Inertia version-specific docs | Agent (`search-docs`) | The agent has Boost's doc search |
| Diagnose a failing test from stack trace | Either | Agent reads output; IDE steps through |

See `rules/ide-agent-division.md` for the full breakdown.

## Formatting Flow

When the agent edits PHP, **always** finish with:

```bash
vendor/bin/pint --dirty --format agent
```

This formats only changed files (`--dirty`) and reports in agent-friendly format. Do NOT run `--test` mode — `--format agent` fixes issues directly. See `rules/run-configs-and-tooling.md`.

## Common Pitfalls

- **Reading 20 files to map a rename.** Wasteful. Ask the user to `Shift+F6` in PHPStorm; it updates all references safely in one action.
- **Running `composer update` from Git Bash.** Must be WSL — symlinks in `vendor/bagart/` point to `misc/BAGArt/` and Windows shell mishandles them.
- **Forgetting `--dirty` on pint.** Without it, pint scans the whole project (slow); with it, only staged/dirty files.
- **Editing generated DTOs.** PHPStorm will happily let you — but the next `actualize.sh` overwrites them. The `#[Warning]` attribute is the signal; heed it (see `telegram-dto-generation`).
- **Adding CRLF line endings.** `.editorconfig` says LF; the agent must write `\n` only. PHPStorm respects `.editorconfig` automatically.
