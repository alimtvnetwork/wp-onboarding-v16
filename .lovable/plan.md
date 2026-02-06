

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

