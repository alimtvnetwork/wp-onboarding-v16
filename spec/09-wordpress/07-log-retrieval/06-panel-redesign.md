# Remote Logs Panel — UI Redesign

> **Status:** Complete
> **Created:** 2026-03-28
> **Replaces:** 05-enhanced-log-viewer.md (partially)

---

## 1. Problem Statement

The previous Remote Logs Panel had accumulated UI debt:

1. **Redundant text** — "Remote Logs" appeared in both the collapsible header and inside the card
2. **3-tab fragmentation** — Overview / Viewer / Actions split a single workflow into 3 clicks
3. **Scattered controls** — Reload, Max Lines, Download buttons appeared in both Overview and Viewer tabs
4. **Not draggable** — Unlike Remote Plugins, Remote Snapshots, and the Error Modal, the panel wasn't a windowed modal
5. **Visual clutter** — Too many badges, banners, and nested containers

---

## 2. Design Principles

- **Single-screen** — Everything visible without switching tabs
- **Toolbar consolidation** — One control bar, no duplicates
- **Draggable windowed modal** — Consistent with other site panels (reuses `useDraggable`)
- **Dense but readable** — Matches the diagnostic UI style (text-xs, dark, monospace)

---

## 3. Layout

### Modal Structure

```
┌─────────────────────────────────────────────────────┐
│ 📄 Remote Logs — {siteName}     [55.3 KB] [Demo] ✕ │  ← draggable header
├─────────────────────────────────────────────────────┤
│ [⊙ Load Logs] [200 lines ▾]  [⬇ Download]  [⋮]    │  ← single toolbar
├─────────────────────────────────────────────────────┤
│ 3 files · 55.3 KB · 2 archived                     │  ← summary line (pre-load)
├─────────────────────────────────────────────────────┤
│ RA: Info 200 · Error — · Trace 1 │ QU: Info 82     │  ← summary banner (post-load)
├─────────────────────────────────────────────────────┤
│ [Riseup Asia 201]  [QUpload 82]                     │  ← plugin tabs (if 2+) with totals
│   [Info 200]  [Error —]  [Trace 1]                  │  ← log type tabs with counts
│   ┌─ metadata: 200/352 lines · 59.5 KB · Truncated │
│   │  ⚠ Showing last 200 of 352 lines...            │
│   ├─────────────────────────────────────────────────┤
│   │  1  [28-Mar-26 9:40 AM] [Info] Database ready   │  ← log content viewer
│   │  2  [28-Mar-26 9:40 AM] [Debug] Scheduled...    │
│   │  ...                                            │
│   └─────────────────────────────────────────────────┘
└─────────────────────────────────────────────────────┘
```

### Pre-Load State

Before logs are loaded, the area below the toolbar shows either:
- **Summary line** — `3 files · 55.3 KB · 2 archived` (compact, no per-file cards)
- **No files** — Green bordered message: "No log files found"

### Toolbar ⋮ Dropdown

The vertical dots menu consolidates secondary/destructive actions:
- Refresh Status
- Inspect Payload (toggle)
- Email Logs
- ─── separator ───
- Clear Logs (two-step token confirmation)
- Clear All Plugins
- ─── separator ─── (demo mode only)
- Exit Demo

### Single-Plugin View

When only one plugin is available, plugin tabs are omitted. Instead, a subtle
uppercase label (`📄 QUPLOAD`) appears above the log-type tabs.

### What Was Removed

| Before | After |
|--------|-------|
| 3 top-level tabs (Overview / Viewer / Actions) | Single unified view |
| Duplicate "Reload" and "Max Lines" in Overview + Viewer | One toolbar |
| Per-file cards with line counts and size badges | Compact summary line |
| Overview stats cards (Total Size, Log Files, Archives) | Inline badge in header |
| Separate Actions tab with 3 cards | Dropdown menu |
| Dialog wrapper (caused drag conflicts) | Self-contained fixed overlay |
| Collapsible card wrapper | Draggable modal with backdrop |
| Redundant "Remote Logs" text repetitions | Single header |

---

## 4. Component Changes

### RemoteLogsPanel.tsx

- Self-contained fixed overlay with backdrop (`fixed inset-0 z-50`)
- No longer wrapped in a `<Dialog>` — renders directly when `showLogs` is true
- Single toolbar row with Load/Reload, Max Lines, Download, and ⋮ dropdown
- `useDraggable()` hook with `data-error-modal` attribute for drag boundary
- Pre-load summary: `{n} files · {size} · {archived}` (single line, no cards)
- Post-load summary banner: per-plugin line counts (Info/Error/Trace)
- Plugin tabs show total line count badges
- Single-plugin mode: uppercase label header via `showLabel` prop
- Log-type tabs: show formatted counts or `—` dash for empty categories

### LogContentViewer.tsx

- No structural changes (already clean)
- Keeps search, severity filter, copy, export as-is

### SiteCard.tsx / RemotePluginsPanel.tsx

- Removed `<Dialog>` wrapper around `<RemoteLogsPanel>`
- Conditional render: `{showLogs && <RemoteLogsPanel ... />}`
- Removed unused `VisuallyHidden` imports

---

## 5. Tasks (all complete)

1. **Write spec** — This document ✓
2. **Merge Overview + Viewer** — Remove 3-tab layout, show content directly ✓
3. **Consolidate toolbar** — Single row, actions dropdown ✓
4. **Remove duplicate headers** — One title line ✓
5. **Add draggable modal** — Fixed-position overlay with `useDraggable` hook ✓
6. **Replace file list with summary line** — Compact totals instead of per-file cards ✓
7. **Fix drag conflicts** — Removed Dialog wrapper that fought useDraggable transforms ✓
8. **Clean up tabs** — Polish plugin/log-type sub-tabs ✓

---

## 6. Files Affected

- `src/components/plugins/RemoteLogsPanel.tsx` — Major rewrite (self-contained fixed overlay)
- `src/components/plugins/LogContentViewer.tsx` — Unchanged
- `src/hooks/useDraggable.ts` — Reused as-is
- `src/components/sites/SiteCard.tsx` — Removed Dialog wrapper for logs panel
- `src/components/sites/RemotePluginsPanel.tsx` — Removed Dialog wrapper for debug logs
