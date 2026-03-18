# QUpload — REST API Endpoints

## Base Namespace

`qupload-api/v1`

Full URL: `https://{site}/wp-json/qupload-api/v1/{endpoint}`

## Authentication

All endpoints require WordPress Application Password authentication.

| Permission Level | Capability | Used By |
|-----------------|------------|---------|
| Status | Any authenticated user | `GET /status` |
| Plugin operations | `activate_plugins` | All other endpoints |

---

## Core Endpoints

### 1. `GET /status`

Health check endpoint.

**Auth:** Application Password (any authenticated user)

**Response:**
```json
{
  "Success": true,
  "Results": [{
    "Plugin": "Quick Upload",
    "Version": "2.17.0",
    "PhpVersion": "8.2.0",
    "WpVersion": "6.4.2",
    "Timestamp": "2026-01-15T09:30:00+00:00"
  }]
}
```

### 2. `POST /upload`

Upload a plugin ZIP, extract, replace existing, and activate.

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `plugin_zip` | file/base64 | Yes | — | Plugin ZIP archive |
| `slug` | string | No | auto-detect | Target plugin slug |
| `activate` | bool | No | `true` | Activate after install |

**Auth:** Application Password (`activate_plugins` capability)

**Flow:**
1. Parse input (multipart or base64)
2. Write ZIP to temp file
3. Validate ZIP structure
4. Detect plugin slug from ZIP contents
5. Back up existing plugin directory (if updating)
6. Deactivate existing plugin if updating
7. Delete old plugin directory
8. Extract ZIP to temp → rename to correct slug
9. Pre-activation PHP syntax validation
10. Reset OPcache
11. Activate plugin (if requested)
12. Detect installed version
13. Return result envelope (rollback on failure)

### 3. `PUT /activate`

Activate an already-installed plugin.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `slug` | string | Yes | Plugin slug to activate |

**Auth:** Application Password (`activate_plugins` capability)

**Flow:**
1. Validate slug is provided
2. Find plugin file by slug
3. Call `activate_plugin()`
4. Capture any activation errors with stack trace
5. Return result envelope

### 4. `PUT /deactivate`

Deactivate an active plugin.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `slug` | string | Yes | Plugin slug to deactivate |

**Auth:** Application Password (`activate_plugins` capability)

**Flow:**
1. Validate slug is provided
2. Find plugin file by slug
3. Check if already inactive (return success with message)
4. Call `deactivate_plugins()`
5. Verify deactivation succeeded
6. Return result envelope

### 5. `GET /plugins`

List all installed plugins.

**Auth:** Application Password (`activate_plugins` capability)

**Response:** Envelope with array of installed plugin details.

---

## Log Management Endpoints

### 6. `GET /logs/status`

Returns log file metadata (sizes, modification dates) for remote monitoring.

**Auth:** Application Password (`activate_plugins` capability)

### 7. `GET /logs/rotation-status`

Returns log rotation configuration and state.

**Auth:** Application Password (`activate_plugins` capability)

### 8. `DELETE /logs/clear`

Step 1 of two-step log clearing. Validates machine, generates a 60-second confirmation token.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `machine_name` | string | Yes | Approved machine identifier |

**Auth:** Application Password (`activate_plugins` capability)

### 9. `POST /logs/clear/confirm`

Step 2 of two-step log clearing. Consumes the token from step 1 and executes deletion.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `token` | string | Yes | Confirmation token from step 1 |

**Auth:** Application Password (`activate_plugins` capability)

### 10. `POST /logs/email`

Emails log files as attachments via `wp_mail()`.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `to` | string | Yes | Recipient email address |
| `include_rotations` | bool | No | Include archived rotations |

**Auth:** Application Password (`activate_plugins` capability)

---

## Machine Management Endpoints

### 11. `PUT /machines/approve`

Adds a machine name to the approved machines list stored in WordPress options.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `machine_name` | string | Yes | Machine name to approve |

**Auth:** Application Password (`activate_plugins` capability)

---

## Endpoint Summary

| # | Method | Endpoint | Handler Trait | Description |
|---|--------|----------|---------------|-------------|
| 1 | GET | `/status` | `StatusHandlerTrait` | Health check |
| 2 | POST | `/upload` | `UploadHandlerTrait` | Upload + install plugin |
| 3 | PUT | `/activate` | `ActivateHandlerTrait` | Activate plugin |
| 4 | PUT | `/deactivate` | `DeactivateEndpointTrait` | Deactivate plugin |
| 5 | GET | `/plugins` | `PluginInventoryTrait` | List installed plugins |
| 6 | GET | `/logs/status` | `LogStatusTrait` | Log file metadata |
| 7 | GET | `/logs/rotation-status` | `LogRotationStatusTrait` | Rotation config/state |
| 8 | DELETE | `/logs/clear` | `LogClearingTrait` | Request log clearing |
| 9 | POST | `/logs/clear/confirm` | `LogClearingTrait` | Confirm log clearing |
| 10 | POST | `/logs/email` | `LogEmailTrait` | Email log files |
| 11 | PUT | `/machines/approve` | `MachineApprovalTrait` | Approve machine |
