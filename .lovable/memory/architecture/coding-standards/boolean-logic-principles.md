# Memory: architecture/coding-standards/boolean-logic-principles
Updated: 2026-02-26

Strict boolean logic standards apply across all languages (PHP, TS, Go). Six principles are enforced (spec: `spec/03-coding-guidelines/boolean-principles.md` v2.1.0):

1. **P1 — `is`/`has` prefix**: All boolean identifiers must use `is` or `has` prefixes.
2. **P2 — No negative words in names**: The words `not`, `no`, `non` are absolutely banned from boolean variable/function/method names. Always use a positive semantic synonym instead (e.g., `isPending` not `isNotReady`, `isAbsentFromList` not `isNotInList`, `isErrorListClear` not `isNoRecentErrors`). Double negatives (`!isNot...`) are the worst form.
3. **P3 — Named guards over raw negation**: Never use `!` on function calls at call sites; use positively named guard functions (e.g., `isFileMissing()` not `!file_exists()`).
4. **P4 — Extract complex expressions**: Conditions with 2+ operators must be extracted into named boolean variables.
5. **P5 — Explicit boolean parameters**: No bare `true`/`false` at call sites; use separate named methods or options objects.
6. **P6 — No mixed polarity**: Never combine positive + negative booleans in a single `if` condition (e.g., `isX && !isY`). Always extract to a single named boolean capturing intent (e.g., `isConflict`, `isAccessDenied`, `isPending`).

**Go-specific exemptions**: `if !ok` (comma-ok), `if err != nil` (error check), `if err != nil && !os.IsNotExist(err)` (file-not-found guard), handler guard returns, stdlib calls (extract if repeated 3+).

These minimize cognitive load and ensure intent is explicitly stated. See also `spec/03-coding-guidelines/no-negatives.md` for guard function inventories across PHP, TypeScript, and Go.
