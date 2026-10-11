# ADR-005: Settings Storage Contract and Channel Fate

> Status: accepted
> Date: 2026-10-08

## Context

The platform migrated module enablements and settings out of the legacy `tg_module_enablements` table into `bot_module_activations.module_settings` (migrations `2026_09_20_000001`/`000002`, the latter dropping the legacy table unconditionally — prod tables already dropped), but the migration was only half-landed: runtime code in four modules (antispam, summarizer, tts, stt) still read and wrote the legacy table through the Eloquent model, so those writes throw SQL errors in prod and are invisible to engine-driver reads. That gap forced the first question — how wide the retargeting scope must be — and, because the lib `ModuleSettingsContract` is read-only while the only existing write paths live in the menu package (`SettingsWriterContract`) and the engine package (`SettingsStorageContract`), neither of which the four modules depend on, the second question — where module chat-settings writes must live under the strict-contracts rule.

The engine driver's read side was separately incomplete. Per-chat enablement had been migrated into `"{chatId}:__enabled__"` keys but `EngineModuleEnablement::isEnabled` ignored the chat dimension, killing every per-chat gate (antispam defense-in-depth, summarizer/tts/stt `isEnabled`, and module dispatch itself via `RegisteredUpdateProcessorSelector`); and `EngineSettingsAdapter::settingsFor()` returned chat-only maps, contradicting the contract docblock's chat → bot → platform inheritance chain and hiding bot-level admin-menu settings from runtime readers. Both read-side behaviors had to be pinned down before dual-driver writes could be honest.

Two structural questions framed the rest. First, adding write/enumeration methods to `ModuleSettingsContract` forces the legacy `TgModuleEnablementService` (which implements it) to satisfy them — so legacy-vs-engine-only support was decided, not chosen; and the module test suites' legacy pinning meant CI verified a mode that no longer exists in prod, masking the entire migration gap. Separately, Direction D2's "two parallel settings contribution channels" premise collapsed under round-3 sizing: Channel B (`settingsScreens`, config-file storage via `ConfigFileAccessContract`) 500s in production (contract bound nowhere), its writer targets a file no runtime code reads, and the field matrix showed almost no duplication to unify — leaving a repair-vs-retire fork that no repo artifact could resolve and that went to the owner, answered 2026-10-07.

## Decision

### Settings-migration scope (source question: `settings-migration-scope`, resolved 2026-09-29)

**Option A** — retarget all four modules (antispam, summarizer, tts, stt) off the legacy table, plus a scope addition found by research: `misc/BAGArt/telegram-platform-management/src/Commands/TgModulesDoctorCommand.php:29` (unconditional legacy query, same bug class). Retargeting goes through the 3-method contract extension from the `settings-write-contract-surface` decision, implemented on **both drivers**. Rejected: Option B (antispam only — matches the SDD note literally but leaves summarizer/tts/stt broken after the table drop) and Option C (antispam read+write only — narrower than A, same class of bug left behind in the other three). Binding obligations carried from the decision's findings: the scope is 7 source files + 3 docblock-only files + the doctor command; the `phpstan-baseline.neon:1597-1601` entry becomes unmatched and must be removed or PHPStan fails; stale comments in `tests/Pest.php:61-69`, three module Pest headers and docblocks must be cleaned; tests calling `patch(['enabled' => ...])` require the reserved `enabled` key to flip enablement, not just settings.

### Settings write-contract surface (source question: `settings-write-contract-surface`, resolved 2026-09-29)

**Option A (research-refined)** — extend lib `ModuleSettingsContract` with exactly three methods, implemented on **both** drivers:

```php
settingsFor(string $moduleId, string $botId, ?int $chatId = null): array   // widened, chat→bot merge
patchSettings(string $moduleId, string $botId, ?int $chatId, array $patch): void  // merge; null value = remove; reserved 'enabled' flips enablement
chatsWithSettings(string $moduleId, ?string $botId = null): array  // explicit (non-inherited) per-scope settings
```

Rejected: menu `SettingsWriterContract` (per-chat patch is functionally impossible under the engine driver — live `EngineSettingsWriter` intentionally drops `$chatId`; CAS/etag unused by all four module callers) and engine `SettingsStorageContract` (bypasses the driver switch so legacy/unmigrated DBs never see the writes; no events; adds an engine dependency). Binding obligations: engine writes get an **after-write closure hook** (mirroring the `setEpochBumper` precedent) so audit event/refiner/etag do not go silent; the `patchSettings` merge must read the **raw** scope map, not `settingsFor()` (inherited), or bot/platform defaults get materialized into chat rows on first save; the widened nullable `settingsFor` and `chatsWithSettings` cover bot-scope read and enumeration respectively.

### Chat-level enablement dispatch (source question: `engine-chat-level-enablement`, resolved 2026-09-29)

**Option A** — `EngineModuleEnablement::isEnabled` honors `"{chatId}:__enabled__"` via a new `ModuleActivationReader::isEffectivelyEnabledForChat(botId, moduleId, chatId)` (single query through the existing `rowFor()`; precedence: platform gate → chat override only when `is_bool` (never `(bool)"false"`) → bot status → descriptor `defaultEnabled`; **absent key = bot-level decision, never default-true**, since `?? true` would re-enable TTS/STT/antispam everywhere). One-line change at `EngineModuleEnablement:39` plus docblock; memo/`refresh()` unchanged (already chat-keyed); decode path stays total (no `JSON_THROW`). Test precondition: apply migration `Persistence/2026_09_11_000001` in `EngineSqliteTestCase` (its `setUp` runs only the 2026_08_27 migration, so `module_settings` does not exist). Rejected: Option B (keep bot-level only and drop the per-chat `enabled` column — strands data the migration was built to preserve) and Option C (leave display-only — a silent no-op). Carried obligation: the write side is fixed by the migration-scope decision; a data audit is still due for bot-level `status` pollution fed by the migration's chat branch.

### Settings read inheritance (source question: `engine-settings-read-inheritance`, resolved 2026-09-29)

**Option A** — chat-overrides merged over bot-level settings inside `EngineSettingsAdapter::settingsFor` (bot-level `all($botId)` plus chat overlay from `all($botId, $chatId)`, chat keys win; descriptor defaults remain the caller's fallback). `DatabaseSettingsStorage::all()` semantics stay unchanged — its chat-only/bot-only separation is pinned by `DatabaseSettingsStorageTest` and the storage primitive stays a primitive; contract-level inheritance belongs to the adapter. The same change fixes the latent cross-module `fieldId` collision at `DatabaseSettingsStorage:96` (key by `moduleId."\0".$fieldId`). Rejected: Option B (keep chat-only reads and correct the docblock — contradicts legacy parity and forces changes in all four modules' call sites) and merging inside `DatabaseSettingsStorage::all()` (pinned by 5 separation test cases; `all()` has exactly one production caller, so the adapter fix has zero side effects on admin reads). Carried obligation: `__enabled__` sentinel keys leak into chat maps as field `__enabled__` — coordinate with the chat-level enablement decision; a new `EngineSettingsAdapter` test file is required (zero coverage exists repo-wide); deploy will shift effective values because bot-level admin-menu settings become visible to runtime under engine for the first time.

### Legacy driver dual support (source question: `legacy-driver-dual-support`, resolved 2026-09-29)

**Option A — dual support, forced by interface satisfaction**: the new `ModuleSettingsContract` methods are implemented on legacy `TgModuleEnablementService` too (~25–40 LOC on the existing `upsert()`/`scopedRows()` paths; **no new CAS/epoch logic** — legacy is test-fixture support for unmigrated dev DBs only). Rejected: engine-only, because it is not expressible — `TgModuleEnablementService` implements the contract and would PHP-fatal on the new methods; each engine-only variant (separate writer contract → `app()->bound()` duck-typing violating strict-contracts plus 5 test-pin rewrites; legacy throwing `LogicException` → reds the entire legacy-pinned matrix) costs more than A. Binding cleanup obligations: delete the stale "INTENTIONAL BRIDGE" comment (management provider `:66-70`) and collapse the duplicated driver read (`:52` + `:71`). Scope limit: **settings only** — chat-scoped *enablement* stays engine-only per the `engine-chat-level-enablement` decision; dual support is only honest if the read-inheritance and chat-enablement decisions land, otherwise the same contract answers differently per driver.

### Module suite engine pinning (source question: `module-suite-engine-pinning`, resolved 2026-09-29)

**Option B (research-flipped from A)** — keep all four legacy pin blocks in root `tests/Pest.php` untouched (0 files, 0 test churn), and close the masking gap with a new seam test `tests/Feature/ModuleSettingsEngineModeTest.php`: guard that `Schema::hasTable('tg_module_enablements') === false` (protecting the lane), Test A = first engine-mode `settingsFor` coverage (green), Test B = a module `patch()` round-trip sentinel that skips with a message naming this question until Wave A lands. Keep `tests/Pest.php:81-83` `require_once` — it is the sole loader of the package Pest pins. Rejected: Option A (full unpin), because it is a project, not a toggle — module `src` still writes the legacy model directly, producing an existent-nowhere "write legacy, read engine" mode with an estimated 60–70 red tests, and chat-level semantics could not even be re-seeded before the chat-enablement decision. Binding obligation: full unpin is recorded as blocked on Wave A src migration + bot-activation fixture helper + the `engine-settings-read-inheritance` and `engine-chat-level-enablement` decisions landing.

### Channel B repair vs retire (source question: `settings-screen-channel-repair-or-retire`, resolved 2026-10-07)

**Option 1 — retire Channel B** (decided 2026-10-07; the owner accepted the agent recommendation, recorded as the final state).

- Channel A (`webUi` manifests) survives as the **single** module-settings contribution channel.
- The deletion itself executes via task **B2/B3a** (Branch T of `b2-b3a-settings-surfaces`), after **W-D2** (`module-extensibility-w-d2-settings-channels`) runs its retire path — decision/prep + D2-b only, handing off to B2/B3a; **W-D2 deletes nothing**.
- Option 3 (fund a config-layer write+read mechanism, 12 `config:cache` constraints, 0/30 targets writable today, key-maps for only 16/30 fields) was **rejected by the owner**; it may re-enter only as a NEW question if platform-level web config editing is ever funded.
- Options **2 (bind-only)** and **4 (fold into Channel A)** remain excluded on evidence/contract grounds: option 2 would stop the 500 while all 30 fields still void-write (honest screens 0/5 — a working-looking UI that changes nothing is worse than a 500); option 4 would move all 30 fields across layers, violating the config-vs-runtime storage rule, and buys nothing because the field matrix shows 0 identical field sets.
- Sequencing: independent of Q1 — D2 closes as "Channel A only; Channel B retired via B2" (adjusts D2's content, not its order).

## Consequences

**Contracts and surfaces touched.**
- `ModuleSettingsContract` (telegram-bot-lib) gains exactly three methods — widened nullable `settingsFor`, `patchSettings`, `chatsWithSettings` — with two real implementors each: legacy `TgModuleEnablementService` and engine `EngineSettingsAdapter`.
- `ModuleActivationReader` (engine) gains `isEffectivelyEnabledForChat`; `EngineModuleEnablement:39` switches to it; `EngineSettingsAdapter::settingsFor` becomes the inheritance-merge site while `DatabaseSettingsStorage::all()` semantics stay pinned.
- Engine writes acquire an after-write closure hook (mirror of the `setEpochBumper` precedent) so audit/refiner/etag survive.
- Legacy driver keeps settings support as test-fixture-only, with the stale INTENTIONAL BRIDGE comment and duplicated driver read removed.
- Channel B is retired: 5 `settingsScreens` config entries, engine `SettingsScreenContribution` + `ConfigFileAccessContract`/`JsonConfigFileAccess`, management `SettingsScreenController` + its 3 `management.settings.*` routes, 2 Inertia TSX pages, and its controller test.

**What depends on these decisions.**
- Antispam, summarizer, tts and stt settings/enablement code paths (including `AntispamPipeline`, the three `patch()` services, `BlocklistSyncCommand`, `SummarizerDigestsCommand`) and `TgModulesDoctorCommand` — all now route through the contract on both drivers.
- Module dispatch itself: `RegisteredUpdateProcessorSelector:198` calls `isEnabled(moduleId, botId, chatId)`, so chat-level enablement gates summarizer/tts/stt processors in chats.
- All 10 chat-scoped `settingsFor` callers across 5 modules receive effective merged maps; `DbRuleOverrides` precedence is unchanged.
- Root test matrix: the four legacy pin blocks stay (module suites: antispam 233, summarizer 59, tts 132, stt 81, lib 569), with the new engine seam test as the only engine-mode lane for the contract; management's own service tests cover the legacy driver.
- D2's scope: closes as "Channel A only", independent of Q1's sequencing.

**Follow-ups.**
- Wave A settings-migration work (retargeting the 7+1 files) plus removal of the unmatched `phpstan-baseline.neon:1597-1601` entry and stale test/docblock comments.
- New tests: `tests/Feature/ModuleSettingsEngineModeTest.php`, an `EngineSettingsAdapter` suite, and `EngineSqliteTestCase` applying `Persistence/2026_09_11_000001`.
- Data audit for bot-level `status` pollution introduced by the migration's chat branch; deploy watch for effective-value shifts once bot-level settings become visible under engine.
- Full unpin of the legacy test pins remains blocked on Wave A src migration + bot-activation fixture helper + the read-inheritance and chat-enablement fixes.
- Deletion of Channel B via B2/B3a after W-D2's D2-b + handoff; option 3 (config-layer write+read mechanism) may only return as a newly raised question.
- En route defects recorded for a bug task: proxy quota fields (`max_import_batch`, `max_concurrent_audits`) persisted but unenforced, and `ProxySettingsController::update()` discarding its payload.
