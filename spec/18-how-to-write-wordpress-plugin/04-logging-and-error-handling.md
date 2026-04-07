# Phase 4 — Logging and Error Handling

> **Purpose:** Define the complete logging architecture and error handling strategy in full detail so any AI can implement it correctly from scratch.

---

## 4.1 Two-Tier Logging Architecture

The plugin uses two independent logging tiers:

| Tier | Class | When available | Purpose |
|------|-------|---------------|---------|
| Tier 1 — PHP native | `error_log()` / `ErrorLogHelper` | Always | Fallback when FileLogger is not ready; also emits to WP_DEBUG log |
| Tier 2 — FileLogger | `FileLogger` (singleton) | After autoloader loads | Primary structured log with rotation, dedup, and stack trace separation |

### Why two tiers

The autoloader and bootstrap run before any plugin classes are available. If they fail, Tier 1 (native `error_log()`) captures the failure. Once the plugin initialises, Tier 2 (FileLogger) handles all logging with structured output and file management.

---

## 4.2 FileLogger — Complete Specification

### Singleton access

```
$logger = FileLogger::getInstance();
```

### Log files

The logger writes to three separate files, all under `wp-content/uploads/{plugin-slug}/logs/`:

| File | Contains | Written by |
|------|----------|-----------|
| `info.log` | All log entries (debug, info, warn, error) | Every log call |
| `error.log` | Only warn and error entries | `warn()` and `error()` calls |
| `stacktrace.log` | Full stack traces for errors | `logException()` and error-level calls |

### Public API

| Method | Level | Writes to error.log | Writes stacktrace | Dedup enabled |
|--------|-------|--------------------|--------------------|---------------|
| `debug($message, $context)` | Debug | No | No | Yes (persistent) |
| `info($message, $context)` | Info | No | No | Yes (persistent) |
| `warn($message, $context)` | Warn | Yes | Yes | No |
| `error($message, $context)` | Error | Yes | Yes | No |
| `logException($e, $context)` | Error | Yes | Yes (from exception) | No |
| `logCriticalException($e, $context)` | Error | Yes | Yes | No — re-throws |

### logCriticalException — the "log and crash" method

This method logs the exception and then **re-throws it** (return type `never`). Use it in infrastructure code (boot, route registration, autoloader) where silent failure causes cascading breakage. Call sites do not need a separate `throw $e` — the method handles it internally.

---

## 4.3 Log Entry Format

Every log line follows this exact format:

```
[{timestamp} v{version}] [{Level}] {message} ({file}:{line}) {json_context}
```

| Component | Source | Example |
|-----------|--------|---------|
| Timestamp | `DateHelper::nowLogDisplay()` | `07-Apr-26 2:30 PM` |
| Version | `PluginConfigType::Version->value` | `2.31.0` |
| Level | `LogLevelType` case value | `Info`, `Error` |
| Message | Passed by caller | `Plugin initialized` |
| File:Line | Extracted from `debug_backtrace()` | `Plugin.php:107` |
| Context | JSON-encoded associative array | `{"version":"2.31.0","timeMs":1.23}` |

### Caller resolution

The logger uses `debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)` to capture the actual caller's file and line, not the logger's own location. The skip depth is:

```
[0] = logAtLevel() (internal)
[1] = actual caller ← this is captured
[2] = caller's caller
```

---

## 4.4 Stack Trace File Format

Stack traces are written to a dedicated file with visual separators:

```
================================================================================
[07-Apr-26 2:30 PM v2.31.0] Error message here (SomeFile.php:42)
--------------------------------------------------------------------------------
#0 Plugin.php(107): PluginName\Core\Plugin->registerRoutes()
#1 WP_Hook.php(324): WP_Hook->apply_filters()
================================================================================

```

Each trace entry is a self-contained block with `=` separators for easy visual scanning. The file is separate from `info.log` and `error.log` to avoid cluttering operational logs.

---

## 4.5 Log Rotation

The FileLogger automatically rotates log files when they exceed a size threshold.

| Setting | Default | Range |
|---------|---------|-------|
| Max file size | 512 KB | 64 KB – 10 MB |
| Max rotations | 10 | 1 – 100 |

### Rotation process

1. Before each write, check if the target file exceeds `maxLogSizeBytes`
2. If yes, move the file to `logs/archive/{NNN}/{filename}` where NNN is a zero-padded sequential index
3. If archive folder count reaches `maxRotations`, delete the oldest folders first
4. The current file is now empty and ready for new writes

### Archive structure

```
logs/
├── info.log           ← current
├── error.log          ← current
├── stacktrace.log     ← current
└── archive/
    ├── 001/
    │   ├── info.log
    │   └── error.log
    ├── 002/
    │   └── info.log
    └── 003/
        └── stacktrace.log
```

---

## 4.6 Deduplication

The logger has two dedup layers to prevent repetitive log entries:

### In-memory dedup (per-request)

- Hashes `level + message + file + line`
- If the same hash appears again in the same PHP request, the entry is silently skipped
- Prevents loops from flooding logs

### Persistent dedup (cross-request)

- Stores hashes in a JSON file (`dedup-registry.json`) in the logs directory
- Used only for `debug()` and `info()` level entries
- Maximum 500 entries; oldest entries are pruned when limit is reached
- Prevents boot/init messages from repeating on every request

---

## 4.7 Error Handling — Mandatory Rules

### Rule 1: Always catch Throwable

Every try-catch block catches `Throwable`, never `Exception`. This captures both standard exceptions and PHP fatal errors (TypeError, Error, etc.).

```
try {
    // code
} catch (Throwable $e) {
    // handle
}
```

### Rule 2: Every catch block must log with stack trace

Every `error_log()` call inside a catch block that has access to `$e` **must** append the trace:

```
error_log($context . ' ' . $e->getMessage() . "\n" . $e->getTraceAsString());
```

Logging only `$e->getMessage()` without the trace is a **critical defect**.

### Rule 3: Use ErrorLogHelper for native logging

When FileLogger is not available (autoloader, bootstrap), use the `ErrorLogHelper` static class:

| Method | Behaviour |
|--------|-----------|
| `ErrorLogHelper::log($e, 'Context:')` | Logs message + trace to `error_log()` |
| `ErrorLogHelper::logAndThrow($e, 'Context:')` | Logs and re-throws (return type `never`) |

### Rule 4: safeExecute wraps all endpoints

Every public REST handler method must be wrapped in `$this->safeExecute()`. Direct try-catch in endpoint handlers is not allowed — delegate to the ResponseTrait infrastructure.

### Rule 5: Stack trace frames in API responses

Error responses must include structured stack trace frames (not just a string) so that external systems can parse them:

```json
{
  "Errors": {
    "BackendMessage": "Error description",
    "Backend": [
      "#0 SomeFile.php(42): ClassName->method()",
      "#1 AnotherFile.php(100): AnotherClass->caller()"
    ]
  }
}
```

---

## 4.8 ErrorLogHelper — Specification

A minimal static class with two methods:

| Method | Signature | Behaviour |
|--------|-----------|-----------|
| `log` | `(Throwable $e, string $context): void` | Calls `error_log()` with context + message + trace |
| `logAndThrow` | `(Throwable $e, string $context): never` | Calls `log()` then `throw $e` |

Use `logAndThrow` in infrastructure code (autoloader, route registration) where the plugin cannot recover. Use `log` in graceful-degradation paths.

---

## 4.9 Shutdown Handler (Fatal Errors)

Register a global shutdown handler to catch fatal errors that bypass try-catch:

1. Check `error_get_last()` for fatal error types
2. Log to a dedicated `fatal-errors.log` file (not through FileLogger, which may be compromised)
3. Include memory usage statistics (helps diagnose OOM kills)
4. Attempt JSON output if the response has not been sent

---

## 4.10 DateHelper — Timestamp Specification

All timestamps flow through a centralised `DateHelper` class:

| Method | Returns | Used for |
|--------|---------|----------|
| `nowUtc()` | `2026-04-07T14:30:00Z` | API responses, database storage |
| `nowIso()` | ISO 8601 with timezone | API metadata |
| `nowLogDisplay()` | `07-Apr-26 2:30 PM` | Log file entries |
| `formatInWpTimezone($format, $timestamp)` | Formatted string in WP timezone | All display timestamps |

### Timezone handling

- All storage is UTC
- All display converts to the WordPress-configured timezone (`Settings > General > Timezone`)
- The timezone is resolved once and cached for the request lifetime
- Supports both named timezones (`Asia/Kuala_Lumpur`) and GMT offset fallback
