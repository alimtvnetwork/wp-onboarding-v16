# Dedup Registry Remote Endpoint

> **Applies to:** QUpload, RiseUp Asia Uploader  
> **Status:** Implementation  
> **Date:** 2026-03-31  
> **Related:** `spec/02-app-issues/30-persistent-log-deduplication.md`

---

## Overview

Exposes the persistent log deduplication registry (`dedup-registry.json`) via REST API endpoints for remote viewing and clearing from the dashboard.

## Endpoints

### `GET /logs/dedup-registry`

Returns the current dedup registry contents and metadata.

**Response:**

```json
{
  "Success": true,
  "DedupRegistry": {
    "Exists": true,
    "Version": "2.31.0",
    "EntryCount": 42,
    "FileSizeBytes": 1284,
    "Entries": ["a1b2c3d4...", "e5f6a7b8..."]
  }
}
```

When the file doesn't exist:

```json
{
  "Success": true,
  "DedupRegistry": {
    "Exists": false,
    "Version": null,
    "EntryCount": 0,
    "FileSizeBytes": 0,
    "Entries": []
  }
}
```

### `DELETE /logs/dedup-registry`

Clears the dedup registry, allowing all info messages to be logged again.

**Response:**

```json
{
  "Success": true,
  "Message": "Dedup registry cleared",
  "PreviousEntryCount": 42
}
```

## PHP Implementation

### New Enum Cases

**EndpointType** (both plugins):
```php
case LogsDedupRegistry = 'logs/dedup-registry';
```

**ResponseKeyType** (both plugins):
```php
case DedupRegistry = 'DedupRegistry';
case EntryCount    = 'EntryCount';
case Entries       = 'Entries';
case FileSizeBytes = 'FileSizeBytes';
```

### New Trait: `LogDedupRegistryTrait`

**Locations:**
- `wp-plugins/riseup-asia-uploader/includes/Traits/Log/LogDedupRegistryTrait.php`
- `wp-plugins/qupload/includes/Traits/Log/LogDedupRegistryTrait.php`

**Methods:**
- `handleLogsDedupRegistryGet(WP_REST_Request): WP_REST_Response`
- `handleLogsDedupRegistryClear(WP_REST_Request): WP_REST_Response`

### Route Registration

Both plugins register in `registerLogManagementRoutes()`:
```php
$safeRegister(EndpointType::LogsDedupRegistry->route(), [
    'methods'             => 'GET, DELETE',
    'callback'            => [$this, 'handleLogsDedupRegistry'],
    'permission_callback' => [$this, 'checkPluginPermission'],
]);
```

The handler dispatches based on `$request->get_method()`.

## File Summary

| Plugin | File | Change |
|--------|------|--------|
| Both | `Enums/EndpointType.php` | Add `LogsDedupRegistry` case |
| Both | `Enums/ResponseKeyType.php` | Add `DedupRegistry`, `EntryCount`, `Entries`, `FileSizeBytes` |
| Both | `Traits/Log/LogDedupRegistryTrait.php` | New trait |
| Both | `Traits/Route/RouteRegistrationTrait.php` | Register new route |
| Both | `Core/Plugin.php` | `use LogDedupRegistryTrait` |
