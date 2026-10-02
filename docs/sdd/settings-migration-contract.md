# SDD: Settings Migration — driver-aware settings contract

> Status: DONE
> Date: 2026-09-29
> Task: docs/tasks/settings-migration-engine.md

## What Was Built

All four chat-settings modules (antispam, summarizer, tts, stt) plus the management doctor command now read/write module settings exclusively through the lib `ModuleSettingsContract`, which is implemented on BOTH `enablement_driver` variants. The dropped legacy table (`tg_module_enablements`, dropped in prod by management `2026_09_20_000002`) is no longer a runtime dependency for these paths. The engine driver gained the two capability gaps that previously forced the legacy pins: chat→bot settings inheritance on read, and per-chat enablement dispatch from the migrated `{chatId}:__enabled__` key.

## Files

- `misc/BAGArt/telegram-bot-lib/src/Contracts/Modules/ModuleSettingsContract.php` — 3 methods: `settingsFor(moduleId, botId, ?chatId=null)` (effective map, chat→bot→platform), `patchSettings(moduleId, botId, ?chatId, patch)` (raw-scope merge, `null`=remove, reserved `enabled` flips enablement), `chatsWithSettings(moduleId, ?botId=null)` (explicit per-scope maps, chat DESC then bot scope last).
- `misc/BAGArt/telegram-platform-management/src/Services/TgModuleEnablementService.php` — legacy impl (thin: reuses `upsert()`/`scopedRows()`); enables unmigrated dev DBs and the pinned test matrix.
- `misc/BAGArt/telegram-platform-module/src/Activation/EngineSettingsAdapter.php` — engine impl: bot-base + chat-overlay reads, per-key raw-scope writes via `SettingsStorageContract`, reserved `enabled` → `{chatId}:__enabled__` sentinel or activation `status` flip (via `ModuleActivationWriterContract`), `setAfterWrite()` seam for the menu provider (audit/refiner/etag — engine has no menu dep).
- `misc/BAGArt/telegram-platform-module/src/Settings/DatabaseSettingsStorage.php` — `scopesWithSettings()` enumeration (legacy-parity ordering, enablement-only scopes as `[]`, platform rows excluded, sentinel excluded from maps); `all()` cross-module fieldId-collision fix.
- `misc/BAGArt/telegram-platform-module/src/Activation/ModuleActivationReader.php` — `isEffectivelyEnabledForChat()`: platform gate → `{chatId}:__enabled__` (bool only) → `descriptor->defaultChatEnabled === false ? false` (chat-default-OFF modules, Q11-A) → bot status → descriptor default; total decode, no exceptions.
- `misc/BAGArt/telegram-platform-module/src/Activation/EngineModuleEnablement.php` — dispatch miss-path now chat-aware (memo/TTL/refresh unchanged — already chat-keyed).
- Retargeted call sites: antispam `AntispamChatsController`, `AntispamUserListsController`, `Commands/BlocklistSyncCommand`; summarizer `SummarizerSettingsService`, `SummarizerDigestsCommand`; `TtsSettingsService`; `SttSettingsService`; management `TgModulesDoctorCommand` (dual-storage scan, legacy branch guarded by `Schema::hasTable`).

## Architecture Decisions

- **Contract on lib, not menu writer / engine storage** — modules require only `telegram-bot-lib`; the driver binding selects the impl; menu's `EngineSettingsWriter` drops `$chatId` (chat writes impossible), engine storage would bypass the driver switch.
- **Dual-driver impl is forced by PHP interface satisfaction** — "engine-only" is inexpressible while the legacy service implements the contract; legacy impls kept thin (~40 LOC) because legacy = fixture/unmigrated-dev support only.
- **Merge lives in the adapter, not the storage** — `all()` chat-only/bot-only semantics stay a pinned primitive; contract-level inheritance belongs to the `ModuleSettingsContract` impl.
- **Chat enablement read goes through `ModuleActivationReader`, not settings storage** — single query (`rowFor` already returns `module_settings`), registry/platform gate preserved; a raw settings read could re-enable a platform-disabled module.
- **Test pins kept (Q5), gap closed by a seam test** — full unpin ≈ 60–70 red tests blocked on fixture rework; `tests/Feature/ModuleSettingsEngineModeTest` is the engine-mode lane (prod schema, no pins) covering read merge + module write round-trip.
- **Reserved `enabled` never lands in settings maps** — parity both directions (legacy mirror + engine sentinel); non-bool values ignored on both drivers.
- **Raw-scope merge, never inherited maps** — prevents materializing bot/platform defaults into chat rows on save.

## Tests

- `tests/Feature/ModuleSettingsEngineModeTest.php` — engine lane: prod-schema guard, chat-over-bot merge, real module (`TtsSettingsService::patch`) round-trip incl. reserved `enabled` + null-removal + enumeration.
- `misc/BAGArt/telegram-platform-management/tests/Services/SettingsWriteTest.php` — legacy impl (6 new: merge/removal/enabled/enabled-bot/enumeration/bot-scope read).
- `misc/BAGArt/telegram-platform-module/tests/` — `EngineSettingsAdapterTest`, `DatabaseSettingsStorageScopesTest`, `EngineChatLevelEnablementTest` (9 cases: fallback, chat-wins, platform gate, JSON shapes, memo isolation).
- All 8 retargeted call-site suites unchanged: antispam 233, summarizer 59, tts 132, stt 81, management 333, lib 569; full gate `composer test` EXIT 0 with root 5180 passed.

## Integration Points

- Menu provider should register the engine adapter's `setAfterWrite()` hook (audit `BotModuleSettingChanged` + refiner invalidate + etag bump) — parallel to the legacy `setEpochBumper` registration; NOT yet wired (follow-up, menu scope).
- Post-analysis (new lib API): no other in-repo consumers found — mafia is already read-only contract-based, menu writes via its own `SettingsWriterContract`; no propagation task needed.
- Root `tests/Pest.php` pins intentionally unchanged (legacy fixtures); engine coverage = the seam test.

## Known Limitations

- Engine writes do not refresh the `EngineModuleEnablement` 60s memo — cross-process toggle staleness up to TTL (pre-existing engine behavior; legacy refreshes immediately). Summarizer opts in locally: `SummarizerSettingsService::patch()` refreshes after `enabled` writes (Q11).
- `TgBot::enablements()` relation (management model) still points at the legacy table — no callers found; candidate for removal.
- ~~`SummarizerSettingsService::get()` reconstructs `enabled` via `isEnabled + chatsWithSettings`~~ **RESOLVED 2026-10-01 (Q11-A)** — the reconstruction and the per-read extra query are gone; collection gates on `isEnabled()` with descriptor `defaultChatEnabled: false`.
- Widening `settingsFor($chatId)` to nullable is a BC break for external `ModuleSettingsContract` implementors (only two in-repo).
