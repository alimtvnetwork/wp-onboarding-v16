# Memory: coding-standards/frontend-query-error-handling
Updated: 2026-03-13

## suppressGlobalError Meta Pattern

React Query's global `onError` handlers in `App.tsx` (both `queryCache` and `mutationCache`) check for `meta.suppressGlobalError`. Any query or mutation that provides **local error feedback** (via `onError` callbacks, `isError` states, inline retry buttons, or toast notifications) MUST include `meta: { suppressGlobalError: true }` to prevent duplicate error displays from the global error modal.

### Shared Hooks for Error Capture

- **`useCaptureQueryError(isError, error, meta)`** — Use in components with `useQuery`/`useApiQuery` that suppress global errors. Automatically calls `captureException` via `useEffect` when errors occur.
- **`useCaptureOnError(meta)`** — Returns an error handler for `useMutation.onError` callbacks. Call in the component body, use the returned function in `onError`.

Both are in `src/hooks/useCaptureQueryError.ts`.

### Where Applied

| File | Queries/Mutations |
|------|-------------------|
| `src/App.tsx` | Global `queryCache.onError` and `mutationCache.onError` both check the flag |
| `src/components/sites/RemotePluginsPanel.tsx` | remote-plugins query (useCaptureQueryError), forceSyncMutation (captureException in onError), toggleMutation (captureException in onError), deleteMutation (captureException in onError), handleBulkActivate (captureException per failed result), handleBulkDeactivate (captureException per failed result), handleBulkDelete (captureException per failed result) |
| `src/components/sites/RemotePluginFileBrowser.tsx` | plugin files query (useCaptureQueryError) |
| `src/components/sites/SiteCard.tsx` | snapshots query, cron jobs query (useCaptureQueryError) |
| `src/hooks/useRemoteSnapshots.ts` | All queries (snapshots, settings, providers — useEffect) and all mutations (create, delete, restore, updateSettings, fullBackup, incrementalBackup, import, cleanup — handleSnapshotError) |
| `src/hooks/useSiteHealth.ts` | useCheckAllSitesHealth mutation (captureException in onError) |
| `src/hooks/useErrorHistory.ts` | All mutations (save, delete, clear, export) in both useErrorHistory and useErrorHistorySync — SKIP (recursive) |
| `src/hooks/useSettings.ts` | `useSaveSettings` mutation (captureException in onError) |
| `src/components/settings/SnapshotSettingsTab.tsx` | Snapshot cron queries (×2), snapshots query (useCaptureQueryError) |
| `src/pages/Sessions.tsx` | deleteMutation (useCaptureOnError) |
| `src/pages/RequestSessions.tsx` | deleteMutation, clearMutation (useCaptureOnError) |
| `src/pages/Tests.tsx` | startRun, rerunCase (useCaptureOnError) |

### Rule

When creating a new query or mutation that handles errors locally (shows its own toast, has `onError`, or uses `isError` for inline UI), **always** add `meta: { suppressGlobalError: true }` AND ensure `captureException` is called (via `useCaptureQueryError`, `useCaptureOnError`, or manual `captureException`).
