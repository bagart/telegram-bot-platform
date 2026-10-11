# Configuration Boundary — Three-Layer Model

Every configurable value in the Telegram Bot Platform flows through three layers.
The effective value at runtime is the highest-precedence layer that has a value.

> **Status (decided 2026-10-07, executed 2026-10-09):** Channel B was retired
> (Q4 option 1). The five `settingsScreens` blocks — the only config
> declarations of `SettingsField` defaults — were removed from
> `config/tg_modules.php`, together with the web-edit surface
> (`SettingsScreenController`, the 3 `management.settings.*` routes, the
> Inertia pages). **Layer 2 now has zero readers and zero writers; Layer 1 has
> no config declarations left.** The only live leg is Layer 3
> (`SettingsStorageContract` over `bot_module_activations.module_settings`);
> Channel A declares its own fields in module `webUi` manifests, which are
> outside this model. The precedence table, layer mapping, resolution example
> and key-class rows below are kept as the model was designed — treat their
> Layer 1/2 rows as historical.

```
┌─────────────────────────────────────────────────────────────┐
│  Layer 3: Bot / Chat Overrides  (DB)                        │
│  bot_module_activations.module_settings JSONB                │
│  Per-bot or per-chat overrides via SettingsStorageContract  │
│  Highest precedence                                         │
├─────────────────────────────────────────────────────────────┤
│  Layer 2: Platform Admin Defaults  (JSON files)             │
│  storage/app/modules/{moduleId}/settings.json               │
│  RETIRED (Q4 option 1, 2026-10-07): web-edit surface gone   │
│  Mid precedence (unreachable — no reader)                    │
├─────────────────────────────────────────────────────────────┤
│  Layer 1: Module Schema Defaults  (PHP config)              │
│  config declarations removed with Channel B (2026-10-09)    │
│  SettingsField::default — no config source left             │
│  Lowest precedence                                          │
└─────────────────────────────────────────────────────────────┘
```

## Resolution Chain

When code reads a setting, the effective value is resolved as:

1. **DB override exists?** → use it (Layer 3)
2. **JSON file has the key?** → use it (Layer 2 — retired: no reader exists,
   so this step never fires in practice)
3. **Otherwise** → there is no defaults leg left: Layer 1's config declarations
   left with Channel B, so callers of `SettingsStorageContract::get()` fall back
   to their own module-code defaults (`settingsFor(...)['key'] ?? …`)

Pre-retirement, step 3 returned `SettingsField::default` from
`config/tg_modules.php`, and deleting a Layer 2/3 value resumed the next lower
layer. That chain is kept below for reference only.

### Precedence Rules (historical — Layers 1/2 retired)

| Scenario | Effective Source |
|---|---|
| No JSON key, no DB override | `SettingsField::default` (Layer 1) |
| JSON key present, no DB override | JSON file value (Layer 2) |
| DB override exists | DB value (Layer 3) |
| Chat override + bot override | Chat override wins (Layer 3, chat scope) |
| JSON key deleted | Reverts to Layer 1 default |
| DB override deleted | Reverts to Layer 2 or Layer 1 |

### Scope Hierarchy (Layer 3 only)

Within the DB layer, chat-scoped overrides beat bot-scoped overrides:

```
chat_override (chatId + fieldId)  >  bot_override (fieldId)  >  platform default
```

## Layer Details

### Layer 1 — Module Schema Defaults (PHP) — declarations retired with Channel B

**Where (historical):** `config/tg_modules.php` → `settingsScreens` →
`SettingsScreenContribution` — the blocks were removed with Channel B
(2026-10-09); **no `SettingsField` declarations remain in config.**

Each module declared its configurable fields as `SettingsField` DTOs inside a
`SettingsDescriptor`. The `default` property was the hardcoded fallback. The
DTOs stay in the engine as dormant primitives (their tests are live); no
production caller resolves them any more.

**Example (historical — antispam counter driver):**

```php
new SettingsField(
    fieldId: 'antispam.counter_driver',
    type: SettingsFieldType::Enum,
    default: 'redis',
    options: [
        ['value' => 'redis', 'labelKey' => 'antispam::settings.counter_driver_redis'],
        ['value' => 'memory', 'labelKey' => 'antispam::settings.counter_driver_memory'],
    ],
),
```

This file also controls module enablement via `env('TG_MODULE_ENABLED_{key}')`:

```php
'antispam' => new TgModuleConfig(
    enabled: env('TG_MODULE_ENABLED_antispam', true),
    // ...
),
```

**Rules:**
- Only source of `env()` calls in the config system.
- No DB reads, no file reads — pure PHP declarations.
- Git-versioned; changes require a deploy.

### Layer 2 — Platform Admin Defaults (JSON) — RETIRED (Q4 option 1)

**Status: retired 2026-10-07.** The platform-admin web-edit surface for this
layer was defunded by Q4 option 1
(`docs/questions/settings-screen-channel-repair-or-retire.md` ## Decision):
the Channel-B `settingsScreens` blocks in `config/tg_modules.php`, the
`management.settings.*` routes, `SettingsScreenController` and the
`Settings/*.tsx` Inertia pages are deleted. No code path reads or writes
`storage/app/modules/{moduleId}/settings.json` any more — the JSON leg has
zero writers and zero readers.

**Where (historical):** `storage/app/modules/{moduleId}/settings.json`
**Interface (dormant):** `ConfigFileAccessContract`
**Implementation (dormant):** `JsonConfigFileAccess`

The contract and its JSON implementation remain in the engine only as unused
primitives: no provider binds `ConfigFileAccessContract`, no production caller
exists, and the pair is kept because its unit test
(`JsonConfigFileAccessTest`) is outside the approved test-deletion set.

**Rules (still binding if the layer is ever re-funded — requires a NEW question):**
- JSON only — no `env()`, no PHP code, no DB (AGENTS storage model).
- Git-ignored (lives in `storage/`); operator-managed.
- Not the canonical source; PHP config defaults are.
- Restoring web editing means re-creating a writer + reader under these rules —
  Q4 option 3 (config-write mechanism) was rejected and is not built.

### Layer 3 — Bot / Chat Overrides (DB)

**Where:** `bot_module_activations.module_settings` (PostgreSQL JSONB column)
**Interface:** `SettingsStorageContract`
**Implementation:** `DatabaseSettingsStorage`

Per-bot (and optionally per-chat) overrides stored in the database. These are
runtime values — bot admins set them via Telegram `/settings` or the web UI.

**JSON structure in `module_settings`:**

```json
{
    "proxy.selection_strategy": "random",
    "proxy.lease_ttl_seconds": 120,
    "123456789:proxy.lease_ttl_seconds": 180
}
```

Keys without a chat prefix are bot-scoped. Keys prefixed with `{chatId}:`
are chat-scoped and take precedence over bot-scoped values.

**How it works:**
- `DatabaseSettingsStorage::get()` reads a single field for a bot/chat.
- `DatabaseSettingsStorage::set()` upserts using atomic JSONB `||` on PostgreSQL,
  read-modify-write on SQLite.
- `DatabaseSettingsStorage::forget()` removes a key using JSONB `-` on PostgreSQL.
- Returns `null` when no override exists — caller falls back to its own
  module-code defaults (the Layer 1/2 config legs retired with Channel B).

**Rules:**
- Per-bot — each bot has its own overrides in a separate row.
- Optional chat scope — chat overrides beat bot overrides.
- Runtime only — never git-versioned.
- PostgreSQL uses atomic JSONB operations; SQLite falls back to lock-based R-M-W.

## Layer Mapping — Which Config File Where (historical)

| Module | Layer 1 (PHP defaults — removed with Channel B) | Layer 2 (JSON admin edits — retired, no reader) | Layer 3 (DB bot overrides — live) |
|---|---|---|---|
| antispam | `config/tg_modules.php` → `antispam.settings` screen | `storage/app/modules/antispam/settings.json` | `bot_module_activations.module_settings` |
| summarizer | `config/tg_modules.php` → `summarizer.settings` screen | `storage/app/modules/summarizer/settings.json` | `bot_module_activations.module_settings` |
| tts | `config/tg_modules.php` → `tts.settings` screen | `storage/app/modules/tts/settings.json` | `bot_module_activations.module_settings` |
| nettools | `config/tg_modules.php` → `nettools.settings` screen | `storage/app/modules/nettools/settings.json` | `bot_module_activations.module_settings` |
| proxy | `config/tg_modules.php` → `proxy.settings` screen | `storage/app/modules/proxy/settings.json` | `bot_module_activations.module_settings` |

## Resolution Example (historical)

**Scenario:** What is the effective `proxy.lease_ttl_seconds` for bot `B1` in chat `C99`?
(Layers 1/2 are retired — today only the Layer 3 rows can ever resolve.)

| Layer | Source | Value |
|---|---|---|
| Layer 1 | `SettingsField::default` in `config/tg_modules.php` | `300` |
| Layer 2 | `storage/app/modules/proxy/settings.json` → `proxy.lease_ttl_seconds` | `600` |
| Layer 3 (bot) | `bot_module_activations` where `bot_id=B1` → `proxy.lease_ttl_seconds` | `120` |
| Layer 3 (chat) | `bot_module_activations` where `bot_id=B1` → `123456789:proxy.lease_ttl_seconds` | `180` |

**Effective value:** `180` (chat override wins)

If the chat override is deleted: `120` (bot override resumes)
If the bot override is also deleted: `600` (JSON file resumes)
If the JSON key is also deleted: `300` (PHP default resumes)

## Key Classes

**Live:** `SettingsStorageContract`, `DatabaseSettingsStorage`, `ResolvedSetting`.
**Dormant (retained after Channel B, no production caller):** `SettingsField`,
`SettingsDescriptor`, `SettingsScreenContribution`, `ConfigFileAccessContract`,
`JsonConfigFileAccess`.

| Class | Namespace | Role |
|---|---|---|
| `SettingsField` | `TelegramModuleEngine\Settings` | Typed field definition with default, constraints, i18n |
| `SettingsDescriptor` | `TelegramModuleEngine\Settings` | Ordered field list for a settings screen |
| `SettingsScreenContribution` | `TelegramModuleEngine\Settings` | Module's contribution: screen ID + descriptor + bindings |
| `ConfigFileAccessContract` | `TelegramModuleEngine\Settings` | Interface for platform-wide config file I/O |
| `JsonConfigFileAccess` | `TelegramModuleEngine\Settings` | JSON-file implementation of `ConfigFileAccessContract` |
| `SettingsStorageContract` | `TelegramModuleEngine\Settings` | Interface for per-bot/chat runtime overrides |
| `DatabaseSettingsStorage` | `TelegramModuleEngine\Settings` | DB implementation of `SettingsStorageContract` |
| `ResolvedSetting` | `TelegramModuleEngine\Settings` | DTO for a resolved value scoped to bot/chat |

## Diagram — Full Resolution Flow

```
Code reads setting "proxy.lease_ttl_seconds" for bot B1, chat C99
                    │
                    ▼
    ┌──────────────────────────────────┐
    │ SettingsStorageContract::get()   │
    │ (DatabaseSettingsStorage)        │
    │ DB: bot_module_activations       │
    └──────────────┬───────────────────┘
                   │
          Found in DB?
          ┌────────┴────────┐
          │ YES             │ NO
          ▼                 ▼
   Return DB value    ┌──────────────────────────────────┐
   (Layer 3)          │ ConfigFileAccessContract::get()   │
                      │ (JsonConfigFileAccess)            │
                      │ RETIRED (Q4 option 1): no binding, │
                      │ no caller, no reader — this leg   │
                      │ never resolves                    │
                      └──────────────┬───────────────────┘
                                     │
                                     ▼
                            Return SettingsField::default
                            (Layer 1)
```
