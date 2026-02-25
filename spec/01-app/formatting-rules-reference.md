# Formatting Rules Reference

> **Purpose:** Canonical specification for all project-wide formatting rules (PHP, Go, TypeScript).
> **Updated:** 2026-02-25

## Rules

### R1 — Braces for Single-Line Control Statements

All `if`, `foreach`, `while` statements must use braces, even for single-line bodies.

### R4 — Blank Line Before Return/Throw

A blank line must precede any `return` or `throw` statement if it is not the sole statement in its block.

### R5 — Blank Line After Closing Brace

A blank line is mandatory after a closing `}` brace when followed by more code (except for `else`, `catch`, or `finally`).

### R9a — Function Signatures

Function signatures with more than two parameters must be written line-by-line with one parameter per line and a trailing comma.

### R9b — Function Calls

Function calls with more than two arguments must be written line-by-line with one argument per line and a trailing comma.

### R9c — PHP Array Literals

PHP array literals with more than two items must be written line-by-line with one item per line and a trailing comma.

**Detection:** `grep -Pn 'array\(.*,.*,.*\)' --include='*.php'`

### R10 — Blank Line Before Control Structures

A blank line is mandatory before control structures (`if`, `foreach`, `switch`, `match`) when preceded by any statements like assignments or function calls.

**Detection:** Look for `;\n    if (` patterns (assignment immediately followed by control structure without separator).

### R11 — Long String Concatenations

Long string concatenations must be broken into line-by-line combinations.

### R12 — No Empty Line After Opening Brace

Opening braces for classes, traits, methods, or functions must not be followed by an empty line on the first line of content.

### R13 — No Empty First Line in Source Files

Source files must not contain an empty line at the very beginning (e.g., immediately following the `<?php` tag).

## Known Pitfalls and Prevention

| Issue | Reference |
|-------|-----------|
| R9c array violations in Snapshot traits | [`../02-app-issues/04-r9c-array-literal-formatting.md`](../02-app-issues/04-r9c-array-literal-formatting.md) |
| R10 missing blank lines in ActivationHandler | [`../02-app-issues/05-r10-activation-handler-formatting.md`](../02-app-issues/05-r10-activation-handler-formatting.md) |
