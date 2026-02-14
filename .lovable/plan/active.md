# Active & Future Phases

**Updated: 2026-02-14**

---

## Current Status: Backlog Audit Complete ✅

All completed work moved to `completed/` folders. Pending work consolidated into `plan.md`.

---

## Completed Tracks

### Golang Enum Migration (Feature G) ✅ (2026-02-14)
8 typed string enums with IsEqual(), String(), and domain helpers across all types.

### File Size Remediation ✅ (2026-02-13)
Main plugin split from 5,604 → ~270 lines. 20 traits + 2 standalone files.

### Long Function Fix (Phases 1–16) ✅ (2026-02-13)
~100 functions refactored, ~170 helpers extracted. 15-line limit enforced.

### RISEUP_ Constant Migration ✅ (2026-02-13)
3 consumer files migrated, constants-compat.php bridge removed.

### camelCase Method Migration (Feature I) ✅ (2026-02-14)
All core domains migrated including ORM layer and semantic boolean guards.

### Nested-If Flattening ✅ (2026-02-14)
8 phases, ~42 violations fixed across all subsystem files.

### PHP Coding Standards (Feature H) ✅ (2026-02-13)
200-line file limit, 15-line function limit, zero raw function negations.

### S-001 & S-004: Final Spec Documentation ✅ (2026-02-09)
### PHP Plugin v1.36.1 — Circular Dependency Fix ✅ (2026-02-09)
### Pre-flight Plugin Guard (S-010) ✅ (2026-02-09)
### 10-Phase DRY Refactoring ✅ (2026-02-09)
### PHP Plugin Refactoring Phases 1–5 ✅ (2026-02-07)
### Go Backend Phases 6–10 ✅ (2026-02-07)
### Feature Phases 1–14, 33–40 ✅ (2026-02-05 to 2026-02-06)

---

## Open Suggestions

None — all 18 suggestions are completed. 🎉

---

## Open Questions

1. **Remote Plugin Backups**: Store on WP site or download locally?
2. **Bulk Quick Publish**: Add "Quick Publish Selected" for multiple plugins?
3. **True Diff Comparison**: Compare with remote files for accurate modified/deleted counts?
4. **PathType Architecture**: Single `PathType` enum vs. split into 4 domain enums?

---

*Next tasks should come from the consolidated backlog in `plan.md`.*
