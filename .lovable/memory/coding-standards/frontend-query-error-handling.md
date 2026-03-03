# Memory: coding-standards/frontend-query-error-handling
Updated: 2026-03-03

## suppressGlobalError Meta Pattern

React Query's global `onError` handlers in `App.tsx` (both `queryCache` and `mutationCache`) check for `meta.suppressGlobalError`. Any query or mutation that provides **local error feedback** (via `onError` callbacks, `isError` states, inline retry buttons, or toast notifications) MUST include `meta: { suppressGlobalError: true }` to prevent duplicate error displays from the global error modal.

### Where Applied

| File | Queries/Mutations |
|------|-------------------|
| `src/App.tsx` | Global `queryCache.onError` and `mutationCache.onError` both check the flag |
| `src/components/sites/RemotePluginsPanel.tsx` | remote-plugins query, forceSyncMutation, toggleMutation, deleteMutation |
| `src/components/sites/RemotePluginFileBrowser.tsx` | plugin files query |
| `src/components/sites/SiteCard.tsx` | site health & mappings queries |
| `src/hooks/useRemoteSnapshots.ts` | All queries (snapshots, settings, providers) and all mutations (create, delete, restore, updateSettings, fullBackup, incrementalBackup, import, cleanup) |
| `src/hooks/useSiteHealth.ts` | useCheckAllSitesHealth mutation |
| `src/hooks/useErrorHistory.ts` | All mutations (save, delete, clear, export) in both useErrorHistory and useErrorHistorySync |
| `src/components/settings/SnapshotSettingsTab.tsx` | Snapshot cron/settings queries |
| `src/pages/Sessions.tsx` | deleteMutation |
| `src/pages/RequestSessions.tsx` | deleteMutation, clearMutation |
| `src/pages/Tests.tsx` | startRun, rerunCase |
| `src/components/settings/SnapshotSettingsTab.tsx` | Snapshot cron/settings queries |

### Rule

When creating a new query or mutation that handles errors locally (shows its own toast, has `onError`, or uses `isError` for inline UI), **always** add `meta: { suppressGlobalError: true }`.
