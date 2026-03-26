# Error Modal — Specification

> **Location:** `src/components/errors/GlobalErrorModal.tsx`
> **Status:** Implemented
> **Updated:** 2026-03-26

> **Diagram:** [delegated-error-flow.mmd](./delegated-error-flow.mmd)

---

## 1. Overview

The Global Error Modal displays structured error diagnostics for backend, frontend, and delegated (PHP/WordPress) failures. It supports multi-error queue navigation, copy/download actions, and a draggable interface.

---

## 2. Draggable Feature

The modal header is a **drag handle** — users can click/tap and drag anywhere on the header bar (excluding buttons/links) to reposition the modal on screen. Supports both mouse and touch input.

### Implementation

- **Hook:** `src/hooks/useDraggable.ts` — `useDraggable()` returns `{ style, onMouseDown, onTouchStart, resetPosition, isDragged }`
- **Drag handle:** The `<DialogHeader>` element receives `onMouseDown`, `onTouchStart`, and cursor classes (`cursor-grab` / `active:cursor-grabbing`)
- **Visual indicator:** A `GripHorizontal` icon in the header signals draggability (hidden on mobile)
- **Reset button:** When the modal has been dragged, a `RotateCcw` button appears to snap it back to center
- **Auto-reset:** Position resets when the modal closes or a new error is selected
- **Exclusions:** Clicks/taps on buttons, links, and inputs inside the header do NOT initiate a drag

### Behavior

- **Mouse:** Left-click only (ignores right-click, middle-click)
- **Touch:** Single-finger drag via `touchstart`/`touchmove`/`touchend`/`touchcancel`; `preventDefault` on `touchmove` blocks page scrolling during drag
- Transform-based positioning (no layout reflow)
- Shared `applyMove` function ensures identical clamping for both input methods
- Boundary clamping: at least 80px of the modal stays visible on every screen edge (`EDGE_MARGIN = 80`)

---

## 3. Top-Level Sections

Three top-level section buttons in the action bar:

| Section | Icon | Theme | Visibility |
|---------|------|-------|-----------|
| **Backend** | `Server` | Default | Always |
| **Frontend** | `Monitor` | Default | Always |
| **Delegated Logs** | `Globe` | Orange (`border-orange-500/30`) | Always (label: "Delegated Logs" on desktop, "Delegated" on mobile) |

---

## 4. Backend Section — Sub-tabs

The Backend section contains nested tabs:

| Tab | Icon | Description |
|-----|------|-------------|
| **Overview** | `AlertCircle` | Error message, code, timestamp, HTTP status, site URL |
| **Log** | `Terminal` | **Go Backend Error Log** (`error.log.txt`) — session-scoped |
| **Execution** | `Activity` | Backend execution logs and method stack frames |
| **Stack** | `Code2` | Go stack traces (envelope, raw, session) |
| **Session** | `FileText` | Full session diagnostic logs (conditional on `sessionId`) |
| **Request** | `Network` | HTTP request details and session diagnostics |
| **Traversal** | `Route` | Envelope error chain and method traversal (conditional) |

### Go Backend Error Log (Log Tab) — Prominent Section

The Log tab features the **Go Backend Error Log** as a visually prominent, amber-themed panel:

- **Header:** Amber background (`bg-amber-500/10`) with amber border (`border-amber-500/30`)
- **Title:** "Go Backend Error Log" with two badges: `error.log.txt` and `Session-scoped`
- **Content:** Monospace scrollable viewer (400px height) with amber-tinted border
- **Actions:** Refresh, Copy, Download buttons
- **States:** Loading spinner, error with retry, empty state, content view
- **Session-scoped:** Only shows entries from the current server session (filtered by `SessionStartTime`)

---

## 5. Delegated Section

See `spec/01-app/error-manage/delegated-error-logs.md` for full specification.

---

## 6. Frontend Section

Displays:
- Parsed frontend stack frames (filterable: internal/external)
- Raw stack trace toggle
- Suggested fixes based on error code

---

## 7. Error Queue Navigation

When multiple errors exist:
- Previous/Next buttons with `ChevronLeft`/`ChevronRight`
- Badge showing `{current}/{total}` position
- "Copy All" button to export all queued errors as markdown

---

## 8. Actions (Footer)

| Action | Type | Description |
|--------|------|-------------|
| **Download** | Dropdown | Download as `.md` report or raw `error.log.txt` |
| **Copy** | Dropdown | Copy full report, report + backend logs, or raw error log |
| **Close** | Button | Closes the modal |

---

## 9. Demo Mode

A mock/demo mode allows previewing all modal sections without a live Go backend.

- **Settings trigger:** Settings → Developer → "Open Demo Modal" button
- **Keyboard shortcut:** `Ctrl+Shift+E` (global, any page)
- **Mock data:** `src/components/errors/demoErrorData.ts` — creates 2 sample errors:
  1. **Delegated error** — full PHP stack traces, `DelegatedRequestServer`, envelope errors, backend logs, click path
  2. **Backend-only error** — Go stack trace, database connection failure, no delegated data
- **Queue testing:** Both errors are queued for prev/next navigation testing

---

## 10. Files

- `src/components/errors/GlobalErrorModal.tsx` — Main modal shell with draggable header
- `src/hooks/useDraggable.ts` — Reusable drag-to-move hook with boundary clamping
- `src/components/errors/BackendSection.tsx` — Backend tab with sub-tabs
- `src/components/errors/FrontendSection.tsx` — Frontend stack and fixes
- `src/components/errors/DelegatedSection.tsx` — PHP/WordPress delegated errors
- `src/components/errors/ErrorModalActions.tsx` — Download and Copy dropdown menus
- `src/components/errors/ErrorModalTypes.ts` — Shared types
- `src/components/errors/errorReportGenerator.ts` — Report generation utilities
- `src/components/errors/demoErrorData.ts` — Mock error data for demo mode
- `src/components/errors/errorLogAdapter.ts` — Maps backend ErrorLog to CapturedError
