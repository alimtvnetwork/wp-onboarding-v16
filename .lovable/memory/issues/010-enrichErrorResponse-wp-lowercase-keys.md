# enrichErrorResponse fails to classify WP-native 401 errors

## Rule

When reading error codes or messages from WP REST response data in `enrichErrorResponse`, always check BOTH PascalCase (`Code`, `Message`) and WordPress-native lowercase (`code`, `message`) keys.

## Why

WordPress `WP_Error` objects serialize to `{"code": "rest_forbidden", "message": "..."}` (lowercase). The plugin's `resolveErrorCode` only checked `ResponseKeyType::Code->value` (`"Code"` PascalCase), so it returned `null` for all WP-native auth errors. This caused `ErrorCategory` to be `"unknown"` and `Message` to be `"Unknown"` — hiding the real auth failure reason.

## How to apply

- `resolveErrorCode`: fallback to `$data['code']` when `$data['Code']` is absent
- `logRestApiError`: fallback to `$data['message']` when `$data['Message']` is absent
- Any future error enrichment code that reads WP response data must handle both key casings