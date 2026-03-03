# QUpload — REST API Endpoints

## Base Namespace

`qupload/v1`

Full URL: `https://{site}/wp-json/qupload/v1/{endpoint}`

## Endpoints

### 1. `POST /upload`

Upload a plugin ZIP, extract, replace existing, and activate.

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `plugin_zip` | file/base64 | Yes | — | Plugin ZIP archive |
| `slug` | string | No | auto-detect | Target plugin slug |
| `activate` | bool | No | `true` | Activate after install |

**Auth:** Application Password (activate_plugins capability)

**Flow:**
1. Parse input (multipart or base64)
2. Write ZIP to temp file
3. Validate ZIP structure
4. Detect plugin slug from ZIP contents
5. Deactivate existing plugin if updating
6. Delete old plugin directory
7. Extract ZIP to temp → rename to correct slug
8. Reset OPcache
9. Activate plugin (if requested)
10. Detect installed version
11. Return result envelope

### 2. `POST /activate`

Activate an already-installed plugin.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `slug` | string | Yes | Plugin slug to activate |

**Auth:** Application Password (activate_plugins capability)

**Flow:**
1. Validate slug is provided
2. Find plugin file by slug
3. Call `activate_plugin()`
4. Capture any activation errors with stack trace
5. Return result envelope

### 3. `GET /status`

Health check endpoint.

**Auth:** Application Password (any authenticated user)

**Response:**
```json
{
  "Success": true,
  "Results": [{
    "Plugin": "Quick Upload",
    "Version": "1.0.0",
    "PhpVersion": "8.2.0",
    "WpVersion": "6.4.2",
    "Timestamp": "2024-01-15T09:30:00+00:00"
  }]
}
```
