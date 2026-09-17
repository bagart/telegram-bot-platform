# Module Integration — Remaining Items

Completed integration decisions are retained in [SDD-module-integration.md](SDD-module-integration.md).

## Phase 4.4 — Legacy Enablement Bindings

- [ ] Migrate settings from `tg_module_enablements` to the agreed replacement storage before retiring the legacy enablement-service bindings.
- [ ] Verify that settings reads remain compatible after activation-source migration.

Status: deferred. The legacy settings dependency blocks removal; this is not an outstanding activation-reader test.

## Docker Repository Enumeration

- [ ] Keep the Dockerfile `REPOS` list synchronized when adding modules, or replace manual enumeration with a verified declarative source.

## Acceptance and Rollback

- [ ] Verify module validation and doctor diagnostics for the remaining integration changes.
- [ ] Verify reversible cutover and storage migration before removing compatibility bindings or applying destructive migrations.
