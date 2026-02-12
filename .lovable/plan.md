


## Plan: Go Upload Performance Optimization + Core Plugin Dashboard + Snapshot UX

> Created: 2026-02-12  
> Status: **Feature A complete. Feature B complete. Feature C (Snapshot UX) — audit in progress.**

---

## Feature A: Go Upload Performance Optimization (5 fixes) ✅ COMPLETE

### Phase A1: Multipart upload ✅ DONE
### Phase A2: Remove pre-upload status check ✅ DONE
### Phase A3: Reduce ZIP compression level ✅ DONE
### Phase A4: Reduce verbose broadcasting ✅ DONE
### Phase A5: Update memory ✅ DONE

---

## Feature B: Core Plugin Dashboard ✅ COMPLETE

### Phase B1: Dashboard component ✅ DONE
### Phase B2: Route + navigation + badge link ✅ DONE
### Phase B3: API integration ✅ DONE

---

## Feature C: Snapshot UX — Audit & Fixes

### Problem Statement
The snapshot panel (RemoteSnapshotsPanel) needs UX improvements: users should be able to name snapshots, choose Full vs Incremental with parent selection, see worker pool settings inline, and have a clear "running" status. Errors from PHP endpoint communication need graceful handling.

### What's Already Done ✅

| Feature | Status | Notes |
|---------|--------|-------|
| 3-tab layout (Snapshots, Timeline, Settings) | ✅ Done | Grid 3-col TabsList |
| Create snapshot with scope (all/wordpress/content/custom) | ✅ Done | Scope selector + custom table picker |
| Full Backup & Incremental Backup buttons | ✅ Done | Separate buttons in advanced row |
| Snapshot list with hierarchy (full → incremental nesting) | ✅ Done | Parent-child grouping with `ml-6` indent |
| Delete with cascade warning | ✅ Done | Warns about incremental children count |
| Restore with full/selective mode | ✅ Done | Table picker for selective restore |
| Settings tab (provider, schedules, retention, worker pool, storage mode) | ✅ Done | Full config with sync indicator |
| Detail dialog (metadata, tables, download ZIP) | ✅ Done | Grid layout + table badges |
| Import ZIP | ✅ Done | File input with `.zip` accept |
| Timeline view | ✅ Done | Vertical timeline with dot indicators |
| Error handling with rich capture + PHP stack traces | ✅ Done | SnapshotApiError + error store |
| Auto-polling when snapshots are running (5s) | ✅ Done | `refetchInterval` conditional |
| WebSocket snapshot_complete notifications | ✅ Done | Toast with "View Details" action |
| Snapshot comparison (diff two snapshots) | ✅ Done | Side-by-side table/row diff |
| Worker pool size config (1–10) | ✅ Done | In Settings tab via SnapshotConfigPanel |
| Storage mode (single vs per-table) | ✅ Done | In Settings tab |

### What's Pending 🔧

| # | Task | Priority | Description |
|---|------|----------|-------------|
| C1 | **Snapshot Name Input** | High | Add a name/label field to the create form so users can name their snapshots. Date/time auto-appended. |
| C2 | **Unified Create Form (Type Selector)** | High | Replace the separate "Create" + "Full Backup" + "Incremental" buttons with a single create form that has a Type dropdown (Full / Incremental). When "Incremental" is selected, show a parent snapshot picker (list of completed full snapshots). |
| C3 | **Inline Worker Pool Quick-Set** | ✅ Done | Compact slider in create form area showing current worker count (1–10) with instant save to settings. |
| C4 | **Progress Indicator** | ✅ Done | Real-time WebSocket-driven progress banner with per-table badge status, overall progress bar, worker count, and dismiss on completion. |
| C5 | **Error Suppression on First Load** | ✅ Done | Initial load flag via `useRef` suppresses error state on first fetch, showing empty state instead. |
| C6 | **Tab Layout Stability** | ✅ Done | Added `shrink-0 overflow-hidden` to TabsList to prevent overflow at narrow widths. |

### Execution Order

1. **C1 + C2** (together) — Redesign create snapshot area with name input + type selector + parent picker
2. **C3** — Add inline worker count display
3. **C4** — Wire up progress endpoint polling
4. **C5 + C6** — Polish: initial load suppression + tab verification

---

## Dependencies & Risks

| Risk | Mitigation |
|------|-----------|
| PHP endpoint errors on first load | Use initial-load flag to suppress; show clean empty state |
| Progress endpoint may not be deployed on all sites | Graceful fallback to "Running…" badge if progress call fails |
| Incremental parent picker needs completed full snapshots | Filter snapshot list to `status === "complete"` and `snapshot_type !== "incremental"` |
