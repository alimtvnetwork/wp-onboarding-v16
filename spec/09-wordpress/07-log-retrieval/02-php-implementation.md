# Log Retrieval — PHP Implementation

> **Applies to:** QUpload, RiseUp Asia Uploader

---

## 1. Enum Change — `EndpointType`

### QUpload (`wp-plugins/qupload/includes/Enums/EndpointType.php`)

Add a new case:

```php
case LogsRetrieve = 'logs/retrieve';
```

Place it after `LogsEmail` in the existing endpoint list.

### RiseUp Asia (`wp-plugins/riseup-asia-uploader/includes/Enums/EndpointType.php`)

Add a new case in the "Remote Log Management" section:

```php
case LogsRetrieve = 'logs/retrieve';
```

Place it after `LogsEmail`.

---

## 2. New Trait — `LogRetrievalTrait`

### QUpload Location

`wp-plugins/qupload/includes/Traits/Log/LogRetrievalTrait.php`

### RiseUp Asia Location

`wp-plugins/riseup-asia-uploader/includes/Traits/Log/LogRetrievalTrait.php`

### Trait Structure

Both plugins implement an identical trait with the same method signatures. Differences are limited to:

1. **Namespace** — `QUpload\Traits\Log` vs `RiseupAsia\Traits\Log`
2. **Enum imports** — respective `PluginConfigType`, `ResponseKeyType`, `HttpStatusType`
3. **Log directory resolution** — QUpload uses `PathHelper::getLogsDir()`, RiseUp Asia uses `$this->fileLogger->getLogsDir()`

### Method Signatures

```php
trait LogRetrievalTrait
{
    /** Handle GET /logs/retrieve — return log file contents. */
    public function handleLogsRetrieve(WP_REST_Request $request): WP_REST_Response;

    /** Resolve retrieval settings from query params with defaults. */
    private function resolveRetrievalSettings(WP_REST_Request $request): array;

    /** Read the last N lines of a log file and return metadata + content. */
    private function readLogFileTail(string $filePath, int $maxLines): array;
}
```

### `handleLogsRetrieve` Logic

1. Log the endpoint call
2. Call `resolveRetrievalSettings($request)` to get parsed parameters
3. Resolve the logs directory path
4. Conditionally build `InfoLog`, `ErrorLog`, `StacktraceLog` objects using `readLogFileTail()`
5. Return success response with version, settings, and log objects

### `resolveRetrievalSettings` Logic

1. Define defaults: `include_info_log=true`, `include_error_log=true`, `include_stacktrace=true`, `max_lines=200`
2. Override each value if the corresponding query param is present
3. Clamp `max_lines` to range [10, 5000]
4. Return the resolved array

### `readLogFileTail` Logic

This method follows the **exact same pattern** as `ErrorLogHandlerTrait::readLogTail()` in RiseUp Asia:

1. Initialize result with `Exists=false`, zeroed counts
2. Check file readability — return early if unreadable
3. Set `Exists=true`, read `filesize()`
4. Read all lines via `file()` with `FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES`
5. Determine if truncated (`totalLines > maxLines`)
6. Slice last N lines if truncated
7. Return metadata + content as newline-joined string

### Large File Protection

Before calling `file()`, check `filesize()`:

```php
$fileSize = @filesize($filePath);
$isFileTooLarge = ($fileSize !== false && $fileSize > 52428800); // 50MB

if ($isFileTooLarge) {
    $result[ResponseKeyType::Content->value] = '[File too large to read: ' . round($fileSize / 1048576, 2) . ' MB]';
    $result[ResponseKeyType::TotalSize->value] = $fileSize;
    return $result;
}
```

---

## 3. Route Registration

### QUpload (`RouteRegistrationTrait.php`)

Add to `registerLogManagementRoutes()`:

```php
$safeRegister(EndpointType::LogsRetrieve->route(), [
    'methods'             => HttpMethodType::Get->value,
    'callback'            => [$this, 'handleLogsRetrieve'],
    'permission_callback' => [$this, 'checkPluginPermission'],
]);
```

### RiseUp Asia

Add to the equivalent route registration method, following the same pattern.

---

## 4. ResponseKeyType Enum Updates

### New Cases (both plugins)

Add these cases if not already present:

```php
case InfoLog       = 'InfoLog';
case StacktraceLog = 'StacktraceLog';
case TotalLines    = 'TotalLines';
```

Note: `ErrorLog`, `Content`, `Lines`, `TotalSize`, `Truncated`, `Exists`, `Path` should already exist in RiseUp Asia's `ResponseKeyType`. Verify and add any missing ones to QUpload.

---

## 5. Trait Usage

Both plugins' main REST controller class must `use` the new trait:

```php
use QUpload\Traits\Log\LogRetrievalTrait;     // QUpload
use RiseupAsia\Traits\Log\LogRetrievalTrait;   // RiseUp Asia
```

---

## 6. File Mapping Summary

| Plugin | File | Change |
|--------|------|--------|
| Both | `Enums/EndpointType.php` | Add `LogsRetrieve` case |
| Both | `Enums/ResponseKeyType.php` | Add `InfoLog`, `StacktraceLog`, `TotalLines` cases |
| Both | `Traits/Log/LogRetrievalTrait.php` | New file — trait implementation |
| Both | `Traits/Route/RouteRegistrationTrait.php` | Register new route |
| Both | Main controller class | `use LogRetrievalTrait` |
