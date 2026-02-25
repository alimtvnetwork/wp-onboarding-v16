# 06 — R9 Multi-File Array Formatting

> **Created:** 2026-02-23

## Issue Summary

1. **What happened:** Arrays and function calls with >2 items were written on a single line or crammed across two lines, violating R9 (R9b for calls, R9c for array literals).
2. **Where:** `UploadInstallExtractTrait.php` (lines 82–86, 112–115), `UploadPipelineTrait.php` (lines 98–110), `SnapshotSettingsHandlerTrait.php` (lines 31, 44, 53, 62, 74–77).
3. **Symptoms:** Reduced readability; horizontal scrolling required; inconsistent formatting across files.
4. **How discovered:** Full codebase audit searching for inline arrays with >2 items.

## Root Cause Analysis

1. **Direct cause:** Arrays were written inline for brevity during initial development.
2. **Contributing factors:** No automated linting for R9; manual review missed these during prior passes.
3. **Triggering conditions:** Any array literal or function call with >2 items written on fewer lines than items.
4. **Why the spec didn't prevent it:** The spec existed but was not applied retroactively to all existing code.

## Fix Description

1. **What was changed:** All arrays and function calls with >2 items were reformatted to one item per line with trailing comma.
2. **New rules:** No new rules — R9 already covers this. This fix is retroactive compliance.
3. **Why it resolves the root cause:** Each item is now on its own line, matching R9a/R9b/R9c.
4. **Config changes:** None.
5. **Logging/diagnostics:** None required.

## Prevention and Non-Regression

1. **Prevention rule:** Every array literal, function signature, and function call with >2 items must have one item per line with a trailing comma (R9).
2. **Acceptance criteria:** No PHP array or call with >2 items should have multiple items on a single line.
3. **Guardrails:** Manual review during all PRs; future linting automation recommended.
4. **Spec references:** `/spec/01-app/formatting-rules-reference.md` (R9a, R9b, R9c).

## Done Checklist

- [x] Spec already covers this under `/spec/01-app/formatting-rules-reference.md`
- [x] Issue write-up created under `/spec/02-app-issues/06-r9-multi-file-array-formatting.md`
- [x] Memory updated with prevention rule
- [x] Acceptance criteria documented above
- [ ] Iterations: not applicable (single-pass fix)
