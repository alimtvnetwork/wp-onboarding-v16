# Quick Upload (QUpload) Plugin Specification

> A minimal WordPress plugin for remote plugin upload and activation via REST API.
> **PHP 8.2+** with PSR-4 namespacing under `QUpload\`.

## Purpose

QUpload is a **thin, single-purpose** WordPress plugin that:

1. Accepts a plugin ZIP file via REST API and installs/replaces it
2. Activates a specified plugin on demand
3. Logs all errors with full stack traces to file

**It does NOT** include: database storage, transaction logging, admin UI, snapshots, agents, sync, post management, licensing, or any other feature beyond upload + activate + error logging.

## Document Structure

| File | Description |
|------|-------------|
| [01-endpoints.md](./01-endpoints.md) | REST API endpoint definitions |
| [02-file-structure.md](./02-file-structure.md) | Directory layout and class map |
| [03-powershell-script.md](./03-powershell-script.md) | PowerShell upload script spec |

## Plugin Identity

| Key | Value |
|-----|-------|
| Plugin Name | Quick Upload |
| Slug | `qupload` |
| Text Domain | `qupload` |
| Namespace | `QUpload\` |
| API Namespace | `qupload-api/v1` |
| Version | `1.0.0` |
| Requires PHP | 8.2 |
| Requires WP | 5.6 |
| Log Prefix | `[QUpload]` |
| Uploads Subdir | `qupload` |

## Endpoints

### POST `/wp-json/qupload-api/v1/upload`

Accepts a plugin ZIP (multipart `plugin_zip` or base64 JSON), extracts it to `wp-content/plugins/`, replaces any existing version, and activates it.

**Request:**
- `plugin_zip` (file or base64 string) — the ZIP archive
- `slug` (string, optional) — target plugin slug (auto-detected from ZIP if omitted)
- `activate` (bool, optional, default: `true`) — activate after upload

**Response (envelope):**
```json
{
  "Success": true,
  "Code": 200,
  "Message": "OK",
  "Results": [{
    "PluginSlug": "my-plugin",
    "IsUpdate": true,
    "Activated": true,
    "PluginVersion": "1.2.3"
  }]
}
```

### POST `/wp-json/qupload-api/v1/activate`

Activates an already-installed plugin by slug.

**Request (JSON):**
```json
{ "slug": "my-plugin" }
```

**Response (envelope):**
```json
{
  "Success": true,
  "Code": 200,
  "Message": "OK",
  "Results": [{
    "PluginSlug": "my-plugin",
    "Activated": true,
    "PluginVersion": "1.2.3"
  }]
}
```

### GET `/wp-json/qupload/v1/status`

Returns plugin health and version info. No authentication required if configured.

## Authentication

All endpoints (except status if configured) require WordPress Application Password via HTTP Basic Auth.

## Error Handling

- All endpoint handlers wrapped in `try-catch (Throwable)`
- Errors logged to `wp-content/uploads/qupload/logs/error.log` with stack traces
- Stack traces logged to `wp-content/uploads/qupload/logs/stacktrace.log`
- General log at `wp-content/uploads/qupload/logs/log.txt`
- Response always returns the standardized envelope format

## Coding Standards

Follows the project's WordPress plugin development specification:
- Backed enums for all constants (no magic strings)
- PSR-4 autoloading
- Lazy initialization (no WP function calls in constructors)
- Positive-polarity boolean variables
- `DateHelper::nowIso()` instead of raw `gmdate()`
- ABSPATH guards on every file
- PascalCase response keys, camelCase log keys

## PowerShell Script

A dedicated `upload-plugin-U-Q.ps1` script uploads a plugin ZIP directly to the QUpload endpoint. It:
- Creates a ZIP of the target plugin folder
- Uploads via `POST /wp-json/qupload/v1/upload`
- Reports success/failure with colored output
- Supports config file, CLI params, and inline JSON
- Includes self-lint header

## Author

**MD ALIM UL KARIM**
https://rasia.pro/alim-r-profile-v1
