# Phase 5 — Helpers, Response Envelope, and Integration

> **Purpose:** Define helper class patterns, the standard API response format, and how all pieces integrate.

---

## 5.1 Helper Classes

Helpers are **stateless static utility classes**. They never hold instance state, never depend on `$this`, and never access WordPress hooks.

### Standard helpers

| Helper | Responsibility |
|--------|---------------|
| `DateHelper` | All timestamp formatting and timezone conversion (see Phase 4, §4.10) |
| `PathHelper` | File path resolution, directory creation, uploads dir resolution |
| `ErrorLogHelper` | Native `error_log()` wrapper with stack traces (see Phase 4, §4.8) |
| `EnvelopeBuilder` | Constructs the standard API response envelope |

### PathHelper specification

PathHelper centralises all file system path resolution:

| Method | Returns |
|--------|---------|
| `getBaseDir()` | `wp-content/uploads/{plugin-slug}` |
| `getLogsDir()` | `{baseDir}/logs` |
| `getTempDir()` | `{baseDir}/temp` |
| `ensureDirectory($dir)` | Recursively creates directory if missing; returns `bool` |
| `ensureFileParentDirectory($filePath)` | Ensures parent dir exists for a file path |
| `isFileMissing($path)` | Negation wrapper around `file_exists()` |

### Key design principle

Path resolution uses `wp_upload_dir()` for the base and caches the result. If `wp_upload_dir()` returns an invalid result, it falls back to `WP_CONTENT_DIR . '/uploads'`.

---

## 5.2 Response Envelope — The Standard API Format

Every REST endpoint returns responses in this exact envelope structure:

```json
{
  "Status": {
    "IsSuccess": true,
    "IsFailed": false,
    "Code": 200,
    "Message": "OK",
    "Timestamp": "2026-04-07T14:30:00Z"
  },
  "Attributes": {
    "RequestedAt": "/api-namespace/v1/endpoint",
    "TotalRecords": 1
  },
  "Results": [
    { "key": "value" }
  ]
}
```

### Error envelope (additional field)

```json
{
  "Status": { "IsSuccess": false, "IsFailed": true, "Code": 500, "Message": "Error description", "..." },
  "Attributes": { "..." },
  "Results": [],
  "Errors": {
    "BackendMessage": "Detailed error message",
    "Backend": [
      "#0 File.php(42): Class->method()",
      "#1 File.php(100): Class->caller()"
    ]
  }
}
```

### Envelope rules

| Rule | Detail |
|------|--------|
| `IsSuccess` and `IsFailed` are always both present | They are logical inverses |
| `Timestamp` is always UTC ISO 8601 | From `DateHelper::nowUtc()` |
| `Results` is always an array | Even for single results, wrap in array |
| `Errors` key only present on failure | Never include empty Errors on success |
| All keys are PascalCase | Defined in `ResponseKeyType` enum |
| Stack trace included on errors | `Backend` array contains trace lines |

---

## 5.3 EnvelopeBuilder — Fluent API

The EnvelopeBuilder uses the builder pattern with static factory methods:

### Success flow

```
EnvelopeBuilder::success('OK', 200)
    ->setRequestedAt('/namespace/v1/endpoint')
    ->setSingleResult(['key' => 'value'])
    ->toResponse();
```

### Error flow

```
EnvelopeBuilder::error('Something failed', 500, $exception)
    ->setRequestedAt('/namespace/v1/endpoint')
    ->toResponse();
```

### Methods

| Method | Purpose |
|--------|---------|
| `success($message, $code)` | Static factory for success envelope |
| `error($message, $code, $exception)` | Static factory for error envelope — extracts trace from exception |
| `setSingleResult($item)` | Wraps a single associative array in `Results: [$item]` |
| `setListResult($items)` | Sets `Results` to the provided array directly |
| `setRequestedAt($path)` | Sets the `RequestedAt` attribute |
| `toResponse()` | Builds and returns a `WP_REST_Response` |

---

## 5.4 Integration Checklist — Adding a New Feature

When adding a new feature endpoint to the plugin, follow this exact sequence:

### Step 1: Define enums

| What to add | Where |
|-------------|-------|
| Endpoint path | New case in `EndpointType` |
| New response keys | New cases in `ResponseKeyType` |
| New capabilities (if any) | New case in `CapabilityType` |

### Step 2: Create the handler trait

1. Create a new file: `Traits/{FeatureDomain}/{FeatureName}Trait.php`
2. Follow the trait anatomy from Phase 3, §3.3
3. The public handler method wraps logic in `$this->safeExecute()`
4. Use `EnvelopeBuilder` for all responses
5. Use `$this->fileLogger` for all logging
6. Use enum values for all string literals

### Step 3: Register the route

1. Add a new registration method in `RouteRegistrationTrait` (or add to an existing group)
2. Wire it using the `$safeRegister` closure pattern
3. Use `EndpointType::NewEndpoint->route()` for the path
4. Use `HttpMethodType` for the method
5. Point to the correct permission callback

### Step 4: Compose in Plugin.php

1. Add `use PluginName\Traits\{FeatureDomain}\{FeatureName}Trait;` import
2. Add `use {FeatureName}Trait;` inside the class body
3. If a new route group was created, add it to the `$groups` array in `registerRoutes()`

### Step 5: Bump version

Update `PluginConfigType::Version` case value.

---

## 5.5 Database — Split DB Concept

Plugins that need data persistence use SQLite stored in `wp-content/uploads/{plugin-slug}/` rather than WordPress's MySQL database. This provides:

| Benefit | Detail |
|---------|--------|
| Isolation | Plugin data is completely separate from WordPress tables |
| Portability | Database file can be backed up, moved, or deleted independently |
| No migration conflicts | No interference with WordPress core or other plugin migrations |
| Schema versioning | Track migration versions inside the SQLite database itself |

### Database location

```
wp-content/uploads/{plugin-slug}/{plugin-slug}.db
```

### Schema versioning

Store a `schema_version` value in the database (either a dedicated table or SQLite `user_version` pragma). On plugin activation or init, compare the stored version to the expected version and run any pending migrations.

---

## 5.6 Notification Patterns

When the plugin needs to send notifications (email, admin notices), follow these patterns:

| Pattern | Implementation |
|---------|---------------|
| Email sending | Delegate to `wp_mail()` with structured HTML templates |
| Log email endpoint | A dedicated REST endpoint that emails log contents to a configured recipient |
| Admin notices | Only show on plugin's own admin pages, never globally |
| Error notifications | Log the error; optionally email if severity is critical |

---

## 5.7 Security Checklist

| Requirement | Implementation |
|-------------|---------------|
| Authentication | WordPress Application Passwords via Basic Auth |
| Capability checks | Every endpoint has a `permission_callback` |
| Input sanitisation | Use `sanitize_text_field()`, `absint()`, `wp_kses()` |
| Output escaping | Use `esc_html()`, `esc_attr()`, `esc_url()` in admin pages |
| Nonce verification | All admin AJAX actions verify nonces via enum-defined values |
| Rate limiting | Track request counts per IP in transients or custom table |
| ABSPATH guard | Every PHP file checks `defined('ABSPATH')` |

---

## 5.8 Summary — The Complete Pattern

```
Request → WordPress REST API
  → RouteRegistrationTrait (resolves endpoint)
  → AuthTrait (validates credentials + capabilities)
  → Handler Trait (public method)
    → safeExecute() (error boundary from ResponseTrait)
      → Private method (business logic)
        → FileLogger (structured logging)
        → EnvelopeBuilder (response construction)
        → Enums (all string/int constants)
        → Helpers (stateless utilities)
      ← WP_REST_Response (envelope format)
    ← Throwable caught → errorResponse() → WP_REST_Response
  ← JSON response to client
```

Every layer has a single responsibility. Every string is an enum value. Every error is caught, logged with a stack trace, and returned in a structured format. The pattern is identical for every endpoint.
