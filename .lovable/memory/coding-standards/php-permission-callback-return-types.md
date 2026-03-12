# Memory: coding-standards/php-permission-callback-return-types

**Rule:** Any PHP method used as a WordPress REST API permission callback (or called by one) that declares return type `true|WP_Error` MUST explicitly return `true` on the success path. It must NEVER pass through or return a `WP_User` object — PHP 8.x will throw a fatal `TypeError`.

**Pattern:**
```php
// CORRECT — narrow WP_User to true
private function checkAuthenticatedOnly(WP_REST_Request $request): true|WP_Error {
    $authResult = $this->resolveAndAuthenticate($request); // returns WP_User|WP_Error
    if (is_wp_error($authResult)) {
        return $authResult;
    }
    return true; // ← MUST be true, not $authResult
}
```

**Anti-pattern (causes HTTP 500 fatal):**
```php
// WRONG — leaks WP_User through a true|WP_Error return type
private function checkAuthenticatedOnly(WP_REST_Request $request): true|WP_Error {
    $authResult = $this->resolveAndAuthenticate($request);
    if (is_wp_error($authResult)) {
        return $authResult;
    }
    return $authResult; // ← FATAL: WP_User is not true|WP_Error
}
```

**Applies to:** `checkAuthenticatedOnly()`, `checkAuthenticatedCapability()`, `checkStatusPermission()`, `checkPostPermission()`, `checkPluginPermission()`, `checkLogsPermission()`, and any future permission callbacks.

**Reference:** `spec/02-app-issues/22-auth-return-type-fatal-error.md`
