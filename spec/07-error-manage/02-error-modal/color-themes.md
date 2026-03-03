# Error Modal — Color Theme & Design Token Reference

> **Version:** 1.0.0  
> **Updated:** 2026-03-03  
> **Status:** Active  
> **Purpose:** Definitive color mapping for every error-related UI element, using Tailwind CSS semantic tokens and explicit HSL values. Any AI or developer must use this document to replicate the exact visual appearance.

---

## 1. Design System Tokens (index.css)

All error-related components consume these CSS custom properties from `src/index.css`. **Never hardcode raw color values in components** — always use semantic tokens.

### Light Mode (`:root`)

```css
--destructive: 0 84% 60%;           /* Red — errors, delete actions */
--destructive-foreground: 0 0% 100%; /* White text on destructive bg */
--warning: 38 92% 50%;              /* Amber — warnings */
--warning-foreground: 0 0% 100%;
--success: 142 76% 36%;             /* Green — success states */
--success-foreground: 0 0% 100%;
--info: 217 91% 60%;                /* Blue — informational */
--info-foreground: 0 0% 100%;
--muted: 210 40% 96.1%;             /* Light gray — backgrounds */
--muted-foreground: 215.4 16.3% 46.9%;
--primary: 222.2 47.4% 11.2%;       /* Dark navy — primary actions */
--primary-foreground: 210 40% 98%;
```

### Dark Mode (`.dark`)

```css
--destructive: 0 72% 51%;
--destructive-foreground: 0 0% 100%;
--warning: 38 92% 50%;
--warning-foreground: 0 0% 100%;
--success: 120 45% 39%;
--success-foreground: 0 0% 100%;
--info: 217 91% 60%;
--info-foreground: 0 0% 100%;
--muted: 0 0% 18%;
--muted-foreground: 0 0% 60%;
--primary: 120 45% 39%;
--primary-foreground: 0 0% 100%;
```

---

## 2. Error Level Color Mapping

### Level Badge Colors

Used in `GlobalErrorModal`, `ErrorDetailModal`, `ErrorHistoryDrawer` headers:

```typescript
const levelColors = {
  error: "bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400",
  warn:  "bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400",
  info:  "bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400",
};
```

### Level Icon Colors

Used in `ErrorHistoryDrawer` list and `ErrorDetailModal` header:

```typescript
const levelIconColors = {
  error: "text-red-500",       // AlertCircle
  warn:  "text-yellow-500",    // AlertTriangle
  info:  "text-blue-500",      // Info
};
```

### Header Icon Color (GlobalErrorModal)

```tsx
<AlertCircle className={cn(
  "h-5 w-5 sm:h-6 sm:w-6 shrink-0",
  selectedError.level === "error" ? "text-destructive"
    : selectedError.level === "warn" ? "text-warning" : "text-muted-foreground"
)} />
```

---

## 3. Backend Section — Tab-Specific Color Themes

### Overview Tab

| Element | Tailwind Classes | Purpose |
|---------|-----------------|---------|
| Error banner | `border-destructive/30 bg-destructive/5`, `text-destructive` | Backend error message |
| Delegated info banner | `border-blue-500/30 bg-blue-500/5`, `text-blue-600 dark:text-blue-400` | DelegatedRequestServer.AdditionalMessages (NEW v2.0.0) |
| Missing delegation warning | `border-amber-500/30 bg-amber-500/5`, `text-amber-600 dark:text-amber-400` | Backend bug indicator |
| HTTP status badge (≥400) | `variant="destructive"` → `bg-destructive text-destructive-foreground` | Failed status code |
| HTTP status badge (<400) | `variant="secondary"` → `bg-secondary text-secondary-foreground` | Success status code |
| Session badge | `variant="outline"` | Session ID indicator |
| Stack traces badge | `variant="outline"` | Stack trace availability |

### Stack Tab — Multi-Tier Color Coding

Each technology tier has a distinct color for instant visual identification:

| Tier | Dot Color | Badge Classes | Text Classes | Background |
|------|-----------|--------------|-------------|------------|
| **Go Backend** | `bg-blue-500` | `bg-blue-500/10 border-blue-500/30` | `text-blue-600` | — |
| **Delegated Server** (v2.0.0) | `bg-purple-500` | `bg-purple-500/10 border-purple-500/30 text-purple-600` | `text-purple-600`, `text-purple-500` | `bg-purple-500/5 border-purple-500/20` |
| **PHP Legacy** | `bg-orange-500` | `bg-orange-500/10 border-orange-500/30 text-orange-600` | `text-orange-500` | `bg-orange-500/5 border-orange-500/20` |
| **Session diagnostics** | — | — | — | `bg-muted` |

#### Stack Tab Code Example — Delegated Server Section (Purple Theme)

```tsx
<div className="space-y-2">
  <div className="flex items-center gap-2">
    <div className="w-2 h-2 rounded-full bg-purple-500" />
    <h4 className="text-sm font-medium">Delegated Server Stack</h4>
    <Badge variant="outline" className="font-mono">{delegatedServer.Method}</Badge>
    <Badge variant={delegatedServer.StatusCode >= 400 ? "destructive" : "secondary"}>
      {delegatedServer.StatusCode}
    </Badge>
  </div>
  <p className="text-xs font-mono text-purple-600 dark:text-purple-400 break-all">
    {delegatedServer.DelegatedEndpoint}
  </p>
  {delegatedServer.StackTrace?.map((line, i) => (
    <p key={i} className="text-xs font-mono text-purple-500">{line}</p>
  ))}
</div>
```

#### Stack Tab Code Example — PHP Frames Table (Orange Theme)

```tsx
<td className="p-2 font-mono text-orange-500">
  {frame.class ? `${frame.class}::${frame.function}()` : `${frame.function}()`}
</td>
```

### Request Tab — 3-Hop Chain Color Coding

```
Node 1: React → Go     → Blue   (bg-blue-500 dot, bg-blue-500/10 badge)
Node 2: Go → Delegated → Orange (bg-orange-500 dot, bg-orange-500/10 badge, text-orange-600)
Node 3: Delegated Resp  → Purple (bg-purple-500 dot, bg-purple-500/10 badge, text-purple-600)
```

### Traversal Tab — Color Coding

Same tier colors as Stack Tab, applied to endpoint flow badges and detail sections.

### Log Tab

Session log line highlighting:

```typescript
function LogLine({ line }: { line: string }) {
  if (line.includes("STAGE:") || line.match(/^[─═]+$/))
    return <div className="text-primary font-semibold">{line}</div>;
  if (line.includes("[ERROR]") || line.includes("[FATAL]"))
    return <div className="text-destructive">{line}</div>;
  if (line.includes("[WARN]"))
    return <div className="text-amber-600 dark:text-amber-400">{line}</div>;
  if (line.includes("✓") || line.includes("success"))
    return <div className="text-green-600 dark:text-green-400">{line}</div>;
  return <div>{line}</div>;
}
```

### Execution Tab

Go call chain table:

```tsx
<tr className={cn(
  "border-t border-border/50",
  index === 0 && "bg-primary/5"  // First frame highlighted with primary tint
)}>
```

---

## 4. Frontend Section — Color Themes

### Overview Tab

| Element | Classes | Purpose |
|---------|---------|---------|
| Trigger badge | `bg-primary/5 border-primary/20` | Component → Action breadcrumb |
| Source badge | `variant="secondary"` | Function source (font-mono) |
| First call chain entry | `text-primary font-semibold` | Primary caller highlighted |
| Last click in path | `text-primary` | Most recent interaction |

### Stack Tab

| Element | Classes |
|---------|---------|
| React execution chain area | `bg-blue-500/5` border, blue-themed |
| First parsed frame row | `bg-primary/5` highlight |
| Internal frames | `opacity-50` dimmed |
| Debug mode tip | `bg-muted text-muted-foreground` |

### Fixes Tab

```tsx
<span className="w-5 h-5 rounded-full bg-primary/10 text-primary text-xs flex items-center justify-center">
  {index + 1}
</span>
```

---

## 5. Section Toggle Buttons

```tsx
<Button
  variant={activeSection === "backend" ? "default" : "outline"}
  className="gap-1.5"
>
  <Server className="h-3.5 w-3.5" /> Backend
</Button>
<Button
  variant={activeSection === "frontend" ? "default" : "outline"}
  className="gap-1.5"
>
  <Monitor className="h-3.5 w-3.5" /> Frontend
</Button>
```

Active section uses `variant="default"` (primary bg), inactive uses `variant="outline"`.

---

## 6. Error History Drawer Colors

```typescript
// Item card states
const cardClasses = {
  selected: "bg-accent border-primary",
  default:  "bg-card hover:bg-accent/50",
};

// Delete/clear buttons
const destructiveButton = "text-destructive hover:text-destructive";
```

---

## 7. Error Queue Badge

```tsx
<Button
  variant="ghost"
  className="relative h-8 px-2 text-destructive hover:text-destructive hover:bg-destructive/10"
>
  <AlertCircle className="h-4 w-4" />
  <Badge variant="destructive" className="absolute -top-1 -right-1 h-5 min-w-5 px-1 text-xs">
    {count}
  </Badge>
</Button>
```

---

## 8. App Error Boundary

Fallback UI colors:

```tsx
<div className="flex min-h-[60vh] items-center justify-center px-6 py-12">
  <div className="w-full max-w-lg space-y-4 rounded-lg border bg-background p-6">
    <h1 className="text-lg font-semibold">Something went wrong</h1>
    <p className="text-sm text-muted-foreground">...</p>
    <Button variant="outline">View error details</Button>
    <Button>Reload</Button>  {/* Primary action */}
  </div>
</div>
```

---

## 9. Color Usage Rules

1. **Never use raw color classes** in error components — always reference design tokens or the documented tier colors
2. **Tier colors are fixed**: Blue = Go, Purple = Delegated (v2.0.0), Orange = PHP/Legacy
3. **Error levels map to**: `text-destructive` (error), `text-warning` or `text-amber-*` (warn), `text-muted-foreground` or `text-blue-*` (info)
4. **Backgrounds use low opacity**: `bg-destructive/5`, `bg-blue-500/5`, `bg-purple-500/5`, `bg-orange-500/5`
5. **Borders use medium opacity**: `border-destructive/30`, `border-blue-500/30`, `border-purple-500/20`
6. **Dark mode overrides**: Most colors work in both themes via `dark:` prefix; where applicable use `dark:text-amber-400`, `dark:text-blue-400`, `dark:text-purple-400`

---

## Cross-References

- [Error Modal Spec](./readme.md) — Full modal structure, data model, visual layout diagrams
- [React Components Reference](./react-components.md) — Component code with all color classes in context
- [Design System](../../../src/index.css) — Root CSS custom properties

---

*Color Theme Reference v1.0.0 — created: 2026-03-03*
