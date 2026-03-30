# 04 — Global Error Modal Visual Theme

> **Created:** 2026-03-30  
> **Status:** ✅ Active  
> **Components:** `src/components/errors/GlobalErrorModal.tsx`, `BackendSection.tsx`, `FrontendSection.tsx`, `DelegatedSection.tsx`  
> **Cross-ref:** [03-notification-toast-theme.md](./03-notification-toast-theme.md) — toast-to-modal escalation

---

## 1. Overview

The Global Error Modal is a full-diagnostic overlay that surfaces captured errors with structured context across three request layers: **Backend** (Go), **Frontend** (React), and **Delegated** (remote WordPress/PHP). It is draggable, responsive, and supports a queue for multiple errors.

---

## 2. Modal Container

### 2.1 Sizing & Responsive Behavior

| Breakpoint | Width | Height | Border Radius |
|------------|-------|--------|---------------|
| `< sm` (mobile) | `100vw` | `100vh` | `rounded-none` (full-screen) |
| `sm` (≥640px) | `95vw` | `95vh` | `rounded-xl` |
| `lg` (≥1024px) | `max-w-6xl` (~72rem) | `95vh` | `rounded-xl` |

### 2.2 Container Tokens

| Property | Value | Token |
|----------|-------|-------|
| Background | `bg-background` | `--background` |
| Border | `border-border/70` | 70% opacity border |
| Shadow | `shadow-2xl` | Deep elevation |
| Overflow | `overflow-hidden` | Clip content |

---

## 3. Header

The header is **draggable** (cursor-grab) and contains the error title, badges, and queue navigation.

### 3.1 Header Tokens

| Property | Value |
|----------|-------|
| Background | `bg-muted/20` |
| Border bottom | `border-border/60` |
| Padding | `px-4 py-3` (mobile) → `px-6 py-4` (sm+) |

### 3.2 Error Level Icon Colors

| Level | Icon Class |
|-------|------------|
| `error` | `text-destructive` (red) |
| `warn` | `text-warning` (amber) |
| `info` | `text-muted-foreground` |

### 3.3 Error Code Badge

Uses `Badge variant="secondary"` with level-specific background:

| Level | Badge Class |
|-------|-------------|
| `error` | `bg-red-900/30 text-red-400` |
| `warn` | `bg-yellow-900/30 text-yellow-400` |
| `info` | `bg-blue-900/30 text-blue-400` |

### 3.4 Error Source Badge

Classifies the error origin with a colored outline badge:

| Source | Border | Background | Text | Icon |
|--------|--------|------------|------|------|
| **Local Backend** | `border-emerald-500/40` | `bg-emerald-500/10` | `text-emerald-400` | `Server` |
| **Frontend** | `border-blue-500/40` | `bg-blue-500/10` | `text-blue-400` | `Monitor` |
| **Delegated Remote** | `border-orange-500/40` | `bg-orange-500/10` | `text-orange-400` | `Globe` |

### 3.5 Queue Navigation

Visible only when `errorQueue.length > 1`:

| Element | Style |
|---------|-------|
| Prev/Next buttons | `variant="outline"`, `h-7 w-7`, `border-border/60 bg-background/60` |
| Counter badge | `font-mono text-xs`, `bg-muted/60 border-border/60` |
| "Copy All" button | Hidden on mobile (icon-only shown), full on `sm+` |

---

## 4. Section Tabs (Backend / Frontend / Delegated)

### 4.1 Tab Bar

| Property | Value |
|----------|-------|
| Container | `rounded-xl border-border/60 bg-muted/20 p-1` |
| Background | `bg-muted/15` |
| Border bottom | `border-border/60` |
| Padding | `px-4 pt-3 pb-2` (mobile) → `px-6 pt-4` (sm+) |

### 4.2 Tab Button States

| Tab | Active Variant | Inactive | Accent |
|-----|---------------|----------|--------|
| **Backend** | `variant="default"` (primary green) | `variant="ghost"` | — |
| **Frontend** | `variant="secondary"` | `variant="ghost"` | `border-border/40` |
| **Delegated** | `variant="secondary"` | `variant="ghost"` | `border-orange-500/30 text-orange-500` |

### 4.3 Tab Label Responsive

| Tab | Mobile (`< sm`) | Desktop (`sm+`) |
|-----|----------------|-----------------|
| Backend | "Backend" | "Backend" |
| Frontend | "Frontend" | "Frontend" |
| Delegated | "Delegated" | "Delegated Logs" |

---

## 5. Section-Specific Themes

### 5.1 Backend Section (`BackendSection.tsx`)

Contains sub-tabs: **Overview**, **Stack**, **Execution**, **Request**, **Traversal**, **Session Logs**, **Log**

#### Error Log Panel (Amber Theme)

The Go backend `error.log.txt` viewer uses an **amber** accent to distinguish server-side logs:

| Property | Value |
|----------|-------|
| Border | `border-amber-500/30` |
| Background | `bg-amber-500/10` |
| Header bg | `bg-amber-500/10` |
| Title text | `text-amber-700 dark:text-amber-300` |
| Badge | `border-amber-500/40 text-amber-300` |
| Log text | `text-amber-700 dark:text-amber-300` |
| ScrollArea height | `h-[320px]` |

### 5.2 Frontend Section (`FrontendSection.tsx`)

Contains sub-tabs: **Overview**, **Stack**, **Execution**, **Suggestions**

Uses standard design tokens (`bg-muted`, `text-foreground`, `text-muted-foreground`). No special accent color — inherits the modal's base theme.

### 5.3 Delegated Section (`DelegatedSection.tsx`)

Contains: Delegated Server Log, PHP Stack Trace table, Response Body viewer

#### Delegated Log Panel (Orange Theme)

Remote WordPress/PHP logs use an **orange** accent to visually distinguish delegated data:

| Property | Value |
|----------|-------|
| Border | `border-orange-500/30` |
| Background | `bg-orange-500/5` |
| Header bg | `bg-orange-500/10` |
| Header border | `border-orange-500/20` |
| Title text | `text-orange-700 dark:text-orange-300` |
| Badge | `border-orange-500/40 text-orange-300` |
| Log text | `text-orange-700 dark:text-orange-300` |
| ScrollArea height | `h-[320px]` |

#### PHP Stack Trace Table

| Property | Value |
|----------|-------|
| Min width | `480px` (horizontal scroll on mobile) |
| Container | `overflow-x-auto` |
| Header bg | `bg-muted/30` |
| Row hover | Standard table hover |

---

## 6. Footer

| Property | Value |
|----------|-------|
| Background | `bg-muted/10` |
| Border top | `border-border/60` |
| Padding | `px-4 py-3` (mobile) → `px-6 py-4` (sm+) |
| Layout | `flex-wrap justify-end gap-2` |
| Close button | `variant="outline"`, `border-border/60 bg-background/60` |
| Download/Copy | Dropdown menus with primary styling |

---

## 7. Draggable Behavior

- Header acts as drag handle (`cursor-grab` / `active:cursor-grabbing`)
- Grip icon (`GripHorizontal`) shown on `sm+` only
- Reset position button appears when dragged (`RotateCcw` icon)
- Position resets on modal close

---

## 8. Z-Index & Layering

```
Toast (99999) > Error Modal (9999) > Regular modals (50) > Content (1)
```

The error modal uses `Dialog` from Radix UI which manages its own z-index via the `DialogContent` portal. Toasts must always remain above the error modal.

---

## 9. Color Accent Summary

| Layer | Accent Color | Purpose |
|-------|-------------|---------|
| Backend (Go) | **Emerald green** | Source badge, active tab |
| Backend Log | **Amber** | `error.log.txt` panel |
| Frontend (React) | **Blue** | Source badge |
| Delegated (PHP) | **Orange** | Source badge, tab accent, log panels, PHP traces |
| Error level | **Red** | Error badges, destructive icon |
| Warning level | **Yellow/Amber** | Warning badges |

---

## 10. Anti-Patterns

| ❌ Don't | ✅ Do Instead |
|----------|---------------|
| Use `bg-red-500` for the modal background | Use `bg-background` with accent badges |
| Mix orange and amber for the same layer | Orange = delegated (PHP), Amber = backend log |
| Hardcode pixel sizes for responsive | Use `sm:` / `lg:` breakpoint prefixes |
| Show full "Delegated Logs" label on mobile | Truncate to "Delegated" on `< sm` |
| Skip the drag handle icon on desktop | Always show `GripHorizontal` on `sm+` |
| Use blurred shadows on panels | Use sharp `shadow-2xl` or `shadow-sm` |
