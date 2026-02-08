

## Plan: Remote Plugins Panel Enhancements

This plan covers 5 improvements to the Remote Plugins Panel and related UI.

---

### 1. Fix Bulk Deactivate (Parallel Execution)

**Problem:** The bulk Activate/Deactivate/Delete handlers run sequentially in a `for` loop, and the Deactivate button appears to do nothing.

**Fix:** Refactor `handleBulkActivate`, `handleBulkDeactivate`, and `handleBulkDelete` to use `Promise.allSettled()` for parallel execution (similar to the existing `useBulkQuickPublish` pattern). Also add optimistic cache updates so the UI reflects changes immediately.

**File:** `src/components/sites/RemotePluginsPanel.tsx` (lines 310-373)
- Replace sequential `for` loops with `Promise.allSettled()` calls
- Add optimistic updates before firing requests (update plugin statuses in cache)
- Count successes/failures from settled results
- Show error toast only for failures

---

### 2. Make Dialog Full Screen

**Problem:** The dialog is constrained to `w-[95vw] max-w-4xl h-[95vh]`.

**Fix:** Change the dialog sizing to true full-screen: `w-screen h-screen max-w-none max-h-none rounded-none` so it occupies the entire viewport.

**File:** `src/components/sites/RemotePluginsPanel.tsx` (line 394)

---

### 3. Fix Icon Flickering on Hover

**Problem:** The Zap icon on the Sync button flickers when hovered. This is caused by the `transition-colors` class on parent elements combined with icon color changes on hover, and potentially the `animate-pulse` class toggling.

**Fix:**
- Add `shrink-0` to icon elements to prevent layout recalculation
- Ensure icons have explicit, stable color classes (not inherited transitions)
- Use `will-change-transform` on icon containers to prevent repaint flicker
- Remove any conditional class toggling that causes re-renders on hover

**File:** `src/components/sites/RemotePluginsPanel.tsx` (lines 425-438)

---

### 4. Improve Toast/Notification Styling (VS Code-like)

**Problem:** The green notification with a red X close button looks confusing. User wants VS Code-style notifications.

**Fix:** Update the Sonner toast configuration to use a more VS Code-like approach:
- Remove the destructive red close button styling -- use a subtle muted X icon instead (gray, not red)
- Keep semantic borders (green for success, red for error) but use subtler background tints
- Make the close button blend into the toast rather than standing out aggressively

**File:** `src/components/ui/sonner.tsx` (line 34) -- change closeButton classes from destructive red to muted/subtle gray

---

### 5. Add ZIP Upload Drop Zone to Remote Plugins Panel

**Problem:** No way to upload plugin ZIPs directly from the Remote Plugins Panel.

**Fix:** Add a drag-and-drop zone at the top of the plugin list area that:
- Accepts multiple `.zip` files via drag-and-drop or a file picker button
- Shows an "Activate after install" checkbox
- Uploads each ZIP in parallel to the backend
- Shows per-file upload progress

**Implementation:**
- Add a new API method `uploadRemotePlugin(siteId, file, activate)` in `src/lib/api.ts` that POSTs a FormData with the ZIP to `/sites/{siteId}/remote-plugins/upload`
- Add a collapsible upload section at the top of the panel with:
  - A dashed border drop zone area
  - File input for click-to-browse
  - "Activate after install" checkbox
  - Upload button that fires parallel uploads via `Promise.allSettled()`
  - Progress indicators per file

**Files:**
- `src/lib/api.ts` -- add `uploadRemotePlugin` method
- `src/components/sites/RemotePluginsPanel.tsx` -- add upload UI section

---

### 6. Default Theme: VS Code Dark with Green Accent

**Problem:** Default dark theme uses navy blue (`222.2 84% 4.9%`), user wants a VS Code-like darker color with light green as the accent/highlight color.

**Fix:** Update the default dark theme CSS variables to use a more neutral VS Code-like dark background (closer to `#1e1e1e` / `hsl(0 0% 12%)`), and change the default accent color from `blue` to `green` or `emerald`.

**Files:**
- `src/index.css` (lines 85-153) -- update `.dark` background to more neutral dark gray
- `src/hooks/useTheme.ts` (line 49) -- change `defaultThemeConfig.accentColor` from `"blue"` to `"green"`

---

### Technical Summary of Files Changed

| File | Changes |
|------|---------|
| `src/components/sites/RemotePluginsPanel.tsx` | Full-screen dialog, parallel bulk actions, icon flicker fix, upload drop zone |
| `src/components/ui/sonner.tsx` | VS Code-like close button (muted gray instead of red) |
| `src/lib/api.ts` | New `uploadRemotePlugin` method |
| `src/index.css` | Darker neutral background for `.dark` theme |
| `src/hooks/useTheme.ts` | Default accent color changed to green |

