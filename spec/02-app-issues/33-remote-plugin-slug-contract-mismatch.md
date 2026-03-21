# Remote Plugin Lifecycle Slug Contract Mismatch

> **Created:** 2026-03-21  
> **Status:** ✅ Resolved

---

## Root Cause

Remote plugin lifecycle calls from the Go backend were posting the slug under the JSON field `plugin`, while the Riseup Asia PHP lifecycle handlers read `plugin_slug`.

That mismatch caused the delegated WordPress endpoint to treat the request as missing the required slug and return:

- `400 Missing required plugin slug parameter`

---

## Code References

### Go request payload source

- `backend/internal/wordpress/UploaderLifecycle.go`
  - `pluginLifecycleAction(...)`
  - `CheckPluginExistsViaUploader(...)`

These calls were building the body from:

- `backend/internal/wordpress/RequestTypes.go`
  - `type PluginSlugRequest struct`

The payload previously serialized only:

```go
type PluginSlugRequest struct {
    Plugin string `json:"plugin"`
}
```

### PHP delegated endpoint expectation

- `wp-plugins/riseup-asia-uploader/includes/Traits/Plugin/PluginLifecycleHelpersTrait.php:47`
- `wp-plugins/riseup-asia-uploader/includes/Traits/Plugin/PluginLifecycleHelpersTrait.php:76`

Those handlers explicitly read:

```php
$request->get_param('plugin_slug')
```

and return:

- `ResponseMessageType::MissingPluginSlug`

when absent.

---

## Failure Path

1. Frontend calls `POST /sites/{id}/remote-plugins/enable`
2. Go handler forwards the lifecycle action to the remote WordPress plugin
3. Go payload contains `plugin`, but not `plugin_slug`
4. PHP handler reads only `plugin_slug`
5. PHP returns HTTP `400`
6. Go wraps that delegated error as `E3007`

---

## Fix

Made the Go request payload backward-compatible by sending **both** fields:

- `plugin`
- `plugin_slug`

### Updated code

- `backend/internal/wordpress/RequestTypes.go`
  - `PluginSlugRequest`
  - `NewPluginSlugRequest(...)`

- `backend/internal/wordpress/UploaderLifecycle.go`
  - lifecycle actions now use `NewPluginSlugRequest(...)`

---

## Validation Coverage

Added request-body assertions in:

- `backend/internal/wordpress/RemoteFiles_test.go`
  - `TestEnablePluginViaUploader_UsesUploaderNamespace`
  - `TestDisablePluginViaUploader_UsesUploaderNamespace`
  - `TestCheckPluginExistsViaUploader_SendsBothSlugFields`

These tests verify that both `plugin` and `plugin_slug` are present in the JSON body.

---

## Preventive Rule

When Go delegates to WordPress plugin endpoints, request structs must match the PHP handler field names exactly, or intentionally include compatibility aliases when multiple plugin generations are supported.