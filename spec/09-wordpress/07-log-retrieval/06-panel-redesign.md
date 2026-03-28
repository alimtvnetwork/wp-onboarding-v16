# Remote Logs Panel — UI Redesign

> **Status:** Planned
> **Created:** 2026-03-28
> **Replaces:** 05-enhanced-log-viewer.md (partially)

---

## 1. Problem Statement

The current Remote Logs Panel has accumulated UI debt:

1. **Redundant text** — "Remote Logs" appears in both the collapsible header and inside the card
2. **3-tab fragmentation** — Overview / Viewer / Actions splits a single workflow into 3 clicks
3. **Scattered controls** — Reload, Max Lines, Download buttons appear in both Overview and Viewer tabs
4. **Not draggable** — Unlike Remote Plugins, Remote Snapshots, and the Error Modal, this panel isn't a windowed modal
5. **Visual clutter** — Too many badges, banners, and nested containers

---

## 2. Design Principles

- **Single-screen** — Everything visible without switching tabs
- **Toolbar consolidation** — One control bar, no duplicates
- **Draggable windowed modal** — Consistent with other site panels (reuse `useDraggable`)
- **Dense but readable** — Match the diagnostic UI style (text-xs, dark, monospace)

---

## 3. New Layout

### Modal Structure

```
┌─────────────────────────────────────────────────────┐
│ 📄 Remote Logs — {siteName}     [55.3 KB] [Demo] ✕ │  ← draggable header
├─────────────────────────────────────────────────────┤
│ [↻ Reload] [200 lines ▾]  [⬇ Download] [⚡ More ▾] │  ← single toolbar
├─────────────────────────────────────────────────────┤
│ RA: Info 200 · Error — · Trace 1 │ QU: Info 82     │  ← summary line
├─────────────────────────────────────────────────────┤
│ [Riseup Asia]  [QUpload]                            │  ← plugin tabs (if 2+)
│   [Info 200]  [Error —]  [Trace 1]                  │  ← log type tabs
│   ┌─ metadata: 200/352 lines · 59.5 KB · Truncated │
│   │  ⚠ Showing last 200 of 352 lines...            │
│   ├─────────────────────────────────────────────────┤
│   │  1  [28-Mar-26 9:40 AM] [Info] Database ready   │  ← log content viewer
│   │  2  [28-Mar-26 9:40 AM] [Debug] Scheduled...    │
│   │  ...                                            │
│   └─────────────────────────────────────────────────┘
└─────────────────────────────────────────────────────┘
```

### Toolbar "More" Dropdown

The `⚡ More` dropdown consolidates destructive/utility actions:
- Clear Logs (two-step)
- Clear All Plugins
- Email Logs
- Inspect Payload

This eliminates the Actions tab entirely.

### What's Removed

| Before | After |
|--------|-------|
| 3 top-level tabs (Overview / Viewer / Actions) | Single unified view |
| Duplicate "Reload" and "Max Lines" in Overview + Viewer | One toolbar |
| Overview stats cards (Total Size, Log Files, Archives) | Inline badges in header |
| Separate Actions tab with 3 cards | Dropdown menu |
| Collapsible card wrapper | Draggable modal |
| Redundant "Remote Logs" text repetitions | Single header |

---

## 4. Component Changes

### RemoteLogsPanel.tsx

- Convert from `Collapsible > Card` to a draggable windowed modal
- Remove top-level `Tabs` (Overview/Viewer/Actions)
- Merge all state into single view
- Add `useDraggable()` hook
- Add `data-error-modal` attribute for drag boundary
- Consolidate toolbar into one row
- Move Clear/Email/Inspect into a `DropdownMenu`

### LogContentViewer.tsx

- No structural changes (already clean)
- Keep search, severity filter, copy, export as-is

### New: RemoteLogsToolbar.tsx (optional extraction)

- Reload button
- Max lines selector
- Download All button
- More dropdown (Clear, Clear All, Email, Inspect Payload)

---

## 5. Tasks (in order)

1. **Write spec** — This document ✓
2. **Merge Overview + Viewer** — Remove 3-tab layout, show content directly
3. **Consolidate toolbar** — Single row, actions dropdown
4. **Remove duplicate headers** — One title line
5. **Add draggable modal** — Reuse `useDraggable` hook
6. **Clean up tabs** — Polish plugin/log-type sub-tabs

---

## 6. Files Affected

- `src/components/plugins/RemoteLogsPanel.tsx` — Major rewrite
- `src/components/plugins/LogContentViewer.tsx` — Minor tweaks if any
- `src/hooks/useDraggable.ts` — Reuse as-is
