
## Plan: DRY Refactoring — Phase-by-Phase

> Audit date: 2026-02-09  
> Goal: Eliminate duplication, improve maintainability across Go backend, React frontend, and PHP WordPress plugin — without breaking anything.

---

### Phase 1 — Go Backend: Uploader Method Deduplication

**Problem:** `EnablePluginViaUploader`, `DisablePluginViaUploader`, `DeletePluginViaUploader` in `uploader.go` are nearly identical (lines 443–552). Each one:
1. Calls `CheckRiseupAsiaAvailable()` to resolve namespace
2. Normalizes slug
3. Builds endpoint + JSON body `{"plugin": slug}`
4. Sends POST, reads body, checks status
5. Returns `&APIError{...}` with identical structure

**Fix:** Extract a single `pluginLifecycleAction(slug, endpointSuffix, operationName, errorCode)` method. The 3 methods become one-liners.

**Also:** `ReplaceFileViaUploader` and `DeleteFileViaUploader` share the same namespace-resolution + manual `http.NewRequest` boilerplate (instead of using `c.request()`). Consolidate to use `c.request()`.

**Also:** `CheckRiseupAsiaAvailable()` tries 3 namespaces with copy-paste loops (lines 115–153). Refactor to a `for` loop over `[]string{RiseupAsiaNamespace, RiseUpUploaderNamespace, PluginUploaderNamespace}`.

**Also:** `endpoint_map.go` reimplements `strings.Replace`, `strconv.FormatInt`, and `strings.Index` manually (lines 96–145). Replace with stdlib calls.

**Files:** `backend/internal/wordpress/uploader.go`, `backend/internal/wordpress/endpoint_map.go`, `backend/internal/wordpress/client.go`

**Risk:** Low — purely internal refactoring of method bodies. API signatures unchanged.

---

### Phase 2 — Go Backend: Envelope Parsing & Error Context

**Problem:** Multiple methods in `uploader.go` manually check for envelope format and fall back to legacy parsing (GetUploaderStatus, ListPluginsViaUploader, UploadPluginViaUploader). Each reimplements the "read body → try envelope → fall back" pattern.

**Fix:** Create a generic helper: `func (c *Client) requestAndUnwrap[T any](method, endpoint string, body interface{}) (T, error)` that:
1. Calls `c.request()`
2. Reads response body
3. Tries `UnwrapResults` / `UnwrapSingleResult`
4. Falls back to legacy JSON decode
5. Returns `APIError` with consistent diagnostics

**Also:** The PHP stack trace parsing in `UploadPluginViaUploader` (lines 369–403) should be extracted into a reusable `extractPHPStackTrace(respBytes)` function since the same pattern is needed whenever a WordPress response contains error details.

**Files:** `backend/internal/wordpress/uploader.go`, `backend/internal/wordpress/envelope.go`

**Risk:** Low — wrapper around existing functions.

---

### Phase 3 — Frontend: Error Diagnostics Context Deduplication

**Problem:** In `src/lib/api.ts`, the error response construction is duplicated 3 times (E9005 HTML fallback at line 288, E9006 at line 319, E9003 at line 351). Each builds the same diagnostic context object (`requestUrl, apiBase, apiBaseAbsolute, VITE_API_URL, VITE_WS_URL, uiOrigin`).

**Fix:** Extract `buildDiagnosticContext()` function that returns the shared context object. The 3 error blocks each call it.

**Also:** `envViteApiUrl` and `envViteWsUrl` are computed identically in 2 places (line 283 and line 348). Hoist to top of `fetchRequest`.

**Files:** `src/lib/api.ts`

**Risk:** Very low — internal refactoring only.

---

### Phase 4 — Frontend: Error Store Capture Deduplication

**Problem:** `captureError` and `captureException` in `errorStore.ts` both:
1. Call `captureStackTrace()` → `parseFullStackTrace()` → `parseStackTrace()`
2. Extract UI click path via `getClickPathForError()`
3. Extract execution logs via `getExecutionLogsForError()`
4. Build `invocationChain` via `buildInvocationChain()`
5. Extract envelope diagnostic fields (`requestedAt`, `requestDelegatedAt`, `envelopeErrors`, `envelopeMethodsStack`)
6. Construct a `CapturedError` with 40+ fields
7. Push to store with pendingSync

The two methods duplicate ~80% of their logic (compare lines 336–424 vs 430–551).

**Fix:** Extract a shared `buildCapturedError(options)` internal function that both `captureError` and `captureException` call with their respective inputs. The envelope extraction logic (lines 482–492 and 402–411) is especially duplicated.

**Files:** `src/stores/errorStore.ts`

**Risk:** Medium — needs careful testing of error capture paths.

---

### Phase 5 — Frontend: api.ts Type Splitting & Query Builder

**Problem 1:** `src/lib/api.ts` is **1,399 lines** — a monolith mixing types, envelope parsing, HTTP client, and API method definitions. Hard to navigate.

**Fix:** Split into:
- `src/lib/api/types.ts` — All interfaces (Site, Plugin, PluginMapping, etc.) — ~500 lines
- `src/lib/api/envelope.ts` — Envelope types + `isEnvelope()` + `parseEnvelope()` — ~100 lines
- `src/lib/api/client.ts` — `fetchRequest`, `request`, circuit breaker wrapper — ~150 lines
- `src/lib/api/methods.ts` — The `api` object with all endpoint methods — ~400 lines
- `src/lib/api/index.ts` — Re-exports everything (barrel file for backward compat)

**Problem 2:** Query string building is duplicated across `getPublishHistory`, `getSiteHealthHistory`, `listErrorHistory`, `getRequestSessions` — each manually creates `URLSearchParams` with identical patterns.

**Fix:** Create a `buildQueryString(params: Record<string, string | number | undefined>)` utility and use it everywhere.

**Files:** `src/lib/api.ts` → split into `src/lib/api/` directory

**Risk:** Medium — many files import from `@/lib/api`. Barrel re-export ensures backward compat.

---

### Phase 6 — Frontend: Hooks Pattern Consolidation

**Problem:** `usePlugins.ts` and `useSites.ts` (and likely others) follow identical patterns:
```ts
useQuery({ queryKey: [...], queryFn: async () => {
  const response = await api.getSomething();
  return requireSuccess(response, { endpoint, method });
}});
```

**Fix:** Create a `useApiQuery<T>(key, apiFn, endpoint, method)` factory hook that encapsulates the pattern. Individual hooks become:
```ts
export const usePlugins = () => useApiQuery("plugins", api.getPlugins, "/plugins", "GET");
```

**Files:** `src/hooks/usePlugins.ts`, `src/hooks/useSites.ts`, `src/hooks/useErrors.ts`, `src/hooks/useSettings.ts`, etc.

**Risk:** Low — thin wrappers.

---

### Phase 7 — PHP Plugin: Snapshot Class Initialization

**Problem:** `class-admin.php` has 5+ instances of:
```php
require_once dirname(__FILE__) . '/class-snapshot-detector.php';
$detector = new RiseupSnapshotDetector(Riseup_File_Logger::get_instance(), Riseup_Database::get_instance());
```
Similarly, `class-snapshot-scheduler.php` has 4 instances of:
```php
require_once dirname(__FILE__) . '/class-snapshot-cleaner.php';
$cleaner = new RiseupSnapshotCleaner($this->logger, $this->db);
```

**Fix:** Use lazy-loading singletons or factory methods. Add a `RiseupSnapshotFactory` class that centralizes construction:
```php
class RiseupSnapshotFactory {
    public static function detector() { ... }
    public static function scheduler() { ... }
    public static function cleaner() { ... }
}
```

**Files:** `wp-plugins/riseup-asia-uploader/includes/class-admin.php`, `wp-plugins/riseup-asia-uploader/includes/class-snapshot-scheduler.php`, new `class-snapshot-factory.php`

**Risk:** Low — construction logic only.

---

### Phase 8 — PHP Plugin: Logger Auto-Context Consolidation

**Problem:** The logger's `warn()`, `error()`, and `log_exception()` methods each independently call `enrich_context_with_request()` and build invocation chains. The enrichment pattern is duplicated across 4 methods (`warn`, `error`, `log_exception`, `log_at`).

**Fix:** Move all context enrichment (request metadata + invocation chain) into a single `prepare_context($context, $include_backtrace = false)` method that all log methods call, eliminating per-method duplication.

**Files:** `wp-plugins/riseup-asia-uploader/includes/class-file-logger.php`

**Risk:** Low — logging internals.

---

### Phase 9 — Frontend: GlobalErrorModal Decomposition

**Problem:** `GlobalErrorModal.tsx` is **2,164 lines** — the largest file in the frontend. It contains rendering logic for 8+ tabs, markdown report generation, copy logic, and session diagnostics — all in one component.

**Fix:** Extract into focused sub-components:
- `ErrorModalOverview.tsx` — Summary/overview tab
- `ErrorModalStackTab.tsx` — Stack trace visualization
- `ErrorModalRequestTab.tsx` — Request chain visualization
- `ErrorModalTraversalTab.tsx` — Method traversal
- `ErrorModalReportGenerator.ts` — Markdown report logic (pure function, not a component)
- Keep `GlobalErrorModal.tsx` as the shell with tabs + state

**Files:** `src/components/errors/GlobalErrorModal.tsx` → extract into `src/components/errors/` subdirectory

**Risk:** Medium — complex UI, needs visual verification after split.

---

### Phase 10 — Cross-Stack: Envelope Type Alignment

**Problem:** The Universal Response Envelope types are defined independently in 3 places:
1. **Go:** `backend/internal/wordpress/envelope.go` — `EnvelopeStatus`, `EnvelopeAttributes`, `EnvelopeErrors`
2. **TypeScript:** `src/lib/api.ts` — `EnvelopeStatus`, `EnvelopeAttributes`, `EnvelopeErrors`, `EnvelopeMethodsStack`
3. **PHP:** `class-envelope-builder.php` — builder class

The Go version is missing `EnvelopeMethodsStack`. The TS version has extra fields not in Go. They can drift silently.

**Fix:** Add a `spec/response-envelope/envelope.schema.json` (JSON Schema) as the single source of truth. Add a comment in each implementation referencing the schema version. This is documentation-level, not code-generation — but it prevents drift.

**Files:** `spec/response-envelope/`, Go/TS/PHP envelope files (add schema version comments)

**Risk:** Very low — documentation + comments only.

---

### Execution Order Summary

| Phase | Layer | Effort | Risk | Description |
|-------|-------|--------|------|-------------|
| 1 | Go | Small | Low | Uploader lifecycle method dedup + stdlib usage |
| 2 | Go | Small | Low | Envelope unwrap helper + PHP stack extraction |
| 3 | Frontend | Small | Very Low | API error diagnostic context dedup |
| 4 | Frontend | Medium | Medium | Error store capture dedup |
| 5 | Frontend | Medium | Medium | api.ts split + query builder utility |
| 6 | Frontend | Small | Low | Hook factory pattern |
| 7 | PHP | Small | Low | Snapshot class factory |
| 8 | PHP | Small | Low | Logger context consolidation |
| 9 | Frontend | Large | Medium | GlobalErrorModal decomposition |
| 10 | Cross | Small | Very Low | Envelope schema alignment |

**Total estimated work:** ~10-12 focused sessions.

Each phase is **self-contained** — you can ship and test after each one. No phase depends on another (though Phase 5 before Phase 9 is recommended since both touch large files).
