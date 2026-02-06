

# Fix Plan: Complete Error Report + API Endpoint Redesign

## Problem Summary

Two major issues need to be fixed:

1. **Copy Full Report is incomplete**: The `generateErrorReport()` function in the error modal is missing several data sections that are already captured in the `CapturedError` object (UI click path, React execution chain, PHP stack frames, error.log.txt content).

2. **Remote plugin API uses bad URL design**: Plugin slugs are embedded in URL paths (e.g., `/remote-plugins/broken-link-checker/broken-link-checker/disable`), which breaks with slashes in identifiers. The user wants JSON-body-based endpoints instead.

---

## Part 1: Complete the Copy Full Report

**File:** `src/components/errors/GlobalErrorModal.tsx` (function `generateErrorReport`, lines 1392-1487)

The `CapturedError` object already captures `uiClickPathString`, `executionLogsFormatted`, `phpStackFrames`, but `generateErrorReport()` never includes them in the output. The following sections will be added to the generated markdown report:

- **User Interactions** (from `error.uiClickPathString`) -- the click path the user took before the error
- **Frontend Execution Chain** (from `error.executionLogsFormatted`) -- React method call chain with original names
- **PHP Stack Trace** (from `error.phpStackFrames`) -- WordPress/PHP call stack formatted as a table
- **error.log.txt content** -- fetched async from the backend via `api.getBackendErrorLog()` and appended

The `copyFullError` function will be made async so it can fetch the backend error log before copying, combining all sections into one comprehensive report.

---

## Part 2: Redesign Remote Plugin API Endpoints

Change from URL-parameter-based to JSON-body-based endpoints.

### Before (current):
```text
POST /sites/{id}/remote-plugins/{plugin:.+}/enable
POST /sites/{id}/remote-plugins/{plugin:.+}/disable
DELETE /sites/{id}/remote-plugins/{plugin:.+}
GET  /sites/{id}/remote-plugins/{plugin:.+}/files
POST /sites/{id}/remote-plugins/{plugin:.+}/file
```

### After (new):
```text
POST /sites/{id}/remote-plugins/enable      { "plugin": "slug" }
POST /sites/{id}/remote-plugins/disable     { "plugin": "slug" }
POST /sites/{id}/remote-plugins/delete      { "plugin": "slug" }
POST /sites/{id}/remote-plugins/files       { "plugin": "slug" }
POST /sites/{id}/remote-plugins/file        { "plugin": "slug", "path": "file.php" }
```

All plugin identifiers move to the JSON request body. DELETE becomes POST (since DELETE with body is non-standard).

### Files Changed:

| File | Change |
|------|--------|
| `backend/internal/api/router.go` | Replace 5 routes with new JSON-body endpoints |
| `backend/internal/api/handlers/handlers.go` | Update 5 handlers to read `plugin` from JSON body instead of `mux.Vars` |
| `src/lib/api.ts` | Update 5 frontend API functions to send plugin in body |
| `src/components/errors/GlobalErrorModal.tsx` | Add missing sections to `generateErrorReport()`, make copy async |

---

## Technical Details

### Router changes (router.go)
Remove the `{plugin:.+}` pattern routes and register new clean routes:
```go
api.HandleFunc("/sites/{id}/remote-plugins/enable", handlers.EnableRemotePlugin).Methods("POST")
api.HandleFunc("/sites/{id}/remote-plugins/disable", handlers.DisableRemotePlugin).Methods("POST")
api.HandleFunc("/sites/{id}/remote-plugins/delete", handlers.DeleteRemotePlugin).Methods("POST")
api.HandleFunc("/sites/{id}/remote-plugins/files", handlers.GetRemotePluginFiles).Methods("POST")
api.HandleFunc("/sites/{id}/remote-plugins/file", handlers.GetRemotePluginFileContent).Methods("POST")
```

These must be registered BEFORE the catch-all GET `/sites/{id}/remote-plugins` to avoid conflicts.

### Handler changes (handlers.go)
Each handler will parse a JSON body struct:
```go
var input struct {
    Plugin string `json:"plugin"`
}
json.NewDecoder(r.Body).Decode(&input)
pluginSlug := input.Plugin
```

### Frontend API changes (api.ts)
```typescript
enableRemotePlugin: (siteId: number, pluginSlug: string) =>
  request(`/sites/${siteId}/remote-plugins/enable`, {
    method: "POST",
    body: JSON.stringify({ plugin: pluginSlug })
  }),
```

### Report generation additions (GlobalErrorModal.tsx)
Add these sections between the existing backend logs and parsed frames sections:
- PHP stack frames table (from `error.phpStackFrames`)
- User interaction path (from `error.uiClickPathString`)  
- Frontend execution chain (from `error.executionLogsFormatted`)
- Backend error.log.txt (fetched async)

ADD LOGS OF User Interaction Path, STEPS IN THE COPY REPORT AND MAKE SURE ALL BUTTONS ARE FUNCTIONAL IN ERROR MODAL
