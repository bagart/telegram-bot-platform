# ADR-006: Summarizer Enablement Model

> Status: accepted
> Date: 2026-10-08

## Context

The settings-migration retarget required the summarizer to collapse its three module `patch()` bodies into a single `ModuleSettingsContract::patchSettings()` call, but that contract reserves the literal key `enabled`: it never lands in the settings map — it flips enablement instead. The summarizer carried two conceptually different flags (`ModuleEnablementContract::isEnabled` defaulting ON with zero rows, and `SummarizerSettings::$enabled` defaulting OFF), both pinned by behaviour tests, and the retarget dropped the dual-write that kept the per-chat opt-in gate alive. Worse, under the platform's actual driver (`enablement_driver=engine`) that gate was already dead in production — legacy-pinned tests were masking a real defect.

The product answer chosen was "one flag": `isEnabled('summarizer', bot, chat)` becomes the single source of truth for collection, with chat-level default OFF while bot-scope/discovery default stays unchanged. That answer forced four coupled decisions. First, **no chat-default mechanism exists** in either enablement driver, so a mechanism had to be invented without flipping the forbidden `defaultEnabled=false` and without breaking the chicken-and-egg: a fresh chat that fails chat-level `isEnabled()` never receives the `/summarizer` menu — the module's only opt-in surface — and drops `my_chat_member`, killing inviter tracking. Second, the reserved `enabled` settings key had to be reconciled with the new single-flag semantics. Third, because chat default OFF would strangle dispatch, the selector's chat-gated command and chat-member paths needed an exemption rule. Fourth, the Feature suite ran in mixed mode (prod config = engine, tests force-bound to legacy with a recreated dropped table), so it validated semantics prod never used. Finally, the web settings form exposed an `enabled` field that never reached enablement storage on either driver — a second, non-functional enablement control that contradicted the one-flag goal.

## Decision

### Chat default OFF mechanism (source question: `summarizer-chat-default-mechanism`, resolved 2026-10-01)

Option **A**: new optional descriptor field `defaultChatEnabled` (default `true`; summarizer sets `false`), engine reader chat branch = platform gate → explicit chat bool → (`defaultChatEnabled === false` ? `false` : row status / `defaultEnabled`), legacy parity in `TgModuleEnablementService`. Bot scope untouched (`defaultEnabled` stays `true`).

- Rejected **B** (module-local composition): `isEnabled` would not be the single source of truth (two conditions), the uncached `chatsWithSettings` query stays on the hot path, "disabled by default per chat" stays aspirational.
- Rejected **C** (flip descriptor `defaultEnabled=false`): forbidden by the task boundary and factually inert — once the bot row exists, all chats inherit ON.

Binding obligations: the chat branch MUST apply `defaultChatEnabled` even when a settings row exists (otherwise the first settings write flips every chat ON); no `ModuleSettingsContract` widening; coupled with the dispatch decision below (D1) — without it there is no opt-in path at all.

### Per-chat opt-in vs the reserved `enabled` settings key (source question: `summarizer-chat-optin-vs-reserved-enabled`, resolved 2026-10-01)

Decision by the **User**: **E → A** (fix the dead prod gate first as its own task, then land option A semantics) — `isEnabled()` as the single flag, chat-level default OFF for summarizer, the two behaviour-pinning tests (`SummarizerSettingsTest`, `SummarizerMenuFlowTest`) rewritten.

- Rejected **B** (second stored key `chat_opt_in`): invents a persisted field with web UI schema and backfill of existing rows.
- Rejected **C** (widen `ModuleSettingsContract`, drop the `enabled` reservation or add raw-scope read): touches the lib contract owned by another agent; remains rejected — no settings-contract changes were made.
- Rejected **D** (carve summarizer out of the retarget): leaves the legacy dual-write and the prod SQL error against the dropped table.

Binding obligations: the dead per-chat opt-in gate under the engine driver becomes its own task first (`summarizer-one-flag-optin`), landing option A semantics; no second stored key; no lib-contract widening of `ModuleSettingsContract`.

### Dispatch exemption for commands and chat-member updates (source question: `summarizer-dispatch-exemption`, resolved 2026-10-01)

Option **D1**: `ModuleEnablementContract::isEnabled(string, string, ?int $chatId = null)` — `null` = bot-level decision (engine `isEffectivelyEnabled()`, legacy bot/platform rows → `defaultEnabledOf()`); the selector dispatches the command path and the `ChatMemberUpdatedTypeDTO` regular path on bot scope; collection stays chat scope. Deliberate lib contract widening, distinct from rejected Q11-C (`ModuleSettingsContract`).

- Rejected **D2** (unconditional command exemption, no contract change): a bot-wide-disabled module would still answer commands, and `my_chat_member` (not a command) stays broken — a separate hack needed.
- Rejected **D3** (no dispatch change; opt-in moves out of chat to a Mini App hub): in-chat `/summarizer` permanently dead pre-opt-in, inviter tracking dead, and the menu fix is itself a cross-module change.
- Rejected **D4** (no exemption at all): chat default OFF makes in-chat opt-in impossible; only DB/manual writes could activate a chat — unacceptable shipped state.

Binding obligations: bot-disabled suppression semantics preserved (bot disabled at bot scope → commands suppressed); `CommandRoutingE2ETest:129-156` rewritten to the new AC (chat-disabled + bot-enabled → command dispatches; suppression covered at regular-processor level by `ModuleEnablementTest`); the widening of `ModuleEnablementContract` is called out explicitly as deliberate.

### Test-suite enablement driver (source question: `summarizer-suite-driver`, resolved 2026-10-01)

Option **E1**: the summarizer Feature suite switches to the engine driver — drop the `LegacyEnablementSchema` hook and both legacy contract binds from `tests/Pest.php`; engine tables are already in the test schema; tests use `refresh()` discipline after every enablement write.

- Rejected **E2** (keep legacy pin): rewritten tests would validate chat-default OFF against a driver prod does not use; the mixed mode (engine config + legacy bindings) stays confusing.
- Rejected **E3** (both lanes / matrix): roughly doubles suite work — overkill for the task.

Binding obligations: keep the root `tests/Pest.php` `require_once` of the module Pest.php harmless; legacy driver keeps its own coverage in management/lib suites.

### `enabled` field in the summarizer web settings form (source question: `summarizer-web-enabled-field`, resolved 2026-10-01)

Option **F1**: remove `enabled` from the summarizer form schema (field, validate branch, five locale keys, `isConfigured` docblock). Enablement surfaces remain: in-chat panel (reserved-key patch) + the existing hub per-chat toggle.

- Rejected **F2** (keep the field and teach menu `saveSettings` to route `enabled` → `setEnabled`): cross-module change affecting every module form, needs a chat-scope decision in bot-only-by-design `EngineSettingsWriter` — large blast radius for one checkbox that duplicates the hub toggle.

Binding obligations: the form carries pure settings only (interval, budget, provider…); no second, non-functional enablement control may exist while the one-flag goal holds.

## Consequences

Contracts touched: `ModuleEnablementContract` widened with a nullable `$chatId` (bot-scope read; nullability follows the established `refresh()` pattern) plus both driver implementations; `ModuleSettingsContract` deliberately untouched (its widening was rejected); `TgModuleDescriptor` gains `defaultChatEnabled` with legacy parity in `TgModuleEnablementService` and the engine `ModuleActivationReader` chat branch.

Dependents: `RegisteredUpdateProcessorSelector` command and chat-member paths now dispatch at bot scope while collection stays chat-gated; summarizer gate, renderer, `CollectMessageProcessor`, `AdminMenuRenderer`, `SummarizerSettingsService`, and `SummarizerWebUi` are keyed to the single `isEnabled` flag; the Feature suite asserts prod (engine) semantics including `refresh()` after enablement writes; `CommandRoutingE2ETest:129-156` carries the rewritten AC.

Follow-ups: SDD `summarizer.md` § One-Flag Opt-In is the recorded design; the settings-migration known limitation (uncached per-message `chatsWithSettings` query) is retired; the en-route antispam missing-import defect (prod fatal on every gated update) is guarded by `ModuleProcessorSurfaceTest` and recorded under the slug `antispam-moduleid-missing-import`.
