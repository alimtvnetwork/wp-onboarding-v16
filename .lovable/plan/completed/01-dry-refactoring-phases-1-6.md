# DRY Refactoring — Completed Phases 1–6

**Completed: 2026-02-09**

---

## Phase 1 — Go Backend: Uploader Method Deduplication ✅
Extracted `pluginLifecycleAction()` to replace 3 near-identical methods (`EnablePluginViaUploader`, `DisablePluginViaUploader`, `DeletePluginViaUploader`). Consolidated `CheckRiseupAsiaAvailable()` to use a loop. Replaced manual string operations with stdlib calls in `endpoint_map.go`.

**Files:** `backend/internal/wordpress/uploader.go`, `endpoint_map.go`, `client.go`

## Phase 2 — Go Backend: Envelope Parsing & Error Context ✅
Created generic `requestAndUnwrap[T]()` helper for consistent envelope parsing with legacy fallback. Extracted `extractPHPStackTrace()` for reusable PHP stack trace parsing.

**Files:** `backend/internal/wordpress/uploader.go`, `envelope.go`

## Phase 3 — Frontend: Error Diagnostics Context Deduplication ✅
Extracted `buildDiagnosticContext()` to eliminate 3x duplicated diagnostic context construction in API error handling. Hoisted `envViteApiUrl` and `envViteWsUrl` computation.

**Files:** `src/lib/api.ts` (later split in Phase 5)

## Phase 4 — Frontend: Error Store Capture Deduplication ✅
Created `buildCapturedError()` factory and `commitErrorToStore()` helper in `errorStore.ts`. Both `captureError` and `captureException` now delegate to shared builders, eliminating ~80 lines of duplicate logic.

**Files:** `src/stores/errorStore.ts`

## Phase 5 — Frontend: api.ts Split & Query Builder ✅
Split the 1,393-line `src/lib/api.ts` monolith into 5 focused modules under `src/lib/api/`:
- `types.ts` — All interfaces and type aliases
- `envelope.ts` — Envelope detection and parsing (`isEnvelope`, `parseEnvelope`)
- `client.ts` — Core HTTP handling, circuit breaker, `ApiClientError`
- `methods.ts` — The `api` object with all endpoint methods + shared `buildQuery()` utility
- `index.ts` — Barrel re-export for backward compatibility (44 consumers unchanged)

**Files:** `src/lib/api/` (5 new files), deleted `src/lib/api.ts`

## Phase 6 — Frontend: Hooks Pattern Consolidation ✅
Created `useApiQuery` and `useApiQueryPaginated` factory hooks in `src/hooks/useApiQuery.ts`. Refactored 5 hooks (`usePlugins`, `useSites`, `useErrors`, `useSettings`, `useSiteMappings`) to use the factory, eliminating ~50 lines of boilerplate.

**Files:** `src/hooks/useApiQuery.ts` (new), 5 hook files refactored

---

*All 6 phases shipped incrementally with no breaking changes.*
