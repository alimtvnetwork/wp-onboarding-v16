# Issue #42: Snapshot `/list` Endpoint Returns 404 — Route Never Registered

## Status: Resolved

## Symptoms

- Go backend request to `GET /wp-json/riseup-asia-api/v1/snapshots/list` returns HTTP 404
- User-Agent is `WP-Plugin-Publish/1.0` (Go backend, not browser)
- `enrichErrorResponse` intercepts WordPress's native `rest_no_route` 404
- Error category: `"unknown"`, message: `"Unknown"` — indicating the `rest_no_route` code
  was not recognized by `WpErrorCodeType`
- Stack trace originates from `InvalidRouteTrait.php:200` → `enrichErrorResponse` (the
  `rest_post_dispatch` filter), NOT from `handleInvalidRoute` — confirming WordPress
  itself returned the 404, not the plugin's catch-all route

## Root Cause Analysis

### The cascading failure in `registerRoutes()`

The `registerRoutes()` method in `RouteRegistrationTrait.php` called 11 sub-registrar
methods sequentially **without individual try-catch isolation**:

```php
// BEFORE (vulnerable)
$this->registerUtilityRoutes($safeRegister);
$this->registerPluginRoutes($safeRegister);
// ...
$this->registerSnapshotRoutes($safeRegister);   // ← if THIS throws...
$this->registerUserRoutes($safeRegister);        // ← these never execute
$this->registerCloudStorageRoutes($safeRegister);
$this->registerSiteSettingsRoutes($safeRegister);
$this->registerCatchAllRoute($safeRegister);     // ← catch-all never registered!
```

While each individual `register_rest_route()` call was wrapped in `$safeRegister`'s
try-catch, the **outer sub-registrar functions** were not. If any sub-registrar threw
an exception **before** its first `$safeRegister()` call (e.g., during
`buildPermissionCallback()`, enum resolution, or class autoloading), the exception
propagated up through `registerRoutes()` and was swallowed by WordPress's
`do_action('rest_api_init')` handler.

### Consequences of an unhandled sub-registrar throw

1. **All routes after the failing group are lost** — snapshot, user, cloud storage,
   site settings, and the catch-all route are never registered
2. **The final log line never prints** — `"Routes registered: X OK, Y failed"` is
   unreachable, so no evidence of the failure appears in the plugin's info log
3. **WordPress returns native `rest_no_route` 404** — since the catch-all `(?P<invalid_path>.+)`
   is also unregistered, WordPress's own route matching returns the 404
4. **`enrichErrorResponse` misclassifies** — `rest_no_route` was not in `WpErrorCodeType`,
   so the error category defaulted to `"unknown"` with message `"Unknown"`

### Why the error appeared intermittent

The throw could originate from:
- **Autoloader failures** — a missing or corrupted PHP file prevents class/trait loading
- **`buildPermissionCallback()`** — if `Admin::isEndpointEnabled()` relies on a
  component not yet initialized during `rest_api_init`
- **PHP resource limits** — memory exhaustion or execution timeout during
  route registration halts execution mid-function
- **Opcode cache stale entries** — after plugin update, PHP serves old bytecode
  that doesn't include newly added enum cases or trait methods

## Affected Files

| File | Role |
|------|------|
| `wp-plugins/riseup-asia-uploader/includes/Traits/Route/RouteRegistrationTrait.php` | **BUG**: Sub-registrars called without try-catch isolation |
| `wp-plugins/riseup-asia-uploader/includes/Traits/Route/InvalidRouteTrait.php` | Missing route diagnostics for `rest_no_route` errors |
| `wp-plugins/riseup-asia-uploader/includes/Enums/WpErrorCodeType.php` | Missing `RestNoRoute` enum case |
| `wp-plugins/qupload/includes/Traits/Route/RouteRegistrationTrait.php` | Same vulnerability (fewer routes, lower risk) |

## Fix

### 1. Route registration isolation (both plugins)

Each sub-registrar is now wrapped in its own try-catch via a `$groups` array pattern:

```php
// AFTER (resilient)
$groups = array(
    'utility'       => fn() => $this->registerUtilityRoutes($safeRegister),
    'snapshot'      => fn() => $this->registerSnapshotRoutes($safeRegister),
    'catch_all'     => fn() => $this->registerCatchAllRoute($safeRegister),
    // ...
);

foreach ($groups as $groupName => $registrar) {
    try {
        $registrar();
    } catch (Throwable $e) {
        $groupsFailed[] = $groupName;
        $this->fileLogger->logException($e, "Route group '$groupName' failed");
    }
}
```

If `registerSnapshotRoutes` throws, only snapshot routes are lost — all other groups
(including the catch-all) still register successfully. The log line now includes
failed group names: `"Routes registered: 35 OK, 0 failed, groups failed: snapshot"`.

### 2. `rest_no_route` error code recognition

Added `RestNoRoute = 'rest_no_route'` to `WpErrorCodeType` with:
- `isRoutingError()` classifier method
- `classifyErrorCode()` returns `"routing"` instead of `"unknown"`

### 3. Route diagnostics in 404 responses

When `enrichErrorResponse` detects a `rest_no_route` error, it now injects
`_routeDiagnostics` into the response:

```json
{
  "_routeDiagnostics": {
    "requestedRoute": "/riseup-asia-api/v1/snapshots/list",
    "namespace": "riseup-asia-api/v1",
    "registeredCount": 35,
    "registeredRoutes": ["/riseup-asia-api/v1/status", "..."],
    "hint": "If expected routes are missing, check the plugin error log for 'Route group ... failed' messages."
  }
}
```

This allows immediate identification of which route groups registered vs. which are
missing, without needing SSH access to the server.

## Prevention

- **All sequential initialization steps** (route groups, component init, hook registration)
  MUST be individually isolated with try-catch to prevent cascading failures
- **WordPress core error codes** (`rest_no_route`, `rest_forbidden`, etc.) must be
  added to `WpErrorCodeType` as they are encountered — the enum must cover all
  codes that the `enrichErrorResponse` filter may intercept
- **Route registration count logging** must always execute (placed after the
  try-catch loop, not inside a sub-registrar that might throw)
