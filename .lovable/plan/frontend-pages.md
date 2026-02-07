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

### Phase 4: Dashboard Enhancements

**Goal:** Make the Dashboard a useful operational overview with live stats.

**Files to change:**
- `src/pages/Dashboard.tsx` — Add summary cards (total sites, plugins, recent publishes, error count), recent activity feed, quick-action buttons
- Create `src/hooks/useDashboardStats.ts` — Aggregate data from multiple endpoints
- Create `src/components/dashboard/` — StatCard, RecentActivity, QuickActions components

**Backend dependency:** Existing endpoints (sites list, plugins list, publish history, error history)

---

### Phase 5: Settings Page — Response Debug Config

**Goal:** Add UI to toggle `ResponseDebug` settings (stack trace exposure, max frames) from the Settings page.

**Files to change:**
- `src/pages/Settings.tsx` — Add "Debug & Diagnostics" section with toggles for `IncludeStackTrace`, `IncludeInternalErrors`, `MaxStackFrames`
- `src/hooks/useSettings.ts` — Ensure save mutation includes new ResponseDebug fields
- `src/lib/api.ts` — Update `Settings` interface to include `responseDebug` block

**Backend dependency:** Config system already supports `ResponseDebug`

---

### Phase 6: Site Health Page — Deep Integration

**Goal:** Enhance Site Health with live connection indicators, uploader version checks, and bulk re-test.

**Files to change:**
- `src/pages/SiteHealth.tsx` — Add bulk "Re-test All" action, uploader version column, last-seen timestamps
- `src/hooks/useSiteHealth.ts` (create if needed) — Dedicated hook for health polling
- `src/components/sites/SiteHealthCard.tsx` (create if needed)

**Backend dependency:** Site health service (✅ scaffolded)

---

### Phase 7: E2E Tests Page — Live Results & Rerun

**Goal:** Polish the E2E Tests page with real-time result streaming and individual test rerun.

**Files to change:**
- `src/pages/Tests.tsx` — Add per-test status indicators, rerun button, result details expandable rows
- WebSocket event handling for test progress updates

**Backend dependency:** E2E service (✅ implemented in Phase 10)

---

### Phase 8: Logs Page — Filtering & Search

**Goal:** Add structured filtering (by level, service, time range) and full-text search to the Logs page.

**Files to change:**
- `src/pages/Logs.tsx` — Add filter bar (level checkboxes, service dropdown, date range picker)
- `src/components/shared/LogViewer.tsx` — Support filtered rendering
- `src/components/shared/LiveLogEntry.tsx` — Highlight search matches

**Backend dependency:** WebSocket log stream (✅ working)

---

### Phase 9: Sessions Page — Detail View

**Goal:** Add expandable session detail with full log timeline when clicking a session row.

**Files to change:**
- `src/pages/Sessions.tsx` — Add click-to-expand with session log timeline
- `src/components/errors/SessionLogsTab.tsx` — Reuse for inline session detail

**Backend dependency:** Session service (✅ implemented)

---

### Phase 10: API Explorer — Auto-Refresh & Try-It

**Goal:** Ensure the Swagger UI always loads the latest OpenAPI spec and "Try It Out" works against the local backend.

**Files to change:**
- `src/pages/ApiExplorer.tsx` — Configure swagger-ui-react with correct base URL, add refresh button

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
