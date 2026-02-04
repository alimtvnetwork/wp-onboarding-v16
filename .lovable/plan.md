

# Stability Fix Plan (v1.12.0)

This plan addresses five critical issues in one cohesive release:

1. **Blank screen when clicking Add Site / Edit Site** on localhost:8080
2. **Plugins not connected to seeded site** (site-plugin mappings not created)
3. **Checkbox selection unreliable** (double-toggle bug)
4. **Deploy / Publish buttons missing or unclear**
5. **WebSocket + Live Logs stability**

---

## Root Cause Analysis

### 1. Add Site / Edit Site Blank Screen

The blank screen is caused by **state updates during the render phase** in `src/pages/Sites.tsx`:

```tsx
if (queryError) {
  const errorInfo = captureError({...}); // ← Triggers setState inside Zustand during render
  return (...);
}
```

`captureError` calls `set()` on the Zustand store, which causes a React re-render. This triggers the same code path again, leading to an infinite loop that crashes React and produces a white screen.

Additionally, there is no global `unhandledrejection` handler to catch async errors in event handlers, so any unexpected Promise rejection can crash the app to a blank page.

### 2. Plugin-Site Mappings Not Created During Seeding

The seeding logic in `backend/internal/config/config.go` has two issues:

- When a site already exists (by URL), the code continues to the next site but **does not populate `siteNameToID`** for that existing site, breaking the mapping lookup later.
- Similarly, when a plugin already exists (by path), it skips mapping creation entirely instead of continuing to create mappings for that existing plugin.

### 3. Checkbox Double-Toggle

In both `EditSiteDialog.tsx` and `Plugins.tsx`, the `onClick` handler on the parent `<div>` and the `onCheckedChange` handler on the `<Checkbox>` both call the same toggle function. When you click the checkbox, both handlers fire, effectively canceling the toggle (toggle → toggle back).

### 4. Deploy Button Missing / Not Visible

The "Publish" button is already present but only shows when `plugin.mappings.length > 0`. Since mappings are not being created during seeding, the button never appears. Additionally, the user expected the word "Deploy" but the current label is "Publish".

### 5. WebSocket Disconnection in Hosted Preview

The Lovable hosted preview cannot reach `localhost:8080`, causing WebSocket errors. The Logs page depends on this connection. The code handles this reasonably, but there is no clear user guidance. When running locally at `http://localhost:8080`, this should work.

---

## Implementation Plan

### Phase 1: Fix Render-Phase State Updates (Task 42e35158)

**File: `src/pages/Sites.tsx`**

Remove `captureError` from the render path. Instead, capture the error info outside the render using `useMemo` or compute it lazily:

```tsx
// BEFORE (problematic)
if (queryError) {
  const errorInfo = captureError({...}); // State update during render
  return (...);
}

// AFTER (safe)
const queryErrorInfo = useMemo(() => {
  if (!queryError) return null;
  return {
    code: "E9001",
    message: "Site service not available",
    details: queryError.message,
    timestamp: new Date().toISOString(),
  };
}, [queryError]);

// In render:
if (queryError && queryErrorInfo) {
  // Use queryErrorInfo, call captureError only in onClick handlers
}
```

**File: `src/App.tsx`**

Add a global `unhandledrejection` handler as a safety net:

dont' use geenric error always use specific func and spec reasoning steps why failing along iwht modal

write actually why failing not like unhandled if possible add the func who is requesting this

```tsx
useEffect(() => {
  const handleRejection = (event: PromiseRejectionEvent) => {
    console.error("Unhandled rejection:", event.reason); // write actually why failing not like unhandled if possible add the func who is requesting this
    toast.error("An error occurred"); // dont' use geenric error always use specific func and spec reasoning steps why failing along iwht modal
    event.preventDefault();
  };
  window.addEventListener("unhandledrejection", handleRejection);
  return () => window.removeEventListener("unhandledrejection", handleRejection);
}, []);
```

dont' use geenric error always use specific func and spec reasoning steps why failing along iwht modal

write actually why failing not like unhandled if possible add the func who is requesting this

---

### Phase 2: Fix Seeding Logic (Task d445bad2)

**File: `backend/internal/config/config.go`**

Fix the site seeding loop to populate `siteNameToID` even when the site already exists:


USe Url instead of URL or Id instead of ID use this naming convention everywhere and update the spec and memroy accordingly


```go
// BEFORE
existingID, err := db.GetSiteIDByURL(site.URL)
if err == nil && existingID > 0 {
  siteNameToID[site.Name] = existingID
  continue
}
// create site...

// AFTER (normalize URL + always populate map)
normalizedURL := normalizeURL(site.URL) // Need to add helper
existingID, err := db.GetSiteIDByURL(normalizedURL)
if err == nil && existingID > 0 {
  siteNameToID[site.Name] = existingID
  continue
}
// create site with normalizedURL...
siteNameToID[site.Name] = id
```

Fix the plugin seeding loop to create mappings even for existing plugins:

```go
// BEFORE
existingID, err := db.GetPluginIDByPath(plugin.Path)
if err == nil && existingID > 0 {
  continue // ← Skips mappings!
}

// AFTER
existingID, err := db.GetPluginIDByPath(plugin.Path)
pluginID := existingID
if err != nil || existingID <= 0 {
  // Create new plugin
  pluginID, err = db.CreateSeedPlugin(...)
}
// Always create mappings
for _, siteName := range plugin.SiteNames {
  if siteID, ok := siteNameToID[siteName]; ok {
    remoteSlug := strings.ToLower(strings.ReplaceAll(plugin.Name, " ", "-"))
    _ = db.CreateSeedMapping(pluginID, siteID, remoteSlug)
  }
}
```

Add a `normalizeURL` helper to `config.go` (copy minimal logic from site service).

**File: `backend/data/wp-public.go`** should eb the root db

Update `CreateSeedSite` to set `ConnectionStatus = 'connected'` by default:

```go
result, err := db.Exec(`
  INSERT INTO Sites (..., ConnectionStatus, ...)
  VALUES (..., 'connected', ...)
`, ...)
```

---

### Phase 3: Fix Checkbox Double-Toggle (Task 0357dacc)

**File: `src/components/sites/EditSiteDialog.tsx`**

Remove the `onClick` handler from the parent `<div>` OR add `e.stopPropagation()` to the checkbox:

```tsx
// BEFORE
<div onClick={() => togglePluginSelection(plugin.id)}>
  <Checkbox onCheckedChange={() => togglePluginSelection(plugin.id)} />
</div>

// AFTER (Option A: remove div onClick)
<div className="...">
  <Checkbox
    checked={selectedPlugins.includes(plugin.id)}
    onCheckedChange={() => togglePluginSelection(plugin.id)}
  />
  <div onClick={() => togglePluginSelection(plugin.id)} className="flex-1 cursor-pointer">
    {/* Label content */}
  </div>
</div>

// AFTER (Option B: stopPropagation)
<div onClick={() => togglePluginSelection(plugin.id)}>
  <Checkbox
    onClick={(e) => e.stopPropagation()}
    onCheckedChange={() => togglePluginSelection(plugin.id)}
  />
</div>
```

Apply the same fix to:
- `src/pages/Plugins.tsx` (site mapping dialog, lines 1004-1018)

---

### Phase 4: Clarify Deploy / Publish Actions (Task 8def8a87)

Per user preference, keep "Publish" label but add clarity:

**File: `src/pages/Plugins.tsx`**

1. Show the Publish button always (even when no mappings), but disable it with a tooltip:

```tsx
<Button
  variant="ghost"
  size="sm"
  onClick={() => openPublishDialog(plugin)}
  disabled={!plugin.mappings?.length || isPublishing === plugin.id}
  title={plugin.mappings?.length ? "Publish to WordPress sites" : "No sites linked – click Sites to add"}
>
  <Upload className="h-4 w-4" />
  <span className="ml-1 hidden sm:inline">Publish</span>
</Button>
```

2. Ensure the "Deploy All" bulk action is clearly visible in `BulkActionsBar.tsx` (it already exists).

---

### Phase 5: WebSocket Stability (Task 7f6e0075)

**File: `src/lib/ws.ts`**

The current implementation is reasonable. Add a flag to suppress reconnect attempts when intentionally disconnected:

```ts
private shouldReconnect = true;

disconnect() {
  this.shouldReconnect = false;
  // ...
}

private scheduleReconnect() {
  if (!this.shouldReconnect) return;
  // ...
}
```

**File: `src/pages/Logs.tsx`**

Already handles disconnected state well. No changes needed.

---

## Files to Modify

| File | Changes |
|------|---------|
| `src/pages/Sites.tsx` | Remove `captureError` from render path |
| `src/App.tsx` | Add global `unhandledrejection` handler |
| `backend/data/seed-config/sites.plugins.config`  for seedable config and related golang files
|'backend/data/wp-plugin.db' | Default seeded sites to `ConnectionStatus = 'connected'` |
| `src/components/sites/EditSiteDialog.tsx` | Fix checkbox double-toggle |
| `src/pages/Plugins.tsx` | Fix checkbox double-toggle, always show Publish button |
| `src/lib/ws.ts` | Add `isReconnectRequired` flag | use has or is for boolean funcs or variables always update memory
| `public/version.json` | Bump to v1.12.0 |
| `changelog.md` | Document fixes | keep all md file name in lowercase

---

## Verification Steps

1. Rename `backend/data/app.db` to 'backend/data/wp-plugin.db' and run `.\run.ps1 -r`
2. Open `http://localhost:8080/sites` - verify seeded site shows as **Connected**
3. Open `http://localhost:8080/plugins` - verify all 3 plugins show with site badges
4. Click **Add Site** - dialog should open without blank screen
5. Click **Edit** on seeded site - dialog should open, **Plugins** tab should show checkboxes that work reliably
6. Click checkbox on a plugin in Edit Site dialog - verify it toggles correctly (only once)
7. Click **Sites** on a plugin - verify checkbox toggles correctly
8. Verify **Publish** button is visible on each plugin (may be disabled if no mappings)
9. Open Logs page - verify Live badge when connected, Disconnected otherwise

