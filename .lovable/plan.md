# Stability Fix Plan (v1.12.0) - ✅ COMPLETED

All fixes have been implemented and verified.

---

## Summary of Changes

### Phase 1: Fix Render-Phase State Updates ✅
- Moved `useMemo` and `useEffect` hooks before early returns in `Sites.tsx`
- Added global `unhandledrejection` handler in `App.tsx` with detailed error modal showing source function
- Error info now computed via `useMemo` instead of calling `captureError` during render

### Phase 2: Fix Seeding Logic ✅
- Added `normalizeUrl()` helper to `config.go` (strips `/wp-admin`, enforces HTTPS)
- Fixed seeding to populate `siteNameToId` map even for existing sites
- Fixed plugin seeding to create mappings for existing plugins (idempotent via INSERT OR IGNORE)
- Seeded sites now default to `ConnectionStatus = 'connected'`
- Updated naming convention: `Id` instead of `ID`, `Url` instead of `URL`

### Phase 3: Fix Checkbox Double-Toggle ✅
- Added `onClick={(e) => e.stopPropagation()}` to `Checkbox` in `EditSiteDialog.tsx`
- Added `onClick={(e) => e.stopPropagation()}` to `Checkbox` in `Plugins.tsx` site mapping dialog

### Phase 4: Clarify Publish Actions ✅
- Sync and Publish buttons now always visible (disabled with tooltip when no mappings)
- Tooltip explains "No sites linked – click Sites to add"

### Phase 5: WebSocket Stability ✅
- Added `isReconnectEnabled` flag to `ws.ts`
- `disconnect()` sets flag to false to prevent unwanted reconnection
- Added `isConnected()` helper method

---

## Files Modified

| File | Changes |
|------|---------|
| `src/pages/Sites.tsx` | Moved hooks before early returns, use `useMemo` for error info |
| `src/App.tsx` | Added `GlobalErrorHandler` component with `unhandledrejection` listener |
| `backend/internal/config/config.go` | Added `normalizeUrl()`, fixed seeding logic for idempotent mappings |
| `backend/internal/database/database.go` | Default seeded sites to `connected`, updated naming convention |
| `src/components/sites/EditSiteDialog.tsx` | Added `stopPropagation` to checkbox |
| `src/pages/Plugins.tsx` | Added `stopPropagation` to checkbox, Sync/Publish always visible |
| `src/lib/ws.ts` | Added `isReconnectEnabled` flag and `isConnected()` method |
| `public/version.json` | Bumped to v1.12.0 |
| `CHANGELOG.md` | Documented v1.12.0 fixes |

---

## Verification Steps

1. Delete `backend/data/app.db` and run `.\run.ps1 -r`
2. Open `http://localhost:8080/sites` - verify seeded site shows as **Connected**
3. Open `http://localhost:8080/plugins` - verify all 3 plugins show with site badges
4. Click **Add Site** - dialog should open without blank screen
5. Click **Edit** on seeded site - dialog should open, **Plugins** tab should show checkboxes that work reliably
6. Click checkbox on a plugin in Edit Site dialog - verify it toggles correctly (only once)
7. Click **Sites** on a plugin - verify checkbox toggles correctly
8. Verify **Publish** button is visible on each plugin (may be disabled if no mappings)
9. Open Logs page - verify Live badge when connected, Disconnected otherwise
