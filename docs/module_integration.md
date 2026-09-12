# Module Integration — Remaining Items

> Completed phases removed (2026-09-13). Full history in git log.
> Status tracking: `docs/STATUS.md`.

## Deferred Items

### Phase 4.4 — Remove legacy enablement service bindings
- **Status:** 🔴 Deferred
- **Reason:** Settings column only exists in `tg_module_enablements` table; removing the legacy binding would break settings reads
- **Blocked by:** Database migration to move settings to a new table or engine-owned storage

### Phase 6.2 — CI workflows
- **Status:** ✅ Done (added `module-validation.yml`)
- **Note:** Dockerfile `REPOS` variable still needs manual sync when adding new modules

## Cross-Cutting Concerns

### Testing
- `php artisan tg:modules:validate` must pass
- `php artisan tg:modules:doctor` must report no issues

### Rollback
- Each phase should be reversible by flipping config values
- No destructive migrations until Phase 6 is verified
