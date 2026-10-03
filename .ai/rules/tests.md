---
paths:
  - 'misc/BAGArt/*/tests/**'
---

# Tests

## No pest-arch toImplement on platform classes (vendor mode)
Never use `->toImplement(...)` (pest-arch `expect(...)->toImplement`) on platform classes: in vendor mode ObjectDescriptionBase copies only name/uses, not path, so `VendorObjectDescription` fatals with "$path must not be accessed before initialization". Use `expect(class_implements(X::class))->toContain(Y::class)` (or a plain test + class_implements loop) instead. Decision: docs/questions/pest-arch-toimplement-vendor.md (Q2=A).
