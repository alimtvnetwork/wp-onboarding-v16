

## Plan: Snapshot Section Improvements + Config File Updates

This plan covers 6 items from your request, executed sequentially.

---

### 1. Add `adminPageSlug` to Example Config Files

**Files to edit:**
- `backend/scripts/wp-plugin-config.example.json` -- Add `"adminPageSlug": "your-plugin-slug"` field
- `backend/config.example.json` -- Add `"adminPageSlug"` inside a `"wordpress"` section if relevant (or note it's a wp-plugin-config concern only)

---

### 2. Duplicate Snapshot Settings into the Snapshot Section (RemoteSnapshotsPanel)

Currently, snapshot configuration (schedules, storage mode, worker pool, retention policy) lives **only** in `Settings > Snapshots`. The `RemoteSnapshotsPanel` (the per-site snapshot dialog) has a "Settings" tab but doesn't include the parallel execution or retention settings.

**Action:** Extract the snapshot config controls (worker count, batch size, storage mode, retention policy) into a reusable component, then render it in both:
- `src/components/settings/SnapshotSettingsTab.tsx` (existing location)
- `src/components/sites/RemoteSnapshotsPanel.tsx` (Settings tab inside the snapshot dialog)

**New file:** `src/components/settings/SnapshotConfigPanel.tsx` -- shared component containing:
- Storage Mode selector (Single / Per-Table)
- Worker Pool settings (Worker Count slider, Batch Size input)
- Retention Policy (`SnapshotRetentionPolicy`)

---

### 3. Improve Snapshot Error Messages with "Check Logs" Button

When snapshot creation fails with a generic error like "The handler for the route is invalid", the toast/error should:
- Show the actual error message
- Include a **"Check Logs"** button that navigates to `/errors` (the error history page)

**Files to edit:**
- `src/hooks/useRemoteSnapshots.ts` -- Update `onError` callbacks for `createMutation`, `fullBackupMutation`, `restoreMutation`, etc. to use `toast.error()` with a custom action button
- Pattern: Use `toast.error("...", { action: { label: "Check Logs", onClick: () => navigate("/errors") } })` or similar using `sonner`'s action API

---

### 4. Add "Copy All Logs" Button to Error Toasts / Snapshot Error Display

Add a standardized copy button alongside error messages in the snapshot section:
- In the `SnapshotDetailDrawer` error section (line ~1153-1162 of `SnapshotSettingsTab.tsx`), add a copy button next to the error text
- In the `RemoteSnapshotsPanel` snapshot rows that show errors, add a small copy icon button
- Uses existing `toClipboardText()` utility from `src/lib/logText.ts`

**Files to edit:**
- `src/components/settings/SnapshotSettingsTab.tsx` -- Add copy button in `SnapshotDetailDrawer` error section
- `src/components/sites/RemoteSnapshotsPanel.tsx` -- Add copy button in error display areas
- `src/hooks/useRemoteSnapshots.ts` -- Enhanced error toasts with copy action

---

### Summary of Changes

| # | Task | Files |
|---|------|-------|
| 1 | Add `adminPageSlug` to example configs | `backend/scripts/wp-plugin-config.example.json`, `backend/config.example.json` |
| 2 | Shared snapshot config panel in both Settings and Snapshot dialog | New: `SnapshotConfigPanel.tsx`, Edit: `SnapshotSettingsTab.tsx`, `RemoteSnapshotsPanel.tsx` |
| 3 | "Check Logs" button on snapshot errors | `useRemoteSnapshots.ts` |
| 4 | Copy button for error logs | `SnapshotSettingsTab.tsx`, `RemoteSnapshotsPanel.tsx`, `useRemoteSnapshots.ts` |

