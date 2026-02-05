 # Issue Fixed: Plugin Activation 404 - Endpoint Mismatch
 
 ## Problem
 
 The Go backend called `/plugins/{slug}/enable` which doesn't exist in the Riseup Asia Uploader PHP plugin, causing 404 errors during the activation stage.
 
 ## Root Cause
 
 The PHP plugin handles activation during the `/upload` endpoint when `activate: true` is passed. There is no separate `/enable` endpoint. The Go constants defined endpoints that didn't exist.
 
 ## Solution
 
 1. Track `activated: true` from upload response
 2. Skip activation stage if already activated during upload
 3. Fallback to WordPress Core API if activation needed
 
 ## Key Learning
 
 **Always verify endpoint existence in PHP before using in Go**. Check `register_routes()` in the WordPress plugin before adding endpoint constants.
 
 ## Files Changed
 
 - `backend/internal/services/publish/service.go`
 - `backend/internal/wordpress/uploader.go`
 
 ## Date Fixed
 
 2026-02-05