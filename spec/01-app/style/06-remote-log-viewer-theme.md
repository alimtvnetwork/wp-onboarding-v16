# 06 — Remote Log Viewer Panel Theme

> **Created:** 2026-03-30  
> **Status:** ✅ Active  
> **Components:** `src/components/plugins/RemoteLogsPanel.tsx`, `src/components/plugins/LogContentViewer.tsx`  
> **Cross-ref:** [04-global-error-modal-theme.md](./04-global-error-modal-theme.md) — shared draggable pattern, [05-inline-error-diagnostic-theme.md](./05-inline-error-diagnostic-theme.md) — embedded inline errors

---

## 1. Overview

The Remote Log Viewer is a **fixed-position draggable overlay** (not a Radix Dialog) that displays remote WordPress log files. It consolidates status overview, log retrieval, content viewing, and management into a single panel with a dual-tab navigation system.

---

## 2. Overlay & Container

### 2.1 Backdrop

| Property | Value |
|----------|-------|
| Element | `<div>` with click-to-close |
| Position | `fixed inset-0 z-50` |
| Background | `bg-black/60` |

### 2.2 Card Container

| Property | Value |
|----------|-------|
| Position | `fixed left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 z-50` |
| Width | `w-[95vw] max-w-5xl` |
| Max height | `max-h-[90vh]` |
| Overflow | `overflow-y-auto` |
| Border | `border border-border/60` |
| Background | `bg-gradient-to-br from-background via-background to-muted/20` |
| Shadow | `shadow-2xl` |
| Border radius | `rounded-xl` |
| Data attribute | `data-error-modal` (shared with GlobalErrorModal for z-index coordination) |

---

## 3. Draggable Header

Shares the `useDraggable` hook pattern with the Global Error Modal.

| Property | Value |
|----------|-------|
| Cursor | `cursor-grab` / `active:cursor-grabbing` |
| User select | `select-none` |
| Border radius | `rounded-t-xl` |
| Border bottom | `border-border/60` |
| Background | `bg-muted/20` |
| Hover | `hover:bg-muted/30` |
| Padding | `py-2.5 px-4` |
| Transition | `transition-colors` |

### 3.1 Header Left

| Element | Style |
|---------|-------|
| Icon | `FileText`, `h-3.5 w-3.5 text-muted-foreground` |
| Title | `text-sm font-semibold truncate` — "Remote Logs" |
| Site name | `text-xs text-muted-foreground truncate` — "— {siteName}" |

### 3.2 Demo Mode Badge

| Property | Value |
|----------|-------|
| Text size | `text-[10px]` |
| Padding | `px-1.5 py-0.5` |
| Shape | `rounded-full` |
| Border | `border-warning/40` |
| Background | `bg-warning/15` |
| Text | `text-warning font-medium` |
| Icon | `FlaskConical`, `h-2.5 w-2.5` |

### 3.3 Status Pill (Header Right)

Primary-colored summary of total size and archive count:

| Property | Value |
|----------|-------|
| Text size | `text-[10px]` |
| Padding | `px-2 py-0.5` |
| Shape | `rounded-full` |
| Border | `border-primary/30` |
| Background | `bg-primary/10` |
| Text | `text-primary font-medium tabular-nums` |
| Content | `"{size} · {N} archived"` |

### 3.4 Header Action Buttons

| Button | Icon | Size | Style |
|--------|------|------|-------|
| Reset position | `Move` | `h-6 w-6 p-0` | `variant="ghost"`, shown only when dragged |
| Close | `X` | `h-6 w-6 p-0` | `variant="ghost"` |

---

## 4. Content Area

| Property | Value |
|----------|-------|
| Padding | `pt-3 pb-4 px-4` |

---

## 5. Clear Confirmation Bar

Appears when a clear token is active (two-step deletion pattern):

| Property | Value |
|----------|-------|
| Border | `border-destructive/30` |
| Background | `bg-destructive/5` |
| Padding | `px-3 py-2` |
| Margin bottom | `mb-4` |
| Text | `text-xs` |
| Icon | `AlertTriangle`, `h-3.5 w-3.5 text-destructive` |
| Confirm button | `variant="destructive"`, `h-6 px-2 text-xs` |
| Cancel button | `variant="ghost"`, `h-6 px-2 text-xs` |

---

## 6. Toolbar

Compact flex toolbar with primary actions and overflow menu.

| Property | Value |
|----------|-------|
| Layout | `flex flex-wrap items-center justify-between gap-1.5 px-1` |

### 6.1 Primary Actions

| Element | Style |
|---------|-------|
| Load/Reload button | `h-7 text-xs`, `variant="default"` (first load) / `variant="outline"` (reload) |
| Max lines selector | `h-7 w-[90px] text-xs`, options: 50, 100, 200, 500, 1000, 2000 |
| Download button | `variant="ghost"`, `h-7 text-xs` |

### 6.2 Overflow Menu (⋮)

| Item | Icon | Accent |
|------|------|--------|
| Refresh Status | `RefreshCw` | — |
| Inspect Payload | `Code2` | — |
| Email Logs | `Mail` | — |
| Clear Logs | `Trash2` | `text-destructive` |
| Clear All Plugins | `Trash2` | `text-destructive` |
| Exit Demo | `XCircle` | — (demo mode only) |

---

## 7. Dual-Tab Navigation System

### 7.1 Plugin Selection Tabs (Underline Style)

Used when multiple plugins have logs. Each tab shows the plugin label with a total line count badge.

| Property | Value |
|----------|-------|
| Container | `h-9 bg-transparent p-0 border-b border-border/30 rounded-none w-full` |
| Tab trigger | `text-xs font-medium rounded-none px-3 pb-2.5 pt-1` |
| Inactive | `border-b-2 border-transparent text-muted-foreground` |
| Hover | `hover:text-foreground/70` |
| Active | `border-b-2 border-primary text-primary bg-transparent shadow-none` |
| Transition | `transition-colors` |

#### Line Count Badge (in plugin tab)

| Property | Value |
|----------|-------|
| Text size | `text-[10px] tabular-nums` |
| Padding | `px-1.5 py-0.5` |
| Shape | `rounded-full` |
| Inactive | `bg-muted/40` |
| Active | `bg-primary/15 text-primary` |

### 7.2 Log Type Sub-Tabs (Pill Style)

Used within each plugin to switch between Info, Error, and Trace logs.

| Property | Value |
|----------|-------|
| Container | `h-8 gap-1.5 bg-muted/15 rounded-full p-1 border border-border/30` |
| Tab trigger | `rounded-full px-3.5 h-6 text-xs font-medium gap-1.5` |
| Inactive | `text-muted-foreground` |
| Transition | `transition-all duration-200` |

#### Active State Colors by Tab

| Tab | Active Background | Active Text |
|-----|-------------------|-------------|
| **Info** | `bg-primary` | `text-primary-foreground` |
| **Error** | `bg-destructive` | `text-destructive-foreground` |
| **Trace** | `bg-primary` | `text-primary-foreground` |

All active tabs include `shadow-sm`.

#### Inline Line Counter

| Property | Value |
|----------|-------|
| Text size | `text-[10px] tabular-nums opacity-80` |
| Content | Formatted line count or "—" if zero |

### 7.3 Auto-Selection Logic

On load, the first non-empty log tab is automatically selected, prioritizing: **Info > Error > Trace**.

---

## 8. Log Content Viewer (`LogContentViewer.tsx`)

### 8.1 Empty/Missing States

| State | Style |
|-------|-------|
| File does not exist | `text-sm text-muted-foreground` — "{label} does not exist" |
| File exists but empty | `text-sm text-muted-foreground` — "{label} is empty" |

### 8.2 Viewer Header

Compact summary showing file metadata:

| Element | Style |
|---------|-------|
| Exists badge | `text-[10px] font-mono`, green check variant |
| Size/Lines info | `text-[10px] tabular-nums text-muted-foreground` |
| Truncation warning | `text-[10px] text-warning` with `AlertTriangle` icon |

### 8.3 Viewer Toolbar

| Element | Style |
|---------|-------|
| Search toggle | Icon button, `h-6 w-6` |
| Severity filter | `h-6 w-[85px] text-[11px]` — All, Errors, Warnings, Info, Debug |
| Word wrap toggle | Icon button, active = `bg-muted` |
| Scroll-to-bottom | Icon button |
| Copy/Download | Icon buttons |

### 8.4 Search Bar

| Property | Value |
|----------|-------|
| Input | `h-7 text-xs font-mono`, `pl-7` (icon space) |
| Search icon | `h-3 w-3 text-muted-foreground` |
| Match counter | `text-[10px] tabular-nums text-muted-foreground` |
| Nav buttons | `h-5 w-5`, `ChevronUp` / `ChevronDown` |
| Current match highlight | `bg-amber-500/20 rounded` |
| Other matches | `bg-amber-500/8 rounded` |
| Search term highlight | `<mark>`, `bg-amber-400/40 text-inherit rounded-sm px-0.5` |

### 8.5 Log Lines — Severity Color Coding

| Severity | Color Class | Detected By |
|----------|-------------|-------------|
| **Error** | `text-red-400` | `fatal`, `exception`, `critical`, `error`, `fail` |
| **Warning** | `text-amber-400` | `warn`, `warning` |
| **Info** | `text-sky-400` | `notice`, `info` |
| **Debug** | `text-muted-foreground/70` | `debug`, `trace` |
| **Date** | `text-emerald-400` | ISO date pattern `YYYY-MM-DD` |
| **Plain** | `text-foreground` | No pattern match |

### 8.6 Line Number Gutter

| Property | Value |
|----------|-------|
| Width | `w-12 shrink-0` |
| Alignment | `text-right pr-3` |
| Text | `text-muted-foreground/50 text-[11px] tabular-nums` |
| Selection | `select-none` |

### 8.7 Code Container

| Property | Value |
|----------|-------|
| Font | `font-mono text-[12px]` |
| Line height | `leading-[1.6]` |
| Word wrap | Togglable: `whitespace-pre-wrap break-all` (on) / `whitespace-pre` (off) |
| ScrollArea height | `h-[500px]` |
| Border | `border border-border/50` |
| Background | `bg-background/80` |
| Border radius | `rounded-xl` |

---

## 9. File Overview (Pre-Load State)

| State | Style |
|-------|-------|
| Has files | `text-xs text-muted-foreground px-1` — "{N} files · {size} · {N} archived" |
| No files | `rounded-lg border-border/40 bg-primary/5 py-6`, `CheckCircle` in `text-primary` |
| No endpoints | `rounded-lg border-border/40 bg-warning/5 py-8`, `AlertTriangle` in `text-warning` |

---

## 10. Loading Skeleton

| Property | Value |
|----------|-------|
| Container | `animate-pulse rounded-lg border-border/40 bg-muted/10 p-4` |
| Tab placeholders | `h-5 rounded-full bg-muted` |
| Content area | `h-[400px] rounded-xl border-border/50 bg-background/70` |
| Line placeholders | `h-3 rounded bg-muted`, random width 60–100% |

---

## 11. Raw Payload Inspector

Toggle-able debug panel for inspecting the raw API response.

| Property | Value |
|----------|-------|
| Container | `rounded-xl border-border/60 bg-muted/10 p-3` |
| Title | `text-xs font-medium uppercase tracking-[0.15em] text-muted-foreground` |
| Size badge | `text-[10px] font-mono`, char count |
| ScrollArea height | `h-[300px]` |
| Code | `text-[11px] font-mono text-muted-foreground whitespace-pre-wrap break-all leading-relaxed` |
| Border | `border-border/40 bg-background/80` |

---

## 12. Email Dialog

Standard `Dialog` with:

| Property | Value |
|----------|-------|
| Recipient input | `type="email"`, optional, defaults to admin |
| Archive toggle | `Checkbox` — "Include archived log rotations" |
| Submit button | Primary with `Mail` icon |
| Cancel button | `variant="outline"` |

---

## 13. Color Accent Summary

| Context | Accent | Purpose |
|---------|--------|---------|
| Primary green | Status pill, active Info/Trace tabs, no-files state | Plugin health, positive state |
| Destructive red | Active Error tab, clear confirmation, delete actions | Errors, destructive actions |
| Warning amber | Demo badge, truncation warnings, no-endpoints state | Caution, degraded |
| Amber (search) | `bg-amber-500/20`, `bg-amber-400/40` marks | Search match highlighting |
| Sky blue | `text-sky-400` | Info-severity log lines |
| Emerald green | `text-emerald-400` | Date-stamped log lines |

---

## 14. Anti-Patterns

| ❌ Don't | ✅ Do Instead |
|----------|---------------|
| Use a Radix Dialog for the log viewer | Use a `fixed` overlay with `useDraggable` |
| Nest borders around the tab system | Flat tab design with underline/pill styles |
| Show all three log tabs even when empty | Auto-select first non-empty (Info > Error > Trace) |
| Use the same tab style for both levels | Underline for plugins, pill for log types |
| Hardcode severity colors | Use the `SEV_CLASSES` mapping consistently |
| Skip the loading skeleton | Always show animated placeholder during fetch |
