# Memory: architecture/coding-standards/generic-database-wrappers
Updated: 2026-02-19

---

## Overview

Database operations across Go and PHP utilize a generic wrapper pattern to centralize error handling and improve type safety. In Go, the `pkg/dbutil` package provides a `DB` struct wrapping `*sql.DB`, generic result envelopes (`Result[T]`, `ResultSet[T]`, `ExecResult`), and package-level generic query functions (`QueryOne[T]`, `QueryMany[T]`, `Exec`) that automatically wrap errors with stack traces using `apperror.Wrap()`. A similar generic pattern is applied to the PHP SQLite Database class.

---

## Go `pkg/dbutil` Package

### Structure

| File | Contents |
|------|----------|
| `db.go` | `DB` struct wrapping `*sql.DB`, `New()` constructor |
| `result.go` | `Result[T]` — single-item envelope with `IsDefined`, `IsEmpty`, `HasError`, `IsSafe`, `Value`, `StackTrace` |
| `result_set.go` | `ResultSet[T]` — multi-row envelope with `HasAny`, `IsEmpty`, `Count`, `HasError`, `IsSafe`, `Items`, `StackTrace` |
| `query.go` | `QueryOne[T]`, `QueryMany[T]` — generic query functions using `RowScanner[T]` / `RowsScanner[T]` |
| `exec.go` | `Exec` — non-query wrapper returning `ExecResult` with `AffectedRows`, `LastInsertID` |

### Key Design Decisions

1. **DB struct** holds `*sql.DB` so callers inject once and never pass the connection on every call
2. **Package-level generic functions** (not methods) because Go doesn't allow generic methods on structs
3. **sql.ErrNoRows** is not an error — `QueryOne` returns `Result[T]{defined: false}` so callers check `IsEmpty()`
4. **All errors** auto-wrapped with `apperror.Wrap()` capturing stack traces
5. **Scanner functions** (`RowScanner[T]`, `RowsScanner[T]`) provided by callers for type-safe row mapping

### Error Codes

| Code | Function |
|------|----------|
| E5010 | QueryOne failed |
| E5011 | QueryMany failed |
| E5012 | Row scan failed |
| E5013 | Rows iteration failed |
| E5014 | Exec failed |

---

*All new database operations MUST use `pkg/dbutil` instead of raw `db.Query`/`db.QueryRow`/`db.Exec`.*
