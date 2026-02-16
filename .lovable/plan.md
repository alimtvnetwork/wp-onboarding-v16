

# Plan: Doc Updates + Three New Code Style Rules + PHP Enforcement

## ✅ COMPLETED — Part A: Spec/Doc Files — Rename PathUtils to PathHelper

All 12+ doc/spec files updated: `RiseupPathUtils` → `PathHelper`, `CleanerUtilsTrait` → `CleanerHelperTrait`, `RestoreUtilsTrait` → `RestoreHelperTrait`.

## ✅ COMPLETED — Part B: Three New/Updated Code Style Rules

- **B1**: Rule 4 updated to include `throw` alongside `return`
- **B2**: Rule 8 added — No leading backslash on `Throwable`
- **B3**: Rule 9 added — Multi-line parameters when >2 params with trailing comma

All three rules added to `spec/01-coding-guidelines/code-style.md` and cross-referenced in PHP specs.

## 🔵 PENDING — Part C: PHP Code Enforcement

### C1: Remove `\` from `Throwable` — ~20 PHP files

All `\Throwable` → `Throwable` in catch blocks and type hints.

### C2: Multi-line params for functions with >2 params — ~26 PHP files

Reformat all function signatures with 3+ parameters to one-per-line with trailing comma.

### C3: Blank line before `return`/`throw` enforcement in PHP

Audit and fix all catch/if blocks missing blank line before return/throw.

---

**Next step:** Execute Part C (C1 → C2 → C3) across all PHP files.
