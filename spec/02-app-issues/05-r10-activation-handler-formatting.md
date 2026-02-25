# Issue: R10 Missing Blank Line Before Control Structures in ActivationHandler

> **ID:** 05-r10-activation-handler-formatting
> **Date:** 2026-02-22
> **Category:** Code Style / Formatting
> **Status:** Resolved

---

## Issue Summary

1. **What happened:** Two `if` statements in `ActivationHandler.php` were not preceded by a blank line after assignment statements, violating R10.
2. **Where it happened:** `includes/Activation/ActivationHandler.php` — lines 53-54 and lines 139-140.
3. **Symptoms and impact:** Reduced readability. Control flow boundaries were not visually separated from preceding assignments.
4. **How it was discovered:** S-030 audit flagged during the deep-scan boot-chain reliability review.

## Root Cause Analysis

1. **Direct cause:** The `if` statements were written immediately after variable assignments without the required blank line separator.
2. **Contributing factors:** The file was written before R10 was formally enforced project-wide.
3. **Triggering conditions:** Any `if`, `foreach`, `switch`, or `match` preceded by an assignment or function call without a blank line.
4. **Why the existing spec did not prevent it:** R10 existed in memory but had not been retroactively applied to `Activation/` files.

## Fix Description

1. **What was changed in the spec:** `../01-app/formatting-rules-reference.md` now explicitly lists R10 with examples.
2. **New rules or constraints added:** R10: A blank line is mandatory before control structures when preceded by statements like assignments or function calls.
3. **Why the fix resolves the root cause:** The spec + completed sweep ensures all existing violations are fixed and future ones are prevented.
4. **Config changes or defaults affected:** None.
5. **Logging or diagnostics required:** None.

## Prevention and Non-Regression

1. **Prevention rule:** Before committing any PHP file, verify that every `if`/`foreach`/`switch`/`match` preceded by an assignment has a blank line separator.
2. **Acceptance criteria:** Visual inspection or grep-based pattern detection for `;\n    if (` (assignment immediately followed by control structure).
3. **Guardrails or linting policies:** A future PHP-CS-Fixer custom rule could enforce R10.
4. **References to updated spec sections:** `../01-app/formatting-rules-reference.md`

## TODO and Follow-Ups

1. None — both violations fixed in S-030.

## Done Checklist

- [x] Spec updated under `../01-app/`
- [x] Issue write-up created under `./`
- [x] Memory updated with summary and prevention rule
- [x] Acceptance criteria updated or added
- [ ] Iterations recorded if applicable — N/A (single-pass fix)
