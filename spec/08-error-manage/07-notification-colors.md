# Notification & Toast Color System

> **Created:** 2026-03-29  
> **Status:** ✅ Active

---

## 1. Overview

All user-facing notifications use **Sonner** (`sonner` package) via the `toast` import from `@/components/ui/sonner`. Notifications are styled using **CSS custom properties** (design tokens) defined in `src/index.css`, ensuring consistent theming across light and dark modes.

The error management system uses these notification types to surface API failures, success confirmations, and warnings — each with a distinct color identity.

---

## 2. Toast Types & Color Tokens

### 2.1 Base Toast (neutral)

Used for: `toast("message")` — generic notifications without semantic meaning.

| Token | Light | Dark | Purpose |
|-------|-------|------|---------|
| `--toast-bg` | `220 13% 18%` | `220 13% 14%` | Background |
| `--toast-fg` | `0 0% 98%` | `0 0% 98%` | Text |
| `--toast-border` | `220 13% 25%` | `220 13% 22%` | Border |
| `--toast-desc` | `215 15% 72%` | `215 15% 65%` | Description text |
| `--toast-shadow` | `0px 10px 28px rgba(0,0,0,0.40), 0px 2px 8px rgba(0,0,0,0.25)` | same | Drop shadow |

### 2.2 Success (green)

Used for: `toast.success("message")` — completed actions, saved changes.

| Token | Light | Dark |
|-------|-------|------|
| `--toast-success-bg` | `142 76% 95%` | `120 45% 12%` |
| `--toast-success-border` | `142 76% 80%` | `120 45% 26%` |
| `--toast-success-fg` | `142 76% 25%` | `120 45% 75%` |

### 2.3 Error (red)

Used for: `toast.error("message")` — API failures, crashes, validation errors.

| Token | Light | Dark |
|-------|-------|------|
| `--toast-error-bg` | `0 72% 96%` | `0 72% 15%` |
| `--toast-error-border` | `0 72% 60%` | `0 72% 30%` |
| `--toast-error-fg` | `0 72% 38%` | `0 72% 82%` |

### 2.4 Warning (amber)

Used for: `toast.warning("message")` — partial failures, outdated plugins, degraded states.

| Token | Light | Dark |
|-------|-------|------|
| `--toast-warning-bg` | `38 92% 95%` | `38 92% 13%` |
| `--toast-warning-border` | `38 92% 75%` | `38 92% 26%` |
| `--toast-warning-fg` | `38 92% 28%` | `38 92% 78%` |

### 2.5 Info (blue)

Used for: `toast.info("message")` — status updates, informational messages, demo mode.

| Token | Light | Dark |
|-------|-------|------|
| `--toast-info-bg` | `217 91% 96%` | `217 91% 13%` |
| `--toast-info-border` | `217 91% 78%` | `217 91% 26%` |
| `--toast-info-fg` | `217 91% 32%` | `217 91% 80%` |

---

## 3. Error Code → Notification Mapping

The error management system maps error codes to specific toast types and messages:

| Error Code | Toast Type | Message Pattern | Duration | Action |
|------------|-----------|-----------------|----------|--------|
| `E9003` | `toast.error` | "Network error" | default | View Details → Error Modal |
| `E9005` | `toast.error` | "API returned HTML instead of JSON" | default | View Details → Error Modal |
| `E9006` | `toast.error` | "Unexpected API response format" | default | View Details → Error Modal |
| `E9007` | `toast.error` | "Server error (5xx) — backend internal failure" | **15s** | Details → Error Modal |
| Publish success | `toast.success` | (handled via WebSocket) | default | — |
| Publish fail | `toast.error` | "Publish failed" / "Server error — check backend logs" | 5s / 15s | Details → Error Modal |
| Connection OK | `toast.success` | "Connection successful! WP {version}" | default | — |
| Connection fail | `toast.error` | Error message from API | **10s** | View Details → Error Modal |
| Plugin outdated | `toast.warning` | "Remote plugin is outdated..." | default | — |
| Clear logs | `toast.success` | "Remote logs cleared successfully" | default | — |
| Clear partial | `toast.warning` | "Partial clear: {details}" | default | — |
| Demo mode | `toast.info` | "Demo mode activated..." | default | — |
| Remote 500 | `toast.error` | "{error message}" | **15s** | View Details → Error Modal |

---

## 4. Code Examples

### 4.1 Standard error with modal escalation

```tsx
import { toast } from "sonner";
import { useErrorStore } from "@/stores/errorStore";

// Capture and show with "View Details" action
const captured = captureError(response.error, {
  endpoint: "/plugins/3/sites/2/publish",
  method: "POST",
});
toast.error("Publish failed", {
  action: { label: "Details", onClick: () => openErrorModal(captured) },
});
```

### 4.2 Server crash (E9007) — extended duration

```tsx
const isServerCrash = response.error.code === "E9007";
toast.error(
  isServerCrash ? "Server error — check backend logs" : "Publish failed",
  {
    description: isServerCrash
      ? "The backend encountered an internal error. Check terminal logs or the remote site's debug.log."
      : undefined,
    action: { label: "Details", onClick: () => openErrorModal(captured) },
    duration: isServerCrash ? 15000 : 5000,
  }
);
```

### 4.3 Remote site 500 — WordPress troubleshooting

```tsx
toast.error("Remote plugin action failed", {
  description: "Check the site's PHP error log or wp-content/debug.log",
  duration: 15000,
});
```

### 4.4 Success confirmation

```tsx
toast.success("Logs downloaded");
toast.success(`Connection successful! WP ${response.data.wpVersion}`);
```

### 4.5 Warning — partial result

```tsx
toast.warning(`Partial clear: ${failures.join("; ")}`);
toast.warning("No log retrieval endpoints available — the remote plugin may be outdated.");
```

### 4.6 Info — status update

```tsx
toast.info("Demo mode activated — showing sample log data");
toast.info("Clear token issued — confirm within " + data.expiresIn + "s");
```

---

## 5. Sonner Configuration

Defined in `src/components/ui/sonner.tsx`:

- **Position:** `bottom-right`
- **Rich colors:** `false` (uses custom tokens instead)
- **Close button:** enabled
- **Structure:** rounded-xl, px-4 py-3, Poppins font
- **Action buttons:** primary-colored, rounded-lg, font-medium

---

## 6. Z-Index Hierarchy

```
Toast (99999) > Error Modal (9999) > Regular modals (50)
```

Toasts are always clickable above all modals and dialogs. This is enforced in `index.css`:

```css
[data-sonner-toaster] {
  z-index: 99999 !important;
  pointer-events: auto !important;
}
```

---

## 7. Anti-Patterns

1. ❌ Never use `richColors={true}` — we use custom tokens
2. ❌ Never hardcode HSL values in toast calls — always use the design tokens
3. ❌ Never show raw error codes to users — always provide human-readable messages
4. ❌ Never use `toast()` (base) for errors — always use `toast.error()`
5. ❌ Never skip the "View Details" action for API errors — always link to Error Modal
6. ❌ Never use default duration for server crashes — use `15000` for 5xx errors
