# Module Integration — Phase 2–6 Plan

> Replaces the deleted `docs/tasks/module_integration.md`.
> Status tracking: `docs/STATUS.md`.

## Phase 0–1 (Done)

- Engine bootstrap takeover: `telegram-platform-module` registered in `bootstrap/providers.php`
- All contracts shipped: `TgModuleContract`, `TgModuleRegistrar`, `TgModuleDescriptor`, `TgModuleCapability`
- Registry builder, activation reader, route resolver, settings contracts
- Contribution system: `MenuContributionResource`, `SettingsScreenContribution`, `ContributionResolver`

## Phase 2 — Proxy Onboarding ✅

**Goal:** Enable the Proxy module through the engine's declarative config.

| Task | Status | What |
|---|---|---|
| 2.1 | ✅ | Flip `enabled: true` in `config/tg_modules.php` |
| 2.2 | ✅ | Add `laravelProvider` (ProxyOperationsServiceProvider) |
| 2.3 | ✅ | Declare `commands` (RunCapabilityProbesCommand, LeaseReaperCommand) |
| 2.4 | ✅ | Declare `schedule` (proxy:lease:reap cron) |
| 2.5 | ✅ | Declare `routes` (/proxy command, private chats) |
| 2.6 | 🔴 | Audit streams consumer (event projection to DB) |
| 2.7 | 🔴 | Frontend side-channels (ProxyUi Mini App chunk) |
| 2.8 | 🔴 | Settings screens contribution (§8.3 settings surface) |
| 2.9 | 🔴 | Remove commands from ServiceProvider boot() — moved to config ✅ |

**Notes:**
- ProxyOperationsServiceProvider stays in `bootstrap/providers.php` for config/migrations/bindings (always-on exemption).
- Engine calls `register()` when module is enabled for processors/commands/web-api.
- `defaultEnabled: false` in descriptor — individual bots still need explicit opt-in.

## Phase 3 — Nettools Cleanup 🔴

**Goal:** Fix legacy placeholders and route loading.

| Task | Status | What |
|---|---|---|
| 3.1 | 🔴 | Nettools migration fix (if needed) |
| 3.2 | 🔴 | Remove legacy placeholders from host routes |
| 3.3 | 🔴 | Route loading cleanup — all routes via engine |
| 3.4 | 🔴 | Verify nettools commands work through engine dispatch |

## Phase 4 — Enablement Driver Flip ✅

**Goal:** Switch from legacy enablement to engine-managed activation.

| Task | Status | What |
|---|---|---|
| 4.1 | ✅ | Flip `enablement_driver` from `legacy` to `engine` |
| 4.2 | 🔴 | Migrate seeders from provider-level to config-level |
| 4.3 | 🔴 | Verify engine activation reader works for all modules |
| 4.4 | 🔴 | Remove legacy enablement service bindings (deferred — settings stay in legacy table) |

**What was done:**
- Management provider's `ModuleEnablementContract` binding is now conditional — skipped when `enablement_driver='engine'`, allowing engine's `EngineModuleEnablement` binding to win
- `ModuleSettingsContract` always stays bound to legacy service (settings column only exists in `tg_module_enablements`)
- `TgModuleToggleCommand` (used by `tg:module:enable`/`tg:module:disable`) now supports both drivers:
  - Engine mode: uses `ModuleActivationService`, requires `--bot`, rejects `--chat`
  - Legacy mode: uses `TgModuleEnablementService`, supports 3-tier scope
- Config flipped to `'enablement_driver' => 'engine'`

## Phase 5 — Manifest-Driven Page Generation 🔴

**Goal:** Delete host module pages, use engine manifest generation.

| Task | Status | What |
|---|---|---|
| 5.1 | 🔴 | Verify `frontendPages` config works for all modules |
| 5.2 | 🔴 | Verify `pageGenerators` (menu:pages) generates correctly |
| 5.3 | 🔴 | Delete host-level module page files |
| 5.4 | 🔴 | Remove `modules_frontend_pages` / `modules_page_generators` side-channels |

## Phase 6 — Docker/CI Cleanup 🔴

**Goal:** Zero module refs outside config, doctor in CI.

| Task | Status | What |
|---|---|---|
| 6.1 | 🔴 | Docker: verify all modules load correctly in container |
| 6.2 | 🔴 | CI: add `tg:modules:doctor` to CI pipeline |
| 6.3 | 🔴 | Audit: zero module references outside `config/tg_modules.php` |
| 6.4 | 🔴 | Remove any remaining hardcoded module paths |
| 6.5 | 🔴 | Verify all modules self-register via engine |

## Cross-Cutting Concerns

### Testing
- Each phase must include tests for the specific integration
- `php artisan tg:modules:validate` must pass after each phase
- `php artisan tg:modules:doctor` must report no issues

### Rollback
- Each phase should be reversible by flipping config values
- No destructive migrations until Phase 6 is verified

### Dependencies
- Phase 2 must complete before Phase 4 (proxy must be enabled to test enablement flip)
- Phase 3 can run in parallel with Phase 2
- Phase 5 depends on Phase 4 (manifest generation needs engine activation)
- Phase 6 depends on all previous phases
