# Frontend Pages — Phase-by-Phase Plan

**Updated: 2026-02-07**

This document maps every frontend page to its backend dependencies and pending work.
Ask to "complete Phase X" to implement one phase at a time.

---

## Current State Summary

| Page | Route | Status | Key Components |
|------|-------|--------|----------------|
| Dashboard | `/dashboard` | ✅ Built | Overview/summary |
| Sites | `/sites` | ✅ Built | SiteCard, AddSiteDialog, EditSiteDialog, ConnectionTestLogs, DeployUploaderDialog, RemotePluginsPanel, RemoteSnapshotsPanel, RemotePluginFileBrowser |
| Plugins | `/plugins` | ✅ Built | PluginActionsDropdown, BulkActionsBar, GitActionsPanel, ScanDirectoryPanel, VersionHistoryPanel, PublishProgressDialog, SyncProgressDialog, DiffPreviewDialog, ContentDiffViewer, SyncTreeView, QuickPublishIndicator, GlobalPublishProgress, ActivationDiagnostics |
| Publish History | `/publish-history` | ✅ Built | SiteVersionBadge |
| Site Health | `/site-health` | ✅ Built | — |
| E2E Tests | `/tests` | ✅ Built | — |
| Logs | `/logs` | ✅ Built | LiveLogEntry, LogViewer |
| Sessions | `/sessions` | ✅ Built | — |
| API Explorer | `/api-explorer` | ✅ Built | Swagger UI |
| Settings | `/settings` | ✅ Built | AboutPanel, ThemeSelector, VersionBadge, WhatsNewModal, NewSettingHighlight |
| Errors | `/errors` | ✅ Built | ErrorDetailModal, ErrorHistoryDrawer, ErrorQueueBadge, GlobalErrorModal, SessionLogsTab |

---

## Pending Cross-Cutting Work (Phases)

### Phase 1: Universal Envelope — Frontend Migration ✅ COMPLETE

**Goal:** Update the frontend API client (`src/lib/api.ts`) and all hooks to consume the new PascalCase Universal Response Envelope from the Go backend.

**Implementation:** Auto-detection in `fetchRequest()` — when a PascalCase envelope is detected, it's transparently converted to the existing `ApiResponse<T>` shape. All hooks work unchanged. Envelope metadata (attributes, navigation, delegated error) is preserved on `response.envelope`.

**Files changed:**
- `src/lib/api.ts` — Added 18 envelope types, `isEnvelope()` detector, `parseEnvelope<T>()` converter, auto-detection in `fetchRequest()` and `importRemoteSnapshot()`
- `src/stores/errorStore.ts` — Added `traversalSteps`, `requestedEndpoint`, `delegatedEndpoint`, `delegatedError`, `envelopeStackFrames` to `CapturedError`; extraction in `captureError()`

---

### Phase 2: Error Modal — DelegatedError & TraversalSteps ✅ COMPLETE

**Goal:** Display the new diagnostic fields in the Global Error Modal.

**Implementation:** Added a conditional "Traversal" tab to the BackendSection showing:
- **Endpoint Flow** — Go → PHP delegation chain with labeled badges
- **Traversal Steps** — Indented step chain with color-coded first/last entries
- **Delegated Error** — Full PHP failure details with status code, message, stack trace, and stack frames table
- **Go Envelope Stack Frames** — Tabular display of Go-side stack frames from the envelope

**Files changed:**
- `src/components/errors/GlobalErrorModal.tsx` — Added `TraversalDetails` component, `Route` icon import, conditional "Traversal" tab trigger and content
- `src/stores/errorStore.ts` — Already enriched in Phase 1

**Backend dependency:** Phase 1 (✅ Done)

---

### Phase 3: Pagination Support ✅ COMPLETE

**Goal:** Add pagination UI to list pages that support it (Sites, Plugins, Publish History, Errors, Sessions).

**Implementation:** Created a reusable `EnvelopePagination` component that reads `Navigation` (NextPage, PrevPage, Pages) and `Attributes` (TotalRecords, PerPage, TotalPages, CurrentPage) from the envelope. The component renders nothing when pagination data is absent or there's only one page, ensuring graceful degradation. Added `requireSuccessWithEnvelope` helper that returns both data and envelope metadata. Created paginated variants of hooks (`useSitesPaginated`, `usePluginsPaginated`, `useErrorsPaginated`) that preserve envelope metadata. PublishHistory falls back to its existing offset-based pagination when envelope is not present.

**Files created:**
- `src/components/shared/EnvelopePagination.tsx` — Reusable pagination with sliding page window, first/last/prev/next controls
- `src/lib/apiHelpers.ts` — `requireSuccessWithEnvelope`, `withPaginationParams`, `PaginatedResult` type

**Files changed:**
- `src/hooks/useSites.ts` — Added `useSitesPaginated` hook
- `src/hooks/usePlugins.ts` — Added `usePluginsPaginated` hook
- `src/hooks/useErrors.ts` — Added `useErrorsPaginated` hook
- `src/pages/Sites.tsx` — Wired `EnvelopePagination`
- `src/pages/Plugins.tsx` — Wired `EnvelopePagination`
- `src/pages/PublishHistory.tsx` — Added envelope pagination with fallback
- `src/pages/Errors.tsx` — Wired `EnvelopePagination`
- `src/pages/Sessions.tsx` — Wired `EnvelopePagination`

**Backend dependency:** Phase 1 (✅ Done)

---

### Phase 4: Dashboard Enhancements ✅ COMPLETE

**Goal:** Make the Dashboard a useful operational overview with live stats.

**Implementation:** Refactored the monolithic Dashboard into focused components with a dedicated aggregation hook. The dashboard now shows 5 stat cards (Connected Sites, Watching Plugins, Pending Changes, Recent Errors, Total Publishes), quick actions, and a Recent Publishes feed with publish history stats (total, success rate, avg duration). Data auto-refreshes every 30 seconds.

**Files created:**
- `src/hooks/useDashboardStats.ts` — Aggregates sites, plugins, errors, publish history stats, and recent publishes in a single query
- `src/components/dashboard/StatCard.tsx` — Reusable linked stat card
- `src/components/dashboard/QuickActions.tsx` — Quick action buttons grid
- `src/components/dashboard/RecentPublishes.tsx` — Recent publish entries with status icons and aggregate stats
- `src/components/dashboard/RecentActivity.tsx` — Generic activity feed (available for future use)

**Files changed:**
- `src/pages/Dashboard.tsx` — Refactored to use new hook and components

**Backend dependency:** Existing endpoints (✅ All available)

---

### Phase 5: Settings Page — Response Debug Config ✅ COMPLETE (Updated in Envelope Refactor C8)

**Goal:** Add UI to toggle `ResponseDebug` settings from the Settings page.

**Implementation:** The Developer tab features a "Response Debug (Backend)" section with toggles for `includeErrors`, `includeStackTrace`, `includeMethodsStack`, and a numeric input for `defaultPerPage`. These match the CONFIGURABILITY spec in `spec/response-envelope/CONFIGURABILITY.md`. Changes are persisted via `useSaveSettings`.

**Files changed:**
- `src/lib/api.ts` — `Settings.responseDebug` with `includeErrors`, `includeStackTrace`, `includeMethodsStack`; added `Settings.pagination.defaultPerPage`
- `src/hooks/useSettings.ts` — `useSaveSettings` mutation hook
- `src/pages/Settings.tsx` — Four controls: Include Errors, Include Stack Traces, Include Methods Stack, Default Per Page

**Backend dependency:** Config system already supports `ResponseDebug` (✅)

---

### Phase 6: Site Health Page — Deep Integration ✅ COMPLETE

**Goal:** Enhance Site Health with live connection indicators, uploader version checks, and bulk re-test.

**Implementation:** Extracted site health logic into a dedicated hook with 30s auto-polling, created a `SiteHealthCard` component with live pulsing status dots, uploader version badges, consecutive-failure warnings, high-latency/low-uptime color indicators, and error detail callouts. Added an "Avg Uptime" stat card. Renamed button to "Re-test All Sites".

**Files created:**
- `src/types/siteHealth.ts` — `SiteHealthSummary` and `SiteHealthStats` types (with optional `uploaderVersion`)
- `src/hooks/useSiteHealth.ts` — Polling hooks + mutation hooks for check operations
- `src/components/sites/SiteHealthCard.tsx` — Rich card with live indicators, version badge, consecutive-down warnings

**Files changed:**
- `src/pages/SiteHealth.tsx` — Refactored to use new hooks and components, added Avg Uptime stat

**Backend dependency:** Site health service (✅ scaffolded)

---

### Phase 7: E2E Tests Page — Live Results & Rerun ✅ COMPLETE

**Goal:** Polish the E2E Tests page with real-time result streaming and individual test rerun.

**Implementation:** Refactored the monolithic Tests page into focused components. Added WebSocket-based real-time test result streaming via a `useE2ETestStream` hook that listens for `e2e_test_started`, `e2e_test_result`, and `e2e_test_complete` events. Each test case card now shows its last result status indicator and a rerun button. The run history results also have per-test rerun buttons. A `LiveTestProgress` component shows a scrollable live result stream during active runs.

**Files created:**
- `src/hooks/useE2ETestStream.ts` — WebSocket hook for live E2E test result streaming
- `src/components/tests/TestCaseCard.tsx` — Test case card with status indicator and rerun button
- `src/components/tests/TestResultRow.tsx` — Test result row with rerun and error view actions
- `src/components/tests/LiveTestProgress.tsx` — Live progress card with scrollable result stream

**Files changed:**
- `src/pages/Tests.tsx` — Refactored to use new components, added rerun mutation and live status map
- `src/lib/ws.ts` — Added `E2E_TEST_STARTED`, `E2E_TEST_RESULT`, `E2E_TEST_COMPLETE` events
- `src/hooks/useWebSocket.ts` — Added E2E event listeners and cleanup
- `src/lib/api.ts` — Added `rerunE2ECase` method

**Backend dependency:** E2E service (✅ implemented)

---

### Phase 8: Logs Page — Filtering & Search ✅ COMPLETE

**Goal:** Add structured filtering (by level, service, time range) and full-text search to the Logs page.

**Implementation:** Replaced the single-select level dropdown with multi-select level checkboxes (info/warn/error/debug) for simultaneous filtering. Added search match highlighting via a reusable `HighlightedText` component. Replaced hover-only detail display with expandable `Collapsible` rows that show formatted JSON details on click. Level counter badges in the header now toggle with opacity feedback.

**Files created:**
- `src/components/shared/HighlightedText.tsx` — Reusable regex-based search match highlighter

**Files changed:**
- `src/pages/Logs.tsx` — Multi-select level checkboxes, search highlighting, expandable log details

**Backend dependency:** WebSocket log stream (✅ working)

---

### Phase 9: Sessions Page — Detail View ✅ COMPLETE

**Goal:** Add expandable session detail with full log timeline when clicking a session row.

**Implementation:** Enhanced the detail panel with: (1) Full session info fetch via `getSession` to access `errorMsg` and `metadata`; (2) Duration display using `formatDistance` in both the list and detail header; (3) Error message banner when session has an error; (4) Tabbed detail view with Logs, Errors (filtered error-only view), and Metadata (formatted JSON) tabs; (5) Added `remote_plugin_action` session type support.

**Files changed:**
- `src/pages/Sessions.tsx` — Added session info query, tabbed detail view, error banner, duration display, metadata tab

**Backend dependency:** Session service (✅ implemented)

---

### Phase 10: API Explorer — Auto-Refresh & Try-It ✅ COMPLETE

**Goal:** Ensure the Swagger UI always loads the latest OpenAPI spec and "Try It Out" works against the local backend.

**Implementation:** Injected `servers` with the correct `resolveApiBase()` URL into both backend and WordPress specs so Swagger UI's "Try it out" hits the right host. Added a 60-second auto-refresh toggle with last-refreshed timestamp. Extracted the Request History sidebar into a dedicated `RequestHistoryPanel` component with a `BodySection` sub-component, reducing the main page from 756 to ~470 lines. The refresh button now shows a spinning indicator during reload.

**Files created:**
- `src/components/api-explorer/RequestHistoryPanel.tsx` — Extracted request history panel with expandable body sections

**Files changed:**
- `src/pages/ApiExplorer.tsx` — Server injection, auto-refresh toggle, refactored to use RequestHistoryPanel

**Backend dependency:** OpenAPI handler (✅ implemented)

---

## Implementation Order (Recommended)

```
Phase 1 (Envelope Migration) ← foundation, everything else depends on this
  ↓
Phase 2 (Error Modal Enhancements)
  ↓
Phase 3 (Pagination)
  ↓
Phase 4 (Dashboard) — can be done independently
Phase 5 (Settings Debug Config) — can be done independently
Phase 6 (Site Health) — can be done independently
Phase 7 (E2E Tests) — can be done independently
Phase 8 (Logs Filtering) — can be done independently
Phase 9 (Sessions Detail) — can be done independently
Phase 10 (API Explorer) — can be done independently
```

Phases 4–10 are independent of each other and can be completed in any order after Phase 3.

---

*Ask: "Complete Phase X" to implement any phase.*
