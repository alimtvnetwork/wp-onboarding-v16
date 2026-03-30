# 03 — Notification & Toast Visual Theme

> **Created:** 2026-03-30  
> **Status:** ✅ Active  
> **Cross-ref:** [spec/08-error-manage/07-notification-colors.md](../../08-error-manage/07-notification-colors.md) — full error-code-to-toast mapping

---

## 1. Overview

All user-facing notifications use **Sonner** via `@/components/ui/sonner`. Toasts are styled with CSS custom properties (HSL design tokens) in `src/index.css` — **never hardcode colors in component code**.

---

## 2. Color Tokens by Type

### 2.1 Success — Green

| Token | Dark Mode HSL | Visual |
|-------|---------------|--------|
| `--toast-success-bg` | `120 45% 12%` | Deep green background |
| `--toast-success-border` | `120 45% 26%` | Subtle green border |
| `--toast-success-fg` | `120 45% 75%` | Bright green text |

**Use for:** Completed actions, saved changes, clipboard copies, connection confirmations.

### 2.2 Error — Red

| Token | Dark Mode HSL | Visual |
|-------|---------------|--------|
| `--toast-error-bg` | `0 72% 15%` | Deep red background |
| `--toast-error-border` | `0 72% 30%` | Muted red border |
| `--toast-error-fg` | `0 72% 82%` | Bright red text |

**Use for:** API failures, crashes, validation errors. Always include "View Details" action for API errors.

### 2.3 Warning — Amber

| Token | Dark Mode HSL | Visual |
|-------|---------------|--------|
| `--toast-warning-bg` | `38 92% 13%` | Deep amber background |
| `--toast-warning-border` | `38 92% 26%` | Muted amber border |
| `--toast-warning-fg` | `38 92% 78%` | Bright amber text |

**Use for:** Partial failures, outdated plugins, degraded states.

### 2.4 Info — Blue

| Token | Dark Mode HSL | Visual |
|-------|---------------|--------|
| `--toast-info-bg` | `217 91% 13%` | Deep blue background |
| `--toast-info-border` | `217 91% 26%` | Muted blue border |
| `--toast-info-fg` | `217 91% 80%` | Bright blue text |

**Use for:** Status updates, demo mode, informational confirmations.

### 2.5 Base Neutral

| Token | Dark Mode HSL | Visual |
|-------|---------------|--------|
| `--toast-bg` | `220 13% 14%` | Dark charcoal background |
| `--toast-border` | `220 13% 22%` | Subtle border |
| `--toast-fg` | `0 0% 98%` | Near-white text |
| `--toast-desc` | `215 15% 65%` | Muted description text |

**Use for:** Only truly neutral messages with no semantic meaning. Prefer typed toasts.

---

## 3. Duration Behavior

| Duration | Constant | When to Use |
|----------|----------|-------------|
| **4s** | default | Success confirmations, info messages |
| **10s** | `10000` | Errors with "View Details" → Error Modal action |
| **15s** | `15000` | Server crashes (E9007), remote 500s |

### Auto-Open Rule

- **E9005** (API returned HTML) → **bypasses toast entirely**, auto-opens Global Error Modal

---

## 4. Z-Index Hierarchy

```
Toast (99999) > Error Modal (9999) > Regular modals (50)
```

Enforced in `index.css`:

```css
[data-sonner-toaster] {
  z-index: 99999 !important;
  pointer-events: auto !important;
}
```

Toasts must **always** be clickable above all modals and dialogs.

---

## 5. Sonner Configuration

Defined in `src/components/ui/sonner.tsx`:

| Property | Value | Rationale |
|----------|-------|-----------|
| Position | `bottom-right` | Non-intrusive, near action area |
| Rich colors | `false` | Custom tokens used instead |
| Close button | `true` | User dismissal always available |
| Border radius | `rounded-xl` | Consistent with card theme |
| Font | Poppins | Matches app typography |
| Action buttons | `bg-primary`, `rounded-lg` | Primary green accent |

---

## 6. Toast Action Pattern

All API error toasts **must** include a "View Details" action that opens the Global Error Modal:

```tsx
toast.error("Publish failed", {
  action: {
    label: "Details",
    onClick: () => openErrorModal(captured),
  },
  duration: 10000,
});
```

---

## 7. Anti-Patterns

| ❌ Don't | ✅ Do Instead |
|----------|---------------|
| `richColors={true}` | Use custom HSL tokens |
| Hardcode `hsl(0 72% 15%)` in components | Reference `--toast-error-bg` token |
| `toast()` for errors | `toast.error()` with typed semantics |
| Skip "View Details" on API errors | Always link to Error Modal |
| Default duration for server crashes | Use `15000` for 5xx/E9007 |
| Show raw error codes to users | Human-readable messages only |
| `toast.success()` for partial results | `toast.warning()` for partial/degraded |

---

## 8. Code Reference

### Correct Usage by Type

```tsx
// ✅ Success — default 4s
toast.success("Logs downloaded");
toast.success(`Connection successful! WP ${version}`);

// ✅ Error — 10s with modal escalation
toast.error("Request failed", {
  action: { label: "Details", onClick: () => openErrorModal(captured) },
  duration: 10000,
});

// ✅ Warning — default 4s
toast.warning("Remote plugin is outdated — consider updating");

// ✅ Info — default 4s
toast.info("Demo mode activated — showing sample data");

// ✅ Server crash — 15s extended
toast.error("Server error — check backend logs", {
  description: "The backend encountered an internal error.",
  duration: 15000,
});
```
