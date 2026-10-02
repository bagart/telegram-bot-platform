# Module Integration — Remaining Items

Completed integration decisions are retained in [sdd/module-integration.md](sdd/module-integration.md).

## Phase 4.4 — Legacy Enablement Bindings

Status: partially migrated. The `enablement_driver = 'engine'` config is active; `ModuleEnablementContract` (is-enabled queries) routes through `EngineModuleEnablement` reading `bot_module_activations`. Two legacy dependencies remain.

### What was migrated

- `ModuleEnablementContract` → `EngineModuleEnablement` (reads `bot_module_activations`). The webhook update selector no longer touches `tg_module_enablements`.
- `TgModuleToggleCommand` dispatches through `ModuleActivationService` when `enablement_driver='engine'`.

### What remains in legacy (`tg_module_enablements`)

1. **`ModuleSettingsContract`** — bound to `TgModuleEnablementService`, which reads `module_settings` from the legacy table with 3-tier inheritance (chat → bot → platform). The engine table already has a `module_settings` JSONB column and `DatabaseSettingsStorage` writes to it, but no adapter bridges `ModuleSettingsContract` to the engine.

2. **Menu `SettingsWriterContract`** — `ModuleEnablementSettingsWriter` wraps `TgModuleEnablementService` directly, so all menu settings writes (web API + CLI install/uninstall) go to `tg_module_enablements.module_settings`, not `bot_module_activations.module_settings`.

3. **Menu `ModuleEnablementSourceContract`** — `DatabaseModuleEnablementSource::build()` reads the 3-tier enablement inheritance from `tg_module_enablements` for bulk menu rendering. The engine table is bot-level only and cannot express the same chain.

4. **`MenuInstallCommand`** — reads `TgModuleEnablement` model directly (line 106) to check prior install state.

### Why full migration is blocked

- **Schema mismatch**: `tg_module_enablements` has nullable `bot_id`/`chat_id` for 3-tier scoping; `bot_module_activations` is bot-level only (`UNIQUE(bot_id, module_id)`).
- **Settings key format**: `DatabaseSettingsStorage` uses flat `"fieldId"` keys; the legacy service uses inheritance-aware merged maps. Chat-scoped settings (`"chatId:fieldId"`) have no equivalent in the engine table.
- **Menu dependency**: The menu module's bulk enablement view (`DatabaseModuleEnablementSource`) and the install/uninstall commands depend on the 3-tier model for platform-level overrides.

### What was done this session

- Created `EngineSettingsAdapter` — implements `ModuleSettingsContract`, reads settings from `bot_module_activations` via `DatabaseSettingsStorage`. Binds when `enablement_driver='engine'` (replaces the legacy `ModuleSettingsContract` binding).
- Updated `TelegramBotManagementServiceProvider` to skip the legacy `ModuleSettingsContract` bridge when engine driver is active.
- Updated `TelegramModuleEngineServiceProvider` to register the new adapter.
- Documented remaining consumers in `tg_module_enablements` above.

### Deferred

- Migrating menu `SettingsWriterContract` writes to engine (requires chat-scoping decision).
- Migrating `DatabaseModuleEnablementSource` reads to engine (requires 3-tier → bot-level simplification or engine schema extension).
- Removing `TgModuleEnablementService` entirely (depends on completing the above).
- Data migration of existing `tg_module_enablements` rows to `bot_module_activations`.

## Docker Repository Enumeration

- [ ] Keep the Dockerfile `REPOS` list synchronized when adding modules, or replace manual enumeration with a verified declarative source.

## Acceptance and Rollback

- [ ] Verify module validation and doctor diagnostics for the remaining integration changes.
- [ ] Verify reversible cutover and storage migration before removing compatibility bindings or applying destructive migrations.
