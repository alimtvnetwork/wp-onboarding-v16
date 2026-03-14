# QUpload — Suggested Additional Endpoints

> **Status:** Proposed  
> **Created:** 2026-03-14  
> **Plugin:** QUpload

---

## Currently Implemented

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/status` | Health check |
| POST | `/upload` | Upload plugin ZIP |
| PUT | `/activate` | Activate installed plugin |
| GET | `/logs/status` | Log file sizes and line counts |
| DELETE | `/logs/clear` | Request log clearing (returns token) |
| POST | `/logs/clear/confirm` | Confirm log clearing |
| POST | `/logs/email` | Email log files |

---

## Suggested Additions

### 1. `PUT /deactivate`

Deactivate an installed plugin by slug.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `slug` | string | Yes | Plugin slug to deactivate |

**Rationale:** Currently QUpload can activate but not deactivate. Needed for safe remote management and pre-maintenance workflows.

---

### 2. `GET /plugins`

List all installed plugins with status.

**Response:** Array of `{ slug, name, version, isActive, file }` for each installed plugin.

**Rationale:** Enables remote inventory — the Go backend can verify deployment state without SSH access.

---

### 3. `DELETE /plugins/delete`

Delete an installed plugin by slug.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `slug` | string | Yes | Plugin slug to delete |

**Rationale:** Remote cleanup of abandoned or test plugins. Requires deactivation before deletion.

---

### 4. `POST /upload-active`

Upload plugin ZIP and force-activate in one call (combines `/upload` + `/activate`).

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `plugin_zip` | file/base64 | Yes | Plugin ZIP archive |
| `slug` | string | No | Target plugin slug |

**Rationale:** Mirrors Riseup Asia's `/upload-active` endpoint. Reduces round-trips for the common "upload and activate" workflow from 2 calls to 1.

---

### 5. `GET /opcache/status`

Return OPcache status information.

**Response:** `{ enabled, memoryUsage, hitRate, cachedScripts, lastReset }`

**Rationale:** Diagnostic visibility for PHP performance. Useful for debugging stale bytecode issues after deployments.

---

### 6. `POST /opcache/reset`

Force an OPcache reset.

**Rationale:** Already done internally during uploads, but an explicit endpoint allows the Go backend to trigger it independently when encountering stale code errors.

---

## Implementation Priority

1. **PUT /deactivate** — Completes the activate/deactivate pair
2. **GET /plugins** — Enables remote inventory
3. **POST /upload-active** — Reduces deployment round-trips
4. **POST /opcache/reset** — Already partially implemented, easy to expose
5. **GET /opcache/status** — Nice-to-have diagnostics
6. **DELETE /plugins/delete** — Lower priority, more dangerous operation

---

## Notes

- All new endpoints require Application Password auth with `activate_plugins` capability
- Error responses must follow the existing `EnvelopeBuilder` pattern with stack traces
- New `EndpointType` enum cases needed for each added endpoint
