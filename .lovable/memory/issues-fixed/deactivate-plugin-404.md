# Issue Fixed: DeactivatePlugin 404 Error
Updated: 2026-02-06

## Problem
Deactivating a plugin via the Remote Plugins panel returned a 404 error:
```
PUT /wp/v2/plugins/broken-link-checker%2Fbroken-link-checker: status 404
```

## Root Cause
The `DeactivatePlugin` function in `backend/internal/wordpress/client.go` did not call `ResolvePluginIdentifier()` like `ActivatePlugin` and `DeletePlugin` do. 

The WordPress Core API requires the plugin identifier in the format `folder/main-file.php` (e.g., `broken-link-checker/broken-link-checker.php`), but the frontend was sending the slug format `broken-link-checker/broken-link-checker`.

## Fix Applied
Added `ResolvePluginIdentifier()` call to `DeactivatePlugin()`:

```go
func (c *Client) DeactivatePlugin(slug string) error {
    // Resolve plugin identifier (consistent with ActivatePlugin and DeletePlugin)
    resolvedID, resolveErr := c.ResolvePluginIdentifier(slug)
    if resolveErr != nil {
        resolvedID = slug
    }
    
    endpoint := "/wp/v2/plugins/" + escapePathSegmentPreservingPercent(resolvedID)
    // ...
}
```

## Files Changed
- `backend/internal/wordpress/client.go` - Lines 469-504

## Verification
The fix ensures consistent behavior across:
- `ActivatePlugin()` ✅ (already had ResolvePluginIdentifier)
- `DeactivatePlugin()` ✅ (now fixed)
- `DeletePlugin()` ✅ (already had ResolvePluginIdentifier)
