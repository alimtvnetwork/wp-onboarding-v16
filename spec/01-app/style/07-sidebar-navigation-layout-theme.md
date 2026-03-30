# Sidebar Navigation & Page Layout Theme

> **Version:** 1.0.0  
> **Updated:** 2026-03-30  
> **Status:** Active  
> **Purpose:** Documents the fixed sidebar, route highlighting, responsive collapse behavior, header bar, and theming tokens.

---

## 1. Layout Architecture

### 1.1 Shell Structure

```
┌──────────────────────────────────────────────────┐
│ flex min-h-screen bg-background overflow-x-hidden│
│ ┌──────────┬────────────────────────────────────┐│
│ │ Sidebar  │  flex-1 flex flex-col min-w-0      ││
│ │ w-64     │ ┌────────────────────────────────┐ ││
│ │ shrink-0 │ │ Header  h-14 border-b bg-card  │ ││
│ │          │ ├────────────────────────────────┤ ││
│ │          │ │ <main>  flex-1 p-3 sm:p-6      │ ││
│ │          │ │   <Outlet />                   │ ││
│ │          │ └────────────────────────────────┘ ││
│ └──────────┴────────────────────────────────────┘│
└──────────────────────────────────────────────────┘
```

| Region | Classes / Styles | Notes |
|--------|-----------------|-------|
| Root wrapper | `flex min-h-screen bg-background overflow-x-hidden` | Full-height flex row |
| Desktop sidebar | `hidden md:flex w-64 flex-col shrink-0` | Fixed 256px width, hidden below `md` |
| Content column | `flex-1 flex flex-col min-w-0` | `min-w-0` prevents flex overflow |
| Header | `h-14 border-b border-border bg-card` | Fixed height, card surface |
| Main | `flex-1 p-3 sm:p-6 overflow-x-hidden` | Responsive padding |

### 1.2 Responsive Breakpoints

| Breakpoint | Sidebar behavior | Header |
|-----------|-----------------|--------|
| `< md` (< 768px) | Hidden; opens as a `Sheet` overlay from left | Shows hamburger `Menu` icon |
| `≥ md` (768px+) | Visible as a fixed `w-64` column | Hamburger hidden |

---

## 2. Sidebar Theming Tokens

The sidebar uses its own HSL token namespace (`--sb-*`) defined in `index.css`, independent of the global theme tokens. This allows sidebar theme switching without affecting the main content area.

### 2.1 Token Reference

| Token | Purpose | Night Blue (default) |
|-------|---------|---------------------|
| `--sb-bg` | Sidebar background | `216 40% 7%` |
| `--sb-divider` | Border & section dividers | `217 33% 12%` |
| `--sb-hover-from` | Gradient start on hover/active | `217 33% 12%` |
| `--sb-hover-to` | Gradient end on hover/active | `217 33% 17%` |
| `--sb-hover-border` | Inset border on hover/active | `216 20% 17%` |
| `--sb-accent` | Active border-left, brand icon | `217 91% 60%` |
| `--sb-text` | Default nav text | `218 11% 65%` |
| `--sb-text-hover` | Hover/active nav text, brand title | `210 20% 98%` |

### 2.2 Available Themes

Themes are applied via `data-sidebar-theme` attribute on the sidebar root or `:root`.

| Theme | Attribute Value | Accent Hue |
|-------|----------------|------------|
| Night Blue (default) | `night-blue` | Blue (`217°`) |
| Midnight Purple | `midnight-purple` | Purple (`271°`) |
| Emerald Dark | `emerald-dark` | Green (`142°`) |
| Solar White | `solar-white` | Orange (`24°`) — light background |

---

## 3. Navigation Item States

Nav items use utility classes defined in `index.css`, not inline Tailwind:

### 3.1 Class Reference

| Class | State | Visual |
|-------|-------|--------|
| `.sb-nav-idle` | Default | `color: hsl(--sb-text)`, transparent left border |
| `.sb-nav-idle:hover` | Hover | Gradient bg, `--sb-text-hover` color, `--sb-accent/0.6` left border, inset shadow |
| `.sb-nav-active` | Active route | Gradient bg, `--sb-text-hover` color, solid `--sb-accent` left border, stronger inset shadow |

### 3.2 Active Route Indicator

```
border-l-[3px]  ─── Left accent border
├─ idle:     transparent
├─ hover:    hsl(--sb-accent / 0.6)   ← 60% opacity accent
└─ active:   hsl(--sb-accent)          ← Full opacity accent
```

Active state is determined by React Router's `NavLink` `isActive` prop. The `cn()` helper conditionally applies `sb-nav-active` or `sb-nav-idle`.

### 3.3 Shared Item Structure

```tsx
<NavLink className={({ isActive }) =>
  cn(
    "sb-nav-item flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium",
    "border-l-[3px] transition-colors duration-150",
    isActive ? "sb-nav-active" : "sb-nav-idle"
  )
}>
  <Icon className="h-4 w-4 shrink-0" />
  {label}
</NavLink>
```

- Item spacing: `space-y-0.5` between items
- Icon size: `h-4 w-4` with `shrink-0`
- Transition: `duration-150` on color/background

---

## 4. Sidebar Internal Layout

```
┌─────────────────────────┐
│ Brand bar  (p-4 sm:p-6) │  ← Plug icon + "WP Publish" in --sb-accent / --sb-text-hover
├─────────────────────────┤
│ Nav list   (px-3 sm:px-4│  ← Scrollable (overflow-y-auto), flex-1
│            pb-4)        │
│   Dashboard             │
│   Sites                 │
│   Plugins               │
│   ...                   │
│   Settings              │
├─────────────────────────┤  ← 1px --sb-divider border-top
│ Footer     (px-3 py-3)  │  ← "About" link + version badge
└─────────────────────────┘
```

- Brand icon uses inline `style={{ color: "hsl(var(--sb-accent))" }}`
- Brand title uses inline `style={{ color: "hsl(var(--sb-text-hover))" }}`
- Footer divider: `1px solid hsl(var(--sb-divider))`
- Version badge: `variant="outline"` with `border-current/30`, `font-mono text-xs`

---

## 5. Mobile Sidebar (Sheet Overlay)

| Property | Value |
|----------|-------|
| Component | Radix `Sheet` via shadcn |
| Side | `left` |
| Width | `w-64` (matches desktop) |
| Background | `hsl(var(--sb-bg))` via inline style |
| Padding | `p-0` (content manages its own) |
| Title | `VisuallyHidden` for a11y |
| Close behavior | Clicking a nav item calls `onMobileClose` to dismiss |

The mobile sheet renders the same `SidebarContent` component with an `onNavigate` callback that closes the sheet on route change.

---

## 6. Header Bar

### 6.1 Layout

```
┌──────────────────────────────────────────────────────┐
│ [☰]  Page Title              [🔔][⚠️][📡][WS][v][🌙]│
└──────────────────────────────────────────────────────┘
```

| Slot | Component | Visibility |
|------|-----------|-----------|
| Hamburger | `Menu` icon button | `md:hidden` |
| Page title | `<h1>` from `routeNames` map | Always, `truncate` |
| Notification panel | `NotificationPanel` | Always |
| Error queue badge | `ErrorQueueBadge` | Always |
| Publish progress | `GlobalPublishProgress` | Always |
| WebSocket indicator | `WebSocketIndicator` | Label hidden on mobile (`hidden sm:inline-flex`) |
| Version badge | `VersionBadge` | `hidden sm:inline-flex` |
| Theme toggle | Dropdown (Light/Dark/System) | Always |

### 6.2 Styling

- Background: `bg-card`
- Border: `border-b border-border`
- Height: `h-14` (56px)
- Padding: `px-3 sm:px-6`
- Title: `text-lg sm:text-xl font-semibold text-foreground tracking-tight`
- Icon buttons: `variant="ghost"`, `text-muted-foreground hover:text-foreground`
- Divider between WS indicator and version: `h-4 w-px bg-border` (hidden on mobile)
- Item gap: `gap-1.5 sm:gap-3`

---

## 7. Anti-Patterns

| ❌ Don't | ✅ Do |
|----------|-------|
| Use raw color values in sidebar components | Use `--sb-*` CSS tokens via `hsl(var(...))` |
| Add hover scale/zoom to nav items | Use color/background transitions only (`duration-150`) |
| Use Tailwind `bg-*` classes for sidebar backgrounds | Use inline `style` with `--sb-*` tokens for theme flexibility |
| Hard-code page titles in the header | Use the `routeNames` lookup map |
| Put sidebar content outside `SidebarContent` component | Keep shared between desktop aside and mobile Sheet |
| Add a separate close button to mobile sidebar | Use `onNavigate` callback to auto-close on route change |

---

## Cross-References

- [Dark Theme Tokens](./01-dark-theme-tokens.md) — Global `--background`, `--card`, `--border` tokens used by Layout/Header
- [Interactive Card Hover](./02-interactive-card-hover.md) — Hover patterns; sidebar uses gradient hover, not card-style border accent
- [Notification Toast Theme](./03-notification-toast-theme.md) — Toast z-index layers above sidebar
- [Global Error Modal Theme](./04-global-error-modal-theme.md) — Modal z-index layers above sidebar

---

*Sidebar Navigation & Page Layout Theme v1.0.0 — updated: 2026-03-30*
