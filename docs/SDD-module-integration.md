# Host Module Integration — SDD

## Design

The module engine owns declarative registration and dependency-ordered provider boot. Module descriptors contribute commands, routes, schedules and frontend pages rather than relying on provider-side configuration mutations. Host page generation consumes those declarations.

The integration status document records bootstrap takeover, proxy onboarding, audit-stream integration, routing cleanup, page generation and CI/Docker integration as completed. These are historical implementation claims, not a fresh test or deployment certification.

## Retained Decisions

- Engine activation is the enablement source selected by host configuration. Legacy settings storage remains a separate compatibility dependency: switching activation does not migrate settings.
- Module validation has a dedicated CI workflow, `module-validation.yml`. Docker repository enumeration still requires maintenance when modules are added.
- Validation and doctor diagnostics are acceptance gates for module integration.
- Cutovers should remain reversible through configuration. Destructive storage changes require verified migration and rollback behavior.
- Package publication and production path resolution are separate acceptance concerns from successful development-mode boot.

## Remaining Work

[Module integration](module_integration.md) tracks legacy settings-binding retirement and Docker repository-list maintenance. `STATUS.md` is a historical summary; unresolved or conflicting completion claims require verification, not deletion.
