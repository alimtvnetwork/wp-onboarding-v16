# Log Retrieval — Go Backend Proxy Endpoint

> **Route:** `GET /api/sites/{siteId}/logs/retrieve`

---

## 1. Endpoint Definition

### Route

```
GET /api/sites/{siteId}/logs/retrieve
```

### Query Parameters (forwarded to WordPress)

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `include_info_log` | bool | `true` | Include info log content |
| `include_error_log` | bool | `true` | Include error log content |
| `include_stacktrace` | bool | `true` | Include stack trace content |
| `max_lines` | int | `200` | Max lines per file (10–5000) |

### Authentication

Requires valid session (same as other `/api/sites/*` endpoints).

---

## 2. Handler Logic

1. Resolve `siteId` → site record from database
2. Determine plugin variant (QUpload vs RiseUp Asia) from site metadata
3. Build WordPress API URL: `{siteUrl}/wp-json/{namespace}/v1/logs/retrieve?{queryParams}`
4. Forward query parameters as-is
5. Execute HTTP GET with Application Password credentials
6. Parse WordPress response
7. Return to React as-is (transparent proxy)

### Plugin Variant Detection

The Go backend already knows which plugin is installed on each site. Use the existing variant detection to select the correct API namespace:

- **QUpload:** `qupload-api/v1`
- **RiseUp Asia:** `riseup-api/v1`

---

## 3. Error Handling

| Scenario | HTTP Code | Response |
|----------|-----------|----------|
| Site not found | 404 | `{"Success": false, "Message": "Site not found"}` |
| WordPress unreachable | 502 | `{"Success": false, "Message": "Failed to connect to WordPress site"}` |
| WordPress auth failure | 401/403 | Forward WordPress error |
| Endpoint not found (old plugin) | 404 | `{"Success": false, "Message": "Log retrieval endpoint not available. Plugin v2.18.0+ required."}` |
| WordPress 500 | 502 | Forward WordPress error details |

### Timeout

- **Connect timeout:** 10 seconds
- **Read timeout:** 30 seconds (log files may be large)

---

## 4. Registration

Register in the site routes handler alongside existing endpoints:

```go
router.GET("/api/sites/:siteId/logs/retrieve", handler.HandleLogsRetrieve)
```

### Handler File

`backend/pkg/sites/handler_logs_retrieve.go`

---

## 5. Minimum Version Requirement

This endpoint requires plugin version **2.18.0+**. The Go backend should check the site's known plugin version before making the request and return a clear error if the version is too old.
