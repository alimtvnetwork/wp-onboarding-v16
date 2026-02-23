# Memory: architecture/coding-standards/generic-database-wrappers
Updated: 2026-02-23

---

## Overview

Database operations across Go and PHP utilize a generic wrapper pattern to centralize error handling and improve type safety. Both stacks return typed result envelopes (`Result[T]`/`DbResult<T>`, `ResultSet[T]`/`DbResultSet<T>`, `ExecResult`/`DbExecResult`) with automatic stack trace capture.

---

## Go `pkg/dbutil` Package

### Structure

| File | Contents |
|------|----------|
| `db.go` | `DB` struct wrapping `*sql.DB`, `New()` constructor, context-aware delegates |
| `result.go` | `Result[T]` — single-item envelope with `IsDefined`, `IsEmpty`, `HasError`, `IsSafe`, `Value`, `AppError`, `StackTrace` |
| `result_set.go` | `ResultSet[T]` — multi-row envelope with `HasAny`, `IsEmpty`, `Count`, `HasError`, `IsSafe`, `Items`, `First`, `AppError`, `StackTrace` |
| `query.go` | `QueryOne[T]`, `QueryMany[T]` — generic query functions using `RowScanner[T]` / `RowsScanner[T]` |
| `exec.go` | `Exec` — non-query wrapper returning `ExecResult` with `AffectedRows`, `LastInsertId`, `AppError` |

### Key Design Decisions

1. **DB struct** holds `*sql.DB` so callers inject once and never pass the connection on every call
2. **Package-level generic functions** (not methods) because Go doesn't allow generic methods on structs
3. **All functions accept `context.Context`** as first parameter
4. **sql.ErrNoRows** is not an error — `QueryOne` returns `Result[T]{defined: false}` so callers check `IsEmpty()`
5. **All errors** auto-wrapped with `apperror.Wrap()` capturing stack traces
6. **Scanner functions** (`RowScanner[T]`, `RowsScanner[T]`) provided by callers for type-safe row mapping
7. **`.AppError()` method** (not `.Error()`) — unified naming across both `dbutil` and `apperror` result types to avoid confusion with Go's native `error` interface. The dbutil types return `error` while apperror types return `*AppError`, but both use the same method name for consistency.

### Error Codes

| Code | Function |
|------|----------|
| E5010 | QueryOne failed |
| E5011 | QueryMany failed |
| E5012 | Row scan failed |
| E5013 | Rows iteration failed |
| E5014 | Exec failed |

---

## PHP `Database\TypedQuery` Class

### Structure

| File | Contents |
|------|----------|
| `DbResult.php` | `DbResult<T>` — single-item envelope with `isDefined()`, `isEmpty()`, `hasError()`, `isSafe()`, `value()`, `stackTrace()` |
| `DbResultSet.php` | `DbResultSet<T>` — multi-row envelope with `hasAny()`, `isEmpty()`, `count()`, `hasError()`, `isSafe()`, `items()`, `first()`, `stackTrace()` |
| `DbExecResult.php` | `DbExecResult` — mutation result with `isEmpty()`, `hasError()`, `isSafe()`, `affectedRows()`, `lastInsertId()`, `stackTrace()` |
| `TypedQuery.php` | `TypedQuery` — wraps `PDO`, provides `queryOne()`, `queryMany()`, `exec()` returning typed envelopes |

### Key Design Decisions

1. **Constructor injection** — `TypedQuery` takes `PDO` in constructor, no global state
2. **Closure mappers** — `Closure(array): T` replaces Go's scanner functions (PHP lacks runtime generics)
3. **@template PHPDoc** — Static analysis tools (PHPStan/Psalm) enforce type safety at analysis time
4. **All readonly** — Result classes use `readonly` constructor promotion (PHP 8.2+)
5. **Stack traces** auto-captured from `\Throwable::getTraceAsString()`
6. **`first()` on DbResultSet** returns `DbResult<T>`, propagating errors

---

## Migration Status

All database operations across both stacks are fully migrated to the generic wrapper pattern:

### Go Services (pkg/dbutil)

| Service | File | Methods migrated |
|---------|------|-----------------|
| Plugin | `plugin/crud.go` | All CRUD + query operations |
| Site | `site/crud.go` | List, GetByID, GetByURL, Create, Update, Delete, updateConnectionStatus, cache queries (getRemotePluginsFromCache, cacheRemotePlugins, InvalidateRemotePluginsCache) |
| Sync | `sync/crud.go` | GetFileChanges, RecordFileChange, MarkSynced, ClearChanges, getMappings, getMapping, getSiteInfo, updateMappingSyncStatus |

### PHP Agent Traits (TypedQuery)

| Trait | Status |
|-------|--------|
| `AgentCrudReadTrait` | Migrated — uses `AgentSite::fromRow(...)` mapper, returns `AgentSite` model via `getAgentModel()` |
| `AgentCrudWriteTrait` | Migrated — uses `TypedQuery::exec()` with `DbExecResult` |
| `AgentRemoteCoreTrait` | Migrated — accepts `AgentSite` model instead of raw arrays |
| `AgentRemoteActionTrait` | Migrated — accepts `AgentSite` model instead of raw arrays |

---

*All new database operations MUST use `pkg/dbutil` (Go) or `TypedQuery` (PHP) instead of raw queries.*
