

## Plan: Fix WebSocket Notifications + Add Full Theme Settings

### Problem 1: WebSocket Toast Notifications on Every Page
Currently, the `useWebSocketStatus` hook (used by `WebSocketIndicator` in the Header) fires toast notifications for connect/disconnect events on **every page**. When you navigate to Settings, the WebSocket disconnects and shows a warning toast, then reconnects on Logs and shows a success toast. This is disruptive.

**Fix:** Make the connect/disconnect toast notifications context-aware. Only show them on pages where WebSocket is actively needed (Logs, Dashboard, Sites with deploy dialogs). On other pages like Settings, the indicator badge in the header will still show the status visually, but no toast will fire.

### Problem 2: Missing Theme Customization in Settings
The Appearance tab in Settings only shows a basic Light/Dark/System dropdown and Compact Mode toggle. However, a full `ThemeSelector` component already exists with accent colors, sidebar themes, font size, border radius, and animation toggles -- it's just not wired into the Settings page.

**Fix:** Replace the bare Appearance tab content with the existing `ThemeSelector` component.

---

### Technical Details

#### Task 1: Context-Aware WebSocket Toasts

**File: `src/hooks/useWebSocketStatus.ts`**
- Add a `suppressToasts` option parameter (default `true`)
- Remove the toast calls from the hook itself -- it should only track state
- The toasts will instead be driven by pages that care about WS status

**File: `src/components/shared/WebSocketIndicator.tsx`**
- Add a `showToasts` prop (default `false`)
- When `showToasts` is true, the indicator will fire connect/disconnect toasts

**File: `src/components/layout/Header.tsx`**
- Pass route-aware logic: check if current route is one that needs WebSocket (e.g., `/logs`, `/`, `/sites`)
- Only pass `showToasts={true}` to `WebSocketIndicator` when on those routes

Alternatively (simpler approach):
- Remove all toast calls from `useWebSocketStatus.ts` entirely
- Pages that need WS awareness (like Logs.tsx) already have their own `useWebSocket` hook and can handle their own connection state display
- The header indicator badge remains as a silent visual-only status

#### Task 2: Full Theme Settings in Appearance Tab

**File: `src/pages/Settings.tsx`**
- Replace the `case "appearance"` content (lines 498-536) with the `ThemeSelector` component
- Import `ThemeSelector` from `@/components/settings/ThemeSelector`
- Remove the local `theme`, `compactMode`, `handleThemeChange`, and `handleCompactModeChange` state/handlers that are now redundant (the `ThemeSelector` uses the `useTheme` hook internally)

This gives users access to:
- Theme (Light, Dark, System, High Contrast, High Contrast Dark)
- Accent Color (16 color options)
- Sidebar Theme (Night Blue, Midnight Purple, Emerald Dark, Solar White)
- Font Size (Extra Small to Extra Large)
- Border Radius (None to Full)
- Compact Mode toggle
- Animations toggle

