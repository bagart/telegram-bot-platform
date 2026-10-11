# SDD Index — telegram-bot-platform (host)

> Compressed essence (declarative, no code). STRICT: creating, renaming or editing
> an SDD in this directory ⇒ update the matching row below in the same step.

| Topic | File | Summary |
|---|---|---|
| module-integration | [`module-integration.md`](module-integration.md) | Host integration decisions and compatibility boundaries: bootstrap takeover, W-D1 adoption CLI + module-validation repair, W-D2 Channel-B retirement, W-D4 web activation, B1 catalog-key convention, B2 Channel-B deletion |
| settings-migration-contract | [`settings-migration-contract.md`](settings-migration-contract.md) | Driver-aware ModuleSettingsContract (3 methods, both drivers), engine chat inheritance + chat enablement, 8 retargeted call sites |
| mutation-gate-nightly | [`mutation-gate-nightly.md`](mutation-gate-nightly.md) | Dead mutation gate revived (direct control + --baseline, floor 97), hooksPath fix, dead shims removed; nightly = vendor-install redesign, Q10–Q16 resolved (Q17 ask-queue open); prod-lock tip-freshness control `lock-tip-fresh:prod` + `cmd/deps/check --mode` repair |
