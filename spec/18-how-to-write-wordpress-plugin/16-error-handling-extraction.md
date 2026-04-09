# Phase 16 — Error Handling & Diagnostics Extraction

> **Purpose:** Define the complete error handling, error log viewing, error session management, and PHP error classification patterns extracted from the RiseUpAsia codebase. Supplements Phase 4 (Logging) with admin-facing error management UI and API patterns.
> **Audience:** AI code generators and human developers.
> **Prerequisite:** Phases 1–4 must be read first.

---

## 16.1 Error Type Classification

PHP errors are classified into three severity groups using a dedicated `ErrorType` class (NOT a backed enum — it holds arrays of constants):

```php
final class ErrorType
{
    public const FATAL_TYPES = [
        E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR,
    ];

    public const WARNING_TYPES = [
        E_WARNING, E_CORE_WARNING, E_USER_WARNING,
        E_NOTICE, E_USER_NOTICE, E_DEPRECATED, E_USER_DEPRECATED,
    ];

    public const RECOVERABLE_TYPES = [
        E_RECOVERABLE_ERROR, E_STRICT,
    ];

    public const TYPE_LABELS = [
        E_ERROR => 'E_ERROR',
        // ... one entry per constant
    ];
}
```

### Why a final class instead of an enum

- Each case would need to hold an **array** of PHP `E_*` constants — backed enums only support `string|int`.
- The class groups related constants; individual error codes are not discrete enum cases.
- `TYPE_LABELS` provides human-readable names for display in admin UI tables.

### Rules

| Rule | Detail |
|------|--------|
| Fatal detection | `in_array($errno, ErrorType::FATAL_TYPES, true)` |
| Label lookup | `ErrorType::TYPE_LABELS[$errno] ?? 'UNKNOWN'` |
| No instantiation | Class is `final` with only `public const` members |
| Namespace | `RiseupAsia\Enums` (lives alongside real enums for discoverability) |

---

## 16.2 Two-Tier Error Capture

### Tier 1 — Bootstrap Errors (before autoloader)

```php
// In InitHelpers (available from the main plugin file)
public static function errorLogWithPrefix(string $message): void {
    error_log(PluginConfigType::LogPrefix->value . ' ' . $message);
}

public static function errorLog(Throwable $e, string $context): void {
    error_log($context . ' ' . $e->getMessage() . "\n" . $e->getTraceAsString());
}
```

**When to use:** Only during bootstrap, activation hooks, or when `FileLogger` is not yet available.

### Tier 2 — FileLogger Errors (after initialization)

All post-bootstrap errors go through `FileLogger` which writes to structured log files with rotation and deduplication (see Phase 4).

---

## 16.3 Error Log Retrieval API

The plugin exposes two diagnostic endpoints for error management:

### Endpoints

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `error-logs` | GET | Query PHP error log entries with configurable inclusion |
| `error-sessions` | GET | Query grouped error sessions |

### Configurable Settings

Error log retrieval is controlled by `OptionNameType::LogRetrieval` settings:

```php
$defaults = [
    'include_error_log'  => true,   // Include error.log content
    'include_full_log'   => false,  // Include info.log content
    'include_stacktrace' => true,   // Include stack trace data
    'max_lines'          => 200,    // Maximum log lines to return
];
```

### Resolution Order

Settings are resolved in this priority:

1. **Request parameters** — Query params override stored settings per-request
2. **Stored settings** — `OptionNameType::LogRetrieval` from `wp_options`
3. **Defaults** — Hardcoded fallbacks above

```php
private function resolveSettings(WP_REST_Request $request): array {
    $logSettings = get_option(OptionNameType::LogRetrieval->value, []);

    $resolved = [
        'include_error_log'  => isset($logSettings['include_error_log'])
            ? (bool) $logSettings['include_error_log'] : true,
        // ... repeat for each key
    ];

    // Per-request overrides
    foreach (['include_error_log', 'include_full_log', 'include_stacktrace'] as $key) {
        if ($request->get_param($key) !== null) {
            $resolved[$key] = (bool) $request->get_param($key);
        }
    }

    return $resolved;
}
```

---

## 16.4 Error Session Model

Errors are grouped into **sessions** — a session represents a single request that produced one or more errors. This enables the admin UI to show errors in context rather than as isolated log lines.

### Session Data Structure

| Field | Type | Description |
|-------|------|-------------|
| `session_id` | string | Unique identifier (UUID or timestamp-based) |
| `created_at` | datetime | When the session started |
| `error_count` | int | Number of errors in this session |
| `is_seen` | boolean | Whether admin has dismissed/acknowledged |
| `errors` | array | Individual error entries within the session |

### Admin Actions

| Action | AJAX Handler | Description |
|--------|-------------|-------------|
| Dismiss flash | `dismissFlash` | Mark all unseen errors as seen (badge clears) |
| Clear all | `clearSessions` | Delete all error sessions |
| View details | Modal | Show full error context in modal overlay |

---

## 16.5 Error Admin Page JavaScript Pattern

The error management page follows the standard localized-object pattern:

```javascript
jQuery(document).ready(function($) {
    var C = window.RiseupErrors;
    var ajaxNonce = C.nonce;
    var activeTab = C.activeTab;
    var autoRefreshTimer = null;

    // Flash banner dismiss
    $('#riseup-dismiss-flash').on('click', function() {
        $.post(ajaxurl, {
            action: C.actions.dismissFlash,
            nonce: ajaxNonce
        }, function(response) {
            if (response.success) {
                $('#riseup-flash-banner').slideUp(300);
                $('.tab-badge, .error-count-badge').fadeOut(200);
            }
        });
    });
});
```

### Localized Object Shape (`RiseupErrors`)

```javascript
window.RiseupErrors = {
    nonce: '...',
    activeTab: 'errors',           // Current active tab
    actions: {
        dismissFlash: 'riseup_dismiss_error_flash',
        clearSessions: 'riseup_clear_error_sessions',
    },
    i18n: {
        dismissing: 'Dismissing...',
        markAsSeen: 'Mark as Seen',
        confirmClearAll: 'Are you sure you want to clear all error sessions?',
    }
};
```

---

## 16.6 Flash Banner Pattern

Unseen errors trigger a **flash banner** at the top of the error admin page:

### Requirements

| Requirement | Implementation |
|-------------|----------------|
| Visibility | Show only when `unseen_count > 0` |
| Badge | Display count in tab badge AND menu badge |
| Dismiss | Single click marks all as seen via AJAX |
| Animation | `slideUp(300)` on dismiss, `fadeOut(200)` on badges |
| Persistence | State stored in database, not session/cookie |

---

## 16.7 Auto-Refresh for Error Pages

Error pages support automatic polling for new errors:

```javascript
var autoRefreshTimer = null;

function startAutoRefresh(intervalMs) {
    stopAutoRefresh();
    autoRefreshTimer = setInterval(function() {
        loadErrors();  // Re-fetch and re-render table
    }, intervalMs);
}

function stopAutoRefresh() {
    if (autoRefreshTimer) {
        clearInterval(autoRefreshTimer);
        autoRefreshTimer = null;
    }
}
```

### Rules

- Default interval: **30 seconds**
- Auto-refresh MUST stop when modal is open
- Auto-refresh MUST stop when user is interacting (e.g., selecting text)
- Toggle state persists via a UI switch (see Phase 13 — Toggle Switch)

---

## 16.8 safeExecute Wrapper

All REST endpoint handlers MUST use the `safeExecute` wrapper to catch exceptions and return standardized error envelopes:

```php
public function handleErrorLogs(WP_REST_Request $request): WP_REST_Response {
    return $this->safeExecute(function() use ($request) {
        // ... handler logic
        return EnvelopeBuilder::success()
            ->autoDetectRequestedAt()
            ->setSingleResult($result)
            ->toResponse();
    }, 'error_logs');  // Context label for logging
}
```

### What safeExecute provides

| Feature | Detail |
|---------|--------|
| Exception catch | Wraps callback in try/catch, returns error envelope on failure |
| Context label | Second argument used in log messages for traceability |
| Debug gating | Stack traces included in response only when debug mode is ON |
| Consistent shape | All responses use `EnvelopeBuilder` regardless of success/failure |

---

## 16.9 Error Notification Settings

Stored under `OptionNameType::ErrorNotification`:

```php
case ErrorNotification = 'RiseupErrorNotificationSettings';
```

### Configurable Fields

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `email_enabled` | bool | false | Send email on fatal errors |
| `email_address` | string | admin_email | Recipient address |
| `threshold` | int | 5 | Minimum errors before notification |
| `cooldown_minutes` | int | 60 | Minimum time between notifications |

---

## 16.10 Checklist

- [ ] `ErrorType` class with `FATAL_TYPES`, `WARNING_TYPES`, `RECOVERABLE_TYPES`, `TYPE_LABELS`
- [ ] `InitHelpers::errorLogWithPrefix()` and `errorLog()` for Tier 1 logging
- [ ] `FileLogger` for Tier 2 structured logging (Phase 4)
- [ ] `error-logs` and `error-sessions` REST endpoints
- [ ] Error log retrieval settings with 3-level resolution (request → stored → defaults)
- [ ] Error session model with `is_seen` tracking
- [ ] Flash banner with AJAX dismiss
- [ ] Auto-refresh with stop-on-modal behavior
- [ ] `safeExecute` wrapper on all REST handlers
- [ ] Error notification settings in `OptionNameType`

---

*Last Updated: 2026-04-09*
