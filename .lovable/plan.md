

# Logging Quality, URL Consistency, and Spec Update Plan

## Problem Summary

The backend logging across all services is inconsistent and unhelpful:

1. **Numeric IDs without names**: Logs say `pluginId=2 siteId=1` instead of including the plugin name, site name, and site URL.
2. **Flat single-line key=value format**: Hard to scan. The user wants multi-line structured output for error logs.
3. **Missing context in error.log.txt**: The `logToErrorFile` and `broadcastDetailedLog` functions often fall back to `plugin#2` or `site#1` because names are not passed through.
4. **"Server failed" false alarm**: Already fixed in the last edit (`http: Server closed` is now ignored).
5. **Remaining URL slug-in-path issues**: The remote plugin endpoints were fixed, but the pattern should be documented as a spec rule.
6. **No spec documenting these logging standards**: Learnings keep getting lost.

---

## Phase 1: Logging Spec Document

Create `.lovable/specs/logging-standards.md` to codify rules so they are never forgotten.

**Rules to document:**
- Every log line at ERROR or WARN level MUST include human-readable names (pluginName, siteName, siteURL), not just numeric IDs
- Numeric IDs should still appear but as secondary context
- The `logToErrorFile` format uses multi-line blocks with indented key-values (already established pattern)
- All `broadcastDetailedLog` callers must pass `pluginName`, `siteName`, `siteUrl` in the details map
- URL design: never embed user-provided identifiers in URL paths; use JSON request bodies
- The logger's key=value pairs should follow a consistent order: name fields first, then IDs, then technical details

---

## Phase 2: Fix `logRemoteAction` in site/service.go

**File:** `backend/internal/services/site/service.go` (line 1303-1321)

Current problem: `logRemoteAction` logs errors with only `siteId` and `action`:
```go
s.log.Error(message, "siteId", siteId, "action", action, "step", step)
```

It should log as { siteId: val, ....} in the log text do you get it???

Fix: Include `siteName`, `siteUrl`, and `pluginSlug` from the details map or pass them explicitly.

---

## Phase 3: Fix `broadcastDetailedLog` in publish/service.go

**File:** `backend/internal/services/publish/service.go` (line 1008-1052)

Current problem: Falls back to `plugin#2` / `site#1` when details map does not contain names. Most callers do NOT pass `pluginName`/`siteName` in the details map.

Fix: Change `broadcastDetailedLog` to accept `pluginName` and `siteName` as explicit parameters (or store them on the Service struct context for the current operation). Then all the existing call sites that pass `nil` for details will still get names.

Approach: Store `pluginInfo.Name` and `siteInfo.Name` as fields on a publish context that is set at the start of `Publish()` and used by all helper methods. This avoids changing 30+ call signatures.

---

## Phase 4: Fix watcher/service.go logging

**File:** `backend/internal/services/watcher/service.go`

Lines 240, 244, 255, 264, 282 all log with `pluginId` only.

Fix: The plugin object `p` is already available in scope (line 234). Use `p.Name` in all log calls:
```go
s.log.Info("Auto-publish triggered",
    "plugin", p.Name,
    "pluginId", pluginID,
    "changes", len(changes),
    "sites", len(p.Mappings),
)
```

Similarly for auto-publish failures (line 264), add `mapping.SiteName`.

---

## Phase 5: Fix version/service.go and git/service.go logging

**Files:**
- `backend/internal/services/version/service.go` - lines 60, 64, 100
- `backend/internal/services/git/service.go` - lines 118, 192, 249, 320, 492, 543

These services have access to plugin objects after initial lookup. Add plugin name to all log calls.

---

## Phase 6: Fix backup/service.go logging

**File:** `backend/internal/services/backup/service.go` - lines 68, 120, 143

Currently logs `mapping_id` and `backup_id` without any human-readable context. Add plugin name and site name where available.

---

## Phase 7: Improve logger multi-line format for error logs

**File:** `backend/internal/logger/logger.go`

The current format puts all key-value pairs on a single line:
```
[v1.19.4 2026-02-05 04:00:13] [publish] Activation failed pluginId=2 siteId=1 step=activate [ERROR] [service.go:526]
```

Improve: For ERROR and WARN levels, render key-value pairs on separate indented lines for readability:
```
[v1.19.4 2026-02-05 04:00:13] [publish] Activation failed [ERROR] [service.go:526]
  plugin  = Category Generator
  site    = Demo AT
  siteUrl = https://demoat.attoproperty.com.au
  step    = activate
--- Stack Trace ---
  ...
```

This change is isolated to the `log()` method in logger.go and affects only ERROR/WARN levels to keep INFO/DEBUG compact.

---

## Files Changed Summary

| File | Change |
|------|--------|
| `.lovable/specs/logging-standards.md` | NEW: Logging rules spec |
| `backend/internal/logger/logger.go` | Multi-line key-value for ERROR/WARN |
| `backend/internal/services/publish/service.go` | Pass names through broadcastDetailedLog |
| `backend/internal/services/site/service.go` | Fix logRemoteAction to include names |
| `backend/internal/services/watcher/service.go` | Add plugin/site names to all logs |
| `backend/internal/services/backup/service.go` | Add context to backup logs |
| `backend/internal/services/version/service.go` | Add plugin name to version logs |
| `backend/internal/services/git/service.go` | Add plugin name to git logs |

---

## Implementation Order

1. Create the spec first (Phase 1) - establishes the rules
2. Fix the logger format (Phase 7) - affects all output immediately
3. Fix publish service (Phase 3) - highest impact, most log volume
4. Fix site service (Phase 2) - remote plugin action logs
5. Fix watcher (Phase 4), version (Phase 5), git (Phase 5), backup (Phase 6) - secondary services

---
---

# Universal Response Envelope — Migration Plan

## Goal
Standardize ALL API responses (Go backend + PHP WordPress plugin) to a single universal envelope structure, controlled by a seedable configuration for debug verbosity.

---

## Envelope Structure

### List Response (isMultiple: true)
```json
{
  "status": {
    "success": true,
    "code": 200,
    "message": "OK",
    "timestamp": "2026-02-07T12:00:00Z"
  },
  "attributes": {
    "isSingle": false,
    "isMultiple": true,
    "totalRecords": 150,
    "perPage": 20,
    "totalPages": 8,
    "currentPage": 2
  },
  "results": [
    { "id": 1, "name": "Example" }
  ],
  "navigation": {
    "nextPage": 3,
    "prevPage": 1,
    "pages": [1, 2, 3, 4, 5]
  },
  "error": null,
  "additional": {}
}
```

### Single Item Response (isSingle: true — slim)
```json
{
  "status": {
    "success": true,
    "code": 200,
    "message": "OK",
    "timestamp": "2026-02-07T12:00:00Z"
  },
  "attributes": {
    "isSingle": true,
    "isMultiple": false
  },
  "results": [
    { "id": 1, "name": "Example Site" }
  ],
  "error": null,
  "additional": {}
}
```
> Note: `navigation` is omitted for single items. `results` is always an array (length 1 for singles).

### Error Response
```json
{
  "status": {
    "success": false,
    "code": 500,
    "message": "Database connection failed",
    "timestamp": "2026-02-07T12:00:00Z"
  },
  "attributes": {
    "isSingle": false,
    "isMultiple": false
  },
  "results": [],
  "error": {
    "code": "E5001",
    "message": "Database connection failed",
    "stackTrace": "(config-controlled)",
    "stackTraceFrames": []
  },
  "navigation": null,
  "additional": {
    "retryable": true,
    "referenceId": "err-uuid-123"
  }
}
```

---

## Configuration: Stack Trace Exposure

A seedable config key controls error verbosity (enable/disable via config):

### Go Backend (`config.json`)
```json
{
  "ResponseDebug": {
    "IncludeStackTrace": true,
    "IncludeInternalErrors": true,
    "MaxStackFrames": 20
  }
}
```

### PHP Plugin (`wp-config.php` or plugin settings)
```php
define('RISEUP_RESPONSE_DEBUG', true);
```

When disabled: `error.stackTrace` = null, `error.stackTraceFrames` = [], error messages are generic.

---

## Phases

### Phase 1: Foundation (Go Backend)
- [ ] Create `envelope` package with `Response`, `Attributes`, `Navigation`, `Status`, `ErrorDetail` structs
- [ ] Create `envelope.Success(data)`, `envelope.List(data, pagination)`, `envelope.Error(err)` builders
- [ ] Add `ResponseDebug` config to `config.json` and config loader
- [ ] Add pagination helper: `envelope.NewPagination(total, page, perPage)` → computes pages, navigation
- [ ] Write unit tests for envelope builders

### Phase 2: Migrate Go Handlers
- [ ] Update `response.go` helpers to emit new envelope
- [ ] Migrate all handler files: site, plugin, sync, publish, backup, error, session, settings, health
- [ ] Ensure all list endpoints accept `page` and `perPage` from request body
- [ ] Update service layer to return `(results, totalCount, error)` where needed for pagination

### Phase 3: Migrate PHP Plugin
- [ ] Create `RiseupResponseEnvelope` PHP class mirroring the Go envelope
- [ ] Add `envelope_success()`, `envelope_list()`, `envelope_error()` helper functions
- [ ] Wire stack trace inclusion to `RISEUP_RESPONSE_DEBUG` config
- [ ] Migrate all REST endpoint handlers to use the new envelope
- [ ] Update `safe_execute` wrapper to emit envelope-formatted errors

### Phase 4: Update Frontend
- [ ] Create `parseEnvelope(response)` utility that extracts `results`, `attributes`, `navigation`, `error`
- [ ] Update all `apiClient` response handlers to use `parseEnvelope`
- [ ] Update pagination components to read `navigation` and `attributes`
- [ ] Update error modal to read `error.stackTrace` from new location
- [ ] Update error history persistence to match new structure

### Phase 5: Update OpenAPI Specs
- [ ] Redefine `UniversalEnvelope` schema in `backend/api/openapi.json`
- [ ] Update all endpoint response schemas to use new envelope with `allOf`
- [ ] Update PHP plugin `api-spec.json` to match
- [ ] Run `make validate-openapi` to confirm validity

### Phase 6: Testing & Documentation
- [ ] End-to-end test: publish flow with new envelope
- [ ] End-to-end test: sync flow with pagination
- [ ] End-to-end test: error flow with stack trace config on/off
- [ ] Update CODING-GUIDELINES.md with new response standard
- [ ] Update memory entries for the new envelope pattern

---

## Migration Rules

1. **`results` is ALWAYS an array** — even for single items (length 1) and errors (empty)
2. **`navigation` is ONLY present** when `attributes.isMultiple === true`
3. **`error` is null on success**, populated on failure
4. **`additional` is optional** — use for metadata like `retryable`, `referenceId`, `deprecationWarning`
5. **Stack traces respect config** — never leak in production unless explicitly enabled
6. **Default pagination**: `perPage: 20`, `page: 1` when not specified

---

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| Frontend breaks during migration | Migrate backend + frontend handlers in lockstep per domain |
| PHP/Go envelope drift | Shared OpenAPI spec is the contract — validate both against it |
| Performance overhead of always computing pagination | Only compute when handler opts into list mode |
| Breaking existing WordPress ↔ Go communication | Migrate Go consumer parsing at same time as PHP producer |

