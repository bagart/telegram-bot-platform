# Configuration Boundary — Three-Layer Model

Every configurable value in the Telegram Bot Platform flows through three layers.
The effective value at runtime is the highest-precedence layer that has a value.

```
┌─────────────────────────────────────────────────────────────┐
│  Layer 3: Bot / Chat Overrides  (DB)                        │
│  bot_module_activations.module_settings JSONB                │
│  Per-bot or per-chat overrides via SettingsStorageContract  │
│  Highest precedence                                         │
├─────────────────────────────────────────────────────────────┤
│  Layer 2: Platform Admin Defaults  (JSON files)             │
│  storage/app/modules/{moduleId}/settings.json               │
│  Edits by platform admin via web UI / Telegram /settings    │
│  Mid precedence                                             │
├─────────────────────────────────────────────────────────────┤
│  Layer 1: Module Schema Defaults  (PHP config)              │
│  config/tg_modules.php → SettingsScreenContribution         │
│  SettingsField::default — hardcoded in code                 │
│  Lowest precedence                                          │
└─────────────────────────────────────────────────────────────┘
```

## Resolution Chain

When code reads a setting, the effective value is resolved as:

1. **DB override exists?** → use it (Layer 3)
2. **JSON file has the key?** → use it (Layer 2)
3. **Otherwise** → use `SettingsField::default` from `config/tg_modules.php` (Layer 1)

When a value is **deleted** from Layer 2 or Layer 3, the next lower layer resumes.
Deleting a JSON key reverts to the PHP default. Deleting a DB override reverts
to the JSON or PHP default.

### Precedence Rules

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

### Layer 1 — Module Schema Defaults (PHP)

**Where:** `config/tg_modules.php` → `settingsScreens` → `SettingsScreenContribution`

Each module declares its configurable fields as `SettingsField` DTOs inside a
`SettingsDescriptor`. The `default` property is the hardcoded fallback.

**Example** (antispam counter driver):

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

### Layer 2 — Platform Admin Defaults (JSON)

**Where:** `storage/app/modules/{moduleId}/settings.json`
**Interface:** `ConfigFileAccessContract`
**Implementation:** `JsonConfigFileAccess`

Platform admins edit these files through the web admin panel or Telegram
`/settings` commands. The engine writes them via `ConfigFileAccessContract`.

**How it works:**
- `JsonConfigFileAccess::write()` merges with existing values (`array_replace`).
- `JsonConfigFileAccess::forget()` removes a key, reverting to the PHP default.
- `JsonConfigFileAccess::read()` returns all stored overrides for a module.
- Files are created on first write; directory structure is auto-created.

**Example** (`storage/app/modules/proxy/settings.json`):

```json
{
    "proxy.selection_strategy": "least_used",
    "proxy.lease_ttl_seconds": 600
}
```

This overrides the PHP defaults (`round_robin` and `300`) for all bots at
platform level.

**Rules:**
- Platform-wide — affects all bots unless a bot-level override exists.
- NOT the canonical source; `config/tg_modules.php` `SettingsField::default` is.
- JSON only — no `env()`, no PHP code, no DB.
- Git-ignored (lives in `storage/`); operator-managed.

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
- Returns `null` when no override exists — caller falls back to Layer 1/2 defaults.

**Rules:**
- Per-bot — each bot has its own overrides in a separate row.
- Optional chat scope — chat overrides beat bot overrides.
- Runtime only — never git-versioned.
- PostgreSQL uses atomic JSONB operations; SQLite falls back to lock-based R-M-W.

## Layer Mapping — Which Config File Where

| Module | Layer 1 (PHP defaults) | Layer 2 (JSON admin edits) | Layer 3 (DB bot overrides) |
|---|---|---|---|
| antispam | `config/tg_modules.php` → `antispam.settings` screen | `storage/app/modules/antispam/settings.json` | `bot_module_activations.module_settings` |
| summarizer | `config/tg_modules.php` → `summarizer.settings` screen | `storage/app/modules/summarizer/settings.json` | `bot_module_activations.module_settings` |
| tts | `config/tg_modules.php` → `tts.settings` screen | `storage/app/modules/tts/settings.json` | `bot_module_activations.module_settings` |
| nettools | `config/tg_modules.php` → `nettools.settings` screen | `storage/app/modules/nettools/settings.json` | `bot_module_activations.module_settings` |
| proxy | `config/tg_modules.php` → `proxy.settings` screen | `storage/app/modules/proxy/settings.json` | `bot_module_activations.module_settings` |

## Resolution Example

**Scenario:** What is the effective `proxy.lease_ttl_seconds` for bot `B1` in chat `C99`?

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
   Return DB value    ┌─────────────────────────────────┐
   (Layer 3)          │ ConfigFileAccessContract::get()  │
                      │ (JsonConfigFileAccess)           │
                      │ File: storage/app/modules/       │
                      │       {moduleId}/settings.json   │
                      └──────────────┬──────────────────┘
                                     │
                            Found in JSON?
                            ┌────────┴────────┐
                            │ YES             │ NO
                            ▼                 ▼
                     Return JSON value   Return SettingsField::default
                     (Layer 2)           (Layer 1)
```
