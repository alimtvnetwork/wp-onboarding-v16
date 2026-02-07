# Logging Standards Specification

**Created:** 2026-02-06

## 1. Human-Readable Context Required

Every log line at **ERROR** or **WARN** level MUST include human-readable names:

| Field | Required at ERROR/WARN | Required at INFO | Required at DEBUG |
|-------|----------------------|------------------|-------------------|
| `plugin` (name) | ✅ | ✅ | Optional |
| `pluginId` | ✅ | ✅ | ✅ |
| `site` (name) | ✅ | ✅ | Optional |
| `siteId` | ✅ | ✅ | ✅ |
| `siteUrl` | ✅ | Optional | Optional |

**Key-value ordering:** Names first, then IDs, then technical details.

```go
// ✅ CORRECT
s.log.Error("Activation failed",
    "plugin", pluginInfo.Name,
    "site", siteInfo.Name,
    "siteUrl", siteInfo.URL,
    "pluginId", pluginID,
    "siteId", siteID,
    "step", "activate",
    "error", err,
)

// ❌ WRONG - missing names, IDs only
s.log.Error("Activation failed", "pluginId", 2, "siteId", 1, "step", "activate")
```

## 2. Multi-Line Format for ERROR/WARN

The logger renders ERROR and WARN key-value pairs on separate indented lines:

```
[v1.19.4 2026-02-05 04:00:13] [publish] Activation failed [ERROR] [service.go:526]
  plugin  = Category Generator
  site    = Demo AT
  siteUrl = https://demoat.attoproperty.com.au
  pluginId = 2
  siteId  = 1
  step    = activate
--- Stack Trace ---
  ...
```

INFO and DEBUG levels remain compact single-line format.

## 3. broadcastDetailedLog Callers

All `broadcastDetailedLog` callers MUST pass `pluginName`, `siteName`, `siteUrl` in the details map. The function resolves names from the details map; if missing, it falls back to `plugin#N` / `site#N` which defeats the purpose.

**Preferred approach:** Use a publish context struct that stores names at the start of `Publish()` so all helper methods automatically include them.

## 4. logToErrorFile Format

The `logToErrorFile` function uses multi-line blocks with indented key-values. It MUST always include:
- Site name and ID
- Site URL
- Plugin slug
- Error message
- Status code (if HTTP)
- Endpoint (if HTTP)
- Response body (truncated to 2000 chars)

## 5. URL Design Rule — Fixed Endpoints Only

**All REST API endpoints MUST use fixed, lowercase paths.** User-provided identifiers (plugin slugs, file paths) MUST be passed in JSON request bodies, never embedded in URL paths.

```
// ❌ WRONG - dynamic slug in URL path
GET  /plugins/{slug}/files
GET  /plugins/(?P<slug>[a-zA-Z0-9_-]+)/export
DELETE /plugins/{slug}

// ✅ CORRECT - fixed URL, slug in JSON body
POST /plugins/files    { "plugin": "my-plugin" }
POST /plugins/export   { "plugin": "my-plugin" }
POST /plugins/delete   { "plugin": "my-plugin" }
POST /plugins/enable   { "plugin": "my-plugin" }
POST /plugins/disable  { "plugin": "my-plugin" }
POST /plugins/file     { "plugin": "my-plugin", "path": "includes/main.php" }
POST /plugins/sync     { "plugin": "my-plugin", "files": [...] }
POST /plugins/sync-manifest { "plugin": "my-plugin" }
```

**Why:** Plugin slugs can contain slashes (e.g., `broken-link-checker/broken-link-checker`) which break URL routing. Fixed endpoints eliminate this entire class of bugs.

**PHP resolution:** The `find_plugin_file($slug)` method iterates `get_plugins()`, extracts the folder name via `dirname()`, and matches against the provided slug. This maps `"broken-link-checker"` → `"broken-link-checker/broken-link-checker.php"` for use with WordPress core functions (`activate_plugin()`, `deactivate_plugins()`, `delete_plugins()`).

## 6. No Hardcoded Fallback Names

Log functions must resolve real names from database lookups or passed context — never use hardcoded strings like `"plugin#2"` or `"site#1"` as primary identifiers.

## 7. Server Shutdown Logging

`http.ErrServerClosed` is a normal shutdown signal and must NOT be logged as FATAL. Check for it explicitly before logging server errors.
