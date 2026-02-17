# Memory: architecture/coding-standards/boolean-logic-principles
Updated: 2026-02-17

Strict boolean logic standards apply across all languages (PHP, TS, Go). Six principles are enforced (spec: `spec/01-coding-guidelines/boolean-principles.md` v2.0.0):

1. **P1 — `is`/`has` prefix**: All boolean identifiers must use `is` or `has` prefixes.
2. **P2 — No double negatives**: `!isNot...` is absolutely banned; name booleans for the positive case.
3. **P3 — Named guards over raw negation**: Never use `!` on function calls at call sites; use positively named guard functions (e.g., `isFileMissing()` not `!file_exists()`).
4. **P4 — Extract complex expressions**: Conditions with 2+ operators must be extracted into named boolean variables.
5. **P5 — Explicit boolean parameters**: No bare `true`/`false` at call sites; use separate named methods or options objects.
6. **P6 — No mixed polarity**: Never combine positive + negative booleans in a single `if` condition (e.g., `isX && !isY`). Always extract to a single named boolean capturing intent (e.g., `isConflict`, `isAccessDenied`, `isPending`).

These minimize cognitive load and ensure intent is explicitly stated. See also `spec/01-coding-guidelines/no-negatives.md` for guard function inventories across PHP, TypeScript, and Go.
