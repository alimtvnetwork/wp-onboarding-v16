# enrichErrorResponse fails to classify WP-native 401/403 errors

**Severity:** High — auth errors are logged as "unknown" category with "Unknown" message  
**Fixed in:** v2.39.0  
**File:** `wp-plugins/riseup-asia-uploader/includes/Traits/Route/InvalidRouteTrait.php`

## Symptom

All WP-native 401 responses (missing auth, invalid credentials) logged with:
- `ErrorCategory: "unknown"` instead of `"authentication"`
- `Message: "Unknown"` instead of the actual reason

## Root cause

WordPress `WP_Error` objects serialize to lowercase keys: `{"code": "rest_forbidden", "message": "Unauthorized", ...}`.

But `resolveErrorCode()` only checked `$data['Code']` (PascalCase from `ResponseKeyType::Code->value`), missing the WP-native lowercase `code` key entirely. So `WpErrorCodeType::tryFrom()` was never called, `errorCode` was always `null`, and `classifyErrorCode()` was never reached.

Similarly, `logRestApiError()` only checked `$data['Message']` and `$data['Status']['Message']`, missing WP's lowercase `$data['message']`.

## Fix

```php
// resolveErrorCode — check both casings
$code = $data[ResponseKeyType::Code->value] ?? $data['code'] ?? null;

// logRestApiError — check both casings
ResponseKeyType::Message->value => $data[ResponseKeyType::Message->value] ?? $data['message'] ?? $data['Status']['Message'] ?? 'Unknown',
```

## Impact

After fix:
- Auth failures correctly classified as `ErrorCategory: "authentication"`
- Actual WP error message (e.g., "Unauthorized") propagated instead of "Unknown"
- Log-level correctly set to `warn` for auth errors instead of `error`