---
name: telegram-dto-generation
description: "Apply when working on the auto-generated Telegram Bot API DTOs under misc/BAGArt/telegram-bot-lib/src/TgApi/ — Methods/DTO, Types/DTO, Methods/Enum, Types/Enum, and the aggregate TgApiMethodsEnum/TgApiTypesEnum. Trigger when the user wants to regenerate DTOs after a Telegram API update, when adding a new API method/type, when fixing a DTO mapping bug, or when editing the generator (DevTool/DTOGenerator.php). Also trigger when asked about the dto:tg_actualize / tg:dev:dto command. Covers: tg_actualize.sh workflow, --full wipe semantics, the npm @grom.js/bot-api-spec source, output directories, the #[Warning] attribute marking generated files. Do NOT use this skill to hand-edit generated DTOs (they will be overwritten) — use it to regenerate, or to edit the generator itself."
license: MIT
metadata:
  author: BAGArt
---

# Telegram DTO Generation

The ~450 DTOs under `misc/BAGArt/telegram-bot-lib/src/TgApi/` are **fully auto-generated** from the official Telegram Bot API schema. They are not hand-written. Every generated file carries the `#[Warning('File is auto-generated...')]` attribute.

## The Golden Rule

> **Never hand-edit a generated DTO.** The next `actualize.sh` run will overwrite it. To change a DTO's shape, either (a) regenerate after a Telegram API release, or (b) edit the generator (`DevTool/DTOGenerator.php`) — never the output file.

## The Correct Command

> ⚠️ `AGENTS.md` and `TgApi/Warning.md` reference `php artisan tg:dev:dto:actualize`. **That Artisan command is not registered.** The real generator is a bash script. Use:

```bash
bash misc/BAGArt/telegram-bot-lib/commands/tg_actualize.sh [--full]
```

From the repo root. The script cd's into the lib dir itself. See `rules/generator.md` for the three steps and when to use `--full`.

## When to Regenerate

- After Telegram ships a new Bot API version with methods/types you need.
- When `@grom.js/bot-api-spec` (the npm schema source) is updated.
- Never as part of normal feature work — regeneration is a deliberate, reviewable event.

## Output Layout

```
telegram-bot-lib/src/TgApi/
├── Methods/
│   ├── DTO/      ~166 *MethodDTO.php    (e.g. SendMessageMethodDTO)
│   ├── Enum/     field enums            (ParseModeEnum, ActionEnum, …)
│   └── TgApiMethodsEnum.php             entity name → DTO class map
├── Types/
│   ├── DTO/      ~284 *TypeDTO.php      (e.g. MessageTypeDTO)
│   ├── Enum/     type enums             (ChatTypeEnum, CurrencyEnum, …)
│   └── TgApiTypesEnum.php               entity name → DTO class map
└── TgApiEntityScopeEnum.php             (Methods | Types)
```

Each DTO implements `TgApiMethodDTOContract` or `TgApiTypeDTOContract` and exposes `tgApiEntity()`, `tgEntityScope()`, `tgPropertyMetas(): TgApiProperty[]`. Method DTOs additionally expose `getReturnTypes()`.

## Rule File

| If the task touches… | Read this first |
|---|---|
| Running the generator, `--full` semantics, editing `DTOGenerator.php`, the JSON schema format | `rules/generator.md` |

## Common Pitfalls

- **Running `php artisan tg:dev:dto:actualize`.** Doesn't exist. Use `bash .../commands/actualize.sh`.
- **Hand-editing a generated DTO** to "add a field". It will be silently overwritten on the next regeneration. Edit the generator or wait for an API update.
- **Running the generator from the wrong directory.** The script handles `cd` itself — invoke it from the repo root with the path shown above.
- **Committing `tg-bots-api.json` blindly.** It's regenerated each run from the npm package — diff it carefully before committing, it's the schema source-of-truth snapshot.
- **Expecting the generator to preserve custom code.** `--full` wipes the output dirs. Any non-generated file placed in `TgApi/` will be lost.
