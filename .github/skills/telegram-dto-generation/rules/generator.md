# DTOGenerator Workflow

## The script: `commands/actualize.sh`

Located at `misc/BAGArt/telegram-bot-lib/commands/actualize.sh`. Three steps, run from the lib root:

```bash
#!/bin/bash
set -e
cd "$(dirname "$0")/.."

# STEP 1 — refresh the schema source
npm update @grom.js/bot-api-spec --prefer-offline

# STEP 2 — dump the schema to JSON
node -e "import('@grom.js/bot-api-spec').then(m => console.log(JSON.stringify({methods: m.methods, types: m.types}, null, 2)))" > tg-bots-api.json

# STEP 3 — generate PHP DTOs
php src/DevTool/DTOGenerator.php [--full]
```

### Invoke from the repo root

```bash
# Incremental (default): only update changed/new DTOs, preserve extras
bash misc/BAGArt/telegram-bot-lib/commands/tg_actualize.sh

# Full wipe + regenerate: removes ALL generated files first
bash misc/BAGArt/telegram-bot-lib/commands/tg_actualize.sh --full
```

## `--full` semantics

- **Without `--full` (incremental):** the generator compares the schema to existing files and reports `Actual` / `Updated` / `Created` / `Extra` arrays. `Extra` = files present in the output dir that the schema doesn't define (often hand-added or renamed — review before deleting).
- **With `--full`:** the output directories are wiped before generation. Use this when the schema has changed structurally (renamed types, removed fields) and you want a clean slate. **Anything not in the schema is lost** — including manually-added files in `TgApi/`.

Default to incremental; use `--full` only when you intend to.

## The generator: `DevTool/DTOGenerator.php`

A standalone PHP script (not an Artisan command). Reads `tg-bots-api.json`, emits:

- `TgApi/Methods/DTO/*MethodDTO.php` — one per API method
- `TgApi/Types/DTO/*TypeDTO.php` — one per API type
- `TgApi/Methods/Enum/*Enum.php`, `TgApi/Types/Enum/*Enum.php` — field/type enums
- `TgApiMethodsEnum.php`, `TgApiTypesEnum.php` — aggregate maps (entity name ↔ DTO class)

CLI parsing is minimal: `$full = in_array('--full', $argv, true);`. The `--debug` flag mentioned in some docs is **not implemented** in the current generator — ignore it.

## Editing the generator

If a DTO is wrong (wrong field type, missing return-type mapping, wrong enum backing), the fix belongs in `DTOGenerator.php`, not in the output DTO. Workflow:

1. Identify the transformation rule that's incorrect (look for the `emit*` / `build*` methods).
2. Edit `DTOGenerator.php`.
3. Re-run `bash commands/actualize.sh` (incremental is fine for a single fix).
4. Diff the affected DTO(s) to confirm.
5. Run the lib's test suite: `composer test` inside `telegram-bot-lib/`.

## The JSON schema (`tg-bots-api.json`)

Sourced from npm `@grom.js/bot-api-spec` (which mirrors the official Telegram Bot API schema). Shape:

```json
{
  "methods": [ { "name": "sendMessage", "fields": [...], "returns": [...] } ],
  "types":   [ { "name": "Message", "fields": [...] } ]
}
```

This file is regenerated each run. Commit it so the generated DTOs are reproducible from a known schema snapshot.

## After generation

- Run `vendor/bin/pint` in the lib to normalize formatting (generated code should still pass the project style).
- Run `composer test` in `telegram-bot-lib/` — the DTO tests (`tests/Unit/Outbound/*` exercise the DTO mapper) should still pass.
- If `--full` removed a DTO that other code imports, PHPStorm's "Find Usages" will surface the breakages — fix call sites to use the new/renamed DTO.
