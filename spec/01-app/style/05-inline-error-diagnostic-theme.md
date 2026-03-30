# 05 — Inline Error Diagnostic Component Theme

> **Created:** 2026-03-30  
> **Status:** ✅ Active  
> **Component:** `src/components/plugins/InlineErrorDiagnostic.tsx`  
> **Used by:** `RemoteLogsPanel.tsx` (remote log retrieval errors, mismatch detection)  
> **Cross-ref:** [04-global-error-modal-theme.md](./04-global-error-modal-theme.md) — escalation target

---

## 1. Overview

The `InlineErrorDiagnostic` renders API failures **inline** within functional panels instead of immediately opening the Global Error Modal. This gives users context-specific error visibility with an option to escalate to the full modal for deeper diagnostics.

---

## 2. Container

| Property | Value |
|----------|-------|
| Border radius | `rounded-xl` |
| Border | `border-2 border-destructive/40` |
| Background | `bg-destructive/5` |
| Shadow | `shadow-lg` |
| Overflow | `overflow-hidden` |

The double-width border (`border-2`) differentiates inline errors from standard cards which use `border`.

---

## 3. Header Bar

| Property | Value |
|----------|-------|
| Background | `bg-destructive/10` |
| Border bottom | `border-destructive/20` |
| Padding | `px-4 py-2.5` |
| Layout | `flex items-center justify-between` |

### 3.1 Header Left

| Element | Style |
|---------|-------|
| Icon | `AlertTriangle`, `h-4 w-4 text-destructive` |
| Title | `text-sm font-semibold text-destructive` — always "API Error" |
| Error code badge | `variant="outline"`, `text-[10px] font-mono border-destructive/30 text-destructive` |
| HTTP status badge | `variant="destructive"` if ≥400, `variant="secondary"` otherwise, `text-[10px] font-mono` |

### 3.2 Header Right (Action Buttons)

All buttons: `size="sm" variant="ghost"`, `h-6 w-6 p-0`, icon-only (`h-3 w-3`).

| Button | Icon | Visibility | Action |
|--------|------|------------|--------|
| Copy | `Copy` | Always | Copies raw JSON diagnostic to clipboard |
| Escalate | `ExternalLink` | When `onOpenGlobalModal` provided | Opens Global Error Modal with full context |
| Dismiss | `X` | When `onDismiss` provided | Removes this inline error |

---

## 4. Message Body

| Property | Value |
|----------|-------|
| Padding | `px-4 py-3` |
| Message | `text-sm text-foreground` |
| Details | `text-xs text-muted-foreground` |

### 4.1 Request Summary

Compact monospace row showing HTTP method and endpoint:

| Element | Style |
|---------|-------|
| Method badge | `variant="outline"`, `text-[10px] font-mono` |
| Endpoint | `text-xs text-muted-foreground font-mono`, truncated at `max-w-[300px]` |

---

## 5. Delegated Remote Error Block (Orange Theme)

Displayed when the error involves a remote WordPress/PHP server. Uses the same orange accent as the Global Error Modal's Delegated section.

| Property | Value |
|----------|-------|
| Container | `rounded-lg border border-orange-500/30 bg-orange-500/5 p-3` |
| Title | `text-xs font-medium text-orange-600 dark:text-orange-400`, uppercase tracking |
| Icon | `Globe`, `h-3 w-3` |
| Endpoint text | `font-mono text-xs opacity-80`, break-all |
| Additional messages | `text-xs text-muted-foreground` |

---

## 6. Collapsible Diagnostic Details

Expandable section containing backend traces, PHP frames, and response bodies. Only rendered when diagnostic data exists.

### 6.1 Trigger Button

| Property | Value |
|----------|-------|
| Layout | `w-full flex items-center gap-2` |
| Padding | `px-4 py-2` |
| Border top | `border-destructive/15` |
| Text | `text-xs text-muted-foreground` |
| Hover | `hover:bg-destructive/5` |
| Transition | `transition-colors` |
| Chevron | `ChevronRight` (collapsed) / `ChevronDown` (expanded), `h-3 w-3` |

### 6.2 Content Badges (in trigger row)

| Badge | Style | When shown |
|-------|-------|------------|
| "Backend" | `variant="outline"`, `text-[9px] h-4` | Backend trace exists |
| "{N} PHP frames" | `variant="outline"`, `text-[9px] h-4 border-orange-500/30 text-orange-500` | Delegated PHP stack exists |
| "Response Body" | `variant="outline"`, `text-[9px] h-4` | Remote response body exists |

### 6.3 Detail Panels

All detail panels share a consistent structure:

| Property | Value |
|----------|-------|
| Container padding | `px-4 pb-3`, `space-y-3` between panels |
| Section spacing | `space-y-1.5` |
| Copy button | `size="sm" variant="ghost"`, `h-5 px-1.5` |

#### PHP Error Stack (Orange accent)

| Property | Value |
|----------|-------|
| Title | `text-xs font-medium text-orange-500`, with `Globe` icon |
| ScrollArea | `max-h-40 rounded-lg border-orange-500/20 bg-orange-500/5 p-2` |
| Code | `text-[11px] font-mono text-muted-foreground whitespace-pre-wrap break-all leading-relaxed` |

#### Remote Stack Trace (Orange accent)

| Property | Value |
|----------|-------|
| Title | `text-xs font-medium text-orange-500`, with `Globe` icon |
| ScrollArea | `max-h-32 rounded-lg border-orange-500/20 bg-orange-500/5 p-2` |
| Code | `text-[11px] font-mono text-muted-foreground whitespace-pre-wrap break-all` |

#### Remote Response Body (Neutral)

| Property | Value |
|----------|-------|
| Title | `text-xs font-medium text-muted-foreground`, with `Server` icon |
| ScrollArea | `max-h-32 rounded-lg border-border/60 bg-muted/20 p-2` |
| Code | `text-[11px] font-mono text-muted-foreground whitespace-pre-wrap break-all` |

#### Go Backend Trace (Neutral)

| Property | Value |
|----------|-------|
| Title | `text-xs font-medium text-muted-foreground`, with `Server` icon |
| ScrollArea | `max-h-32 rounded-lg border-border/60 bg-muted/20 p-2` |
| Code | `text-[11px] font-mono text-muted-foreground whitespace-pre-wrap break-all` |

---

## 7. Footer (Timestamp)

| Property | Value |
|----------|-------|
| Border top | `border-destructive/10` |
| Padding | `px-4 py-1.5` |
| Text | `text-[10px] text-muted-foreground/60 font-mono` |
| Content | ISO 8601 timestamp |

---

## 8. Color Accent Summary

| Layer | Accent | Token Pattern |
|-------|--------|---------------|
| Container & header | **Red (destructive)** | `border-destructive/40`, `bg-destructive/10`, `text-destructive` |
| Delegated (PHP) blocks | **Orange** | `border-orange-500/30`, `bg-orange-500/5`, `text-orange-500` |
| Backend (Go) blocks | **Neutral** | `border-border/60`, `bg-muted/20`, `text-muted-foreground` |
| Response body | **Neutral** | Same as backend |

This mirrors the Global Error Modal's color language: **red** for the error container, **orange** for delegated/PHP, **neutral** for Go backend traces.

---

## 9. Behavioral Rules

### 9.1 Escalation Flow

```
InlineErrorDiagnostic
  └─ "Open in error modal" button (ExternalLink icon)
       └─ onOpenGlobalModal callback
            └─ Preserves original ApiClientError
                 └─ Global Error Modal opens with full context
```

### 9.2 Dismissal

- Inline errors are stored in component state (`CapturedInlineError[]`)
- Dismiss removes from the array by index
- Errors are **not** persisted across panel re-mounts

### 9.3 Multiple Errors

Multiple inline diagnostics stack vertically within the host panel. Each is independently dismissable and expandable.

---

## 10. Anti-Patterns

| ❌ Don't | ✅ Do Instead |
|----------|---------------|
| Show inline error AND trigger global modal simultaneously | Show inline first, let user escalate manually |
| Use `toast.error()` for errors that have inline diagnostics | Use `InlineErrorDiagnostic` for panel-specific errors |
| Use red accent for PHP/delegated traces | Use orange for delegated, red only for the error container |
| Skip the escalation button | Always provide `onOpenGlobalModal` when a captured error exists |
| Persist inline errors in global state | Keep in local component state — they are contextual |
