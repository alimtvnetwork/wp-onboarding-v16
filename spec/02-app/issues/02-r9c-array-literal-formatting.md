# Issue: R9c Array Literal Formatting Violations in Snapshot Traits

> **ID:** 02-r9c-array-literal-formatting
> **Date:** 2026-02-22
> **Category:** Code Style / Formatting
> **Status:** Resolved

---

## Issue Summary

1. **What happened:** A PHP array literal with 3 items was written on a single line, violating rule R9c (arrays with >2 items must be line-by-line).
2. **Where it happened:** `ManagerImportValidationTrait.php` line 36 — `array('filename', 'tables', 'scope')`.
3. **Symptoms and impact:** Reduced readability. No functional impact, but inconsistency with the project's formatting standard.
4. **How it was discovered:** S-025 audit of all `Snapshot/Traits/` PHP files for R9c violations.

## Root Cause Analysis

1. **Direct cause:** The array was written before R9c was formally enforced.
2. **Contributing factors:** No automated linter enforces R9c at commit time.
3. **Triggering conditions:** Any PHP array literal with 3+ items written on one line.
4. **Why the existing spec did not prevent it:** The formatting rules existed in memory but were not yet applied retroactively to all Snapshot traits.

## Fix Description

1. **What was changed in the spec:** `/spec/01-app/formatting-rules-reference.md` created as the canonical spec for all formatting rules, including R9c.
2. **New rules or constraints added:** R9c explicitly states: PHP array literals with more than 2 items must be written line-by-line with one item per line and a trailing comma.
3. **Why the fix resolves the root cause:** Having the rule in a spec file (not just memory) makes it discoverable and enforceable.
4. **Config changes or defaults affected:** None.
5. **Logging or diagnostics required:** None.

## Prevention and Non-Regression

1. **Prevention rule:** Before committing any PHP file, verify that no array literal has more than 2 items on a single line.
2. **Acceptance criteria:** `grep -Pn 'array\(.*,.*,.*\)' --include='*.php'` returns zero matches in non-vendor files.
3. **Guardrails or linting policies:** A future PHP-CS-Fixer rule could enforce this automatically.
4. **References to updated spec sections:** `/spec/01-app/formatting-rules-reference.md`

## TODO and Follow-Ups

1. None — full sweep of `Snapshot/Traits/` completed with only 1 violation found and fixed.

## Done Checklist

- [x] Spec updated under `/spec/01-app/`
- [x] Issue write-up created under `/spec/02-app/issues/`
- [x] Memory updated with summary and prevention rule
- [x] Acceptance criteria updated or added
- [ ] Iterations recorded if applicable — N/A (single-pass fix)
