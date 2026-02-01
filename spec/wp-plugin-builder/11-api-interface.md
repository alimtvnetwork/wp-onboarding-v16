# API Interface

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-02-01  

---

## Overview

REST API for WP Plugin Builder when running in server mode. Provides HTTP endpoints for all CLI operations.

**Cross-References:**
- [CLI Interface](./02-cli-interface.md)
- [Configuration](./03-configuration.md)
- [Error Handling](./10-error-handling.md)

---

## Server Configuration

Default: `localhost:8090`

```json
{
  "server": {
    "host": "localhost",
    "port": 8090,
    "cors": true,
    "corsOrigins": ["*"],
    "rateLimit": {
      "enabled": true,
      "requestsPerMinute": 60
    }
  }
}
```

---

## Base URL

```
http://localhost:8090/api/v1
```

---

## Authentication

Currently supports optional API key authentication:

```
Authorization: Bearer <api-key>
```

Configure in `wpb.json`:

```json
{
  "server": {
    "apiKey": "your-secret-key"
  }
}
```

---

## Endpoints

### Health Check

```http
GET /health
```

**Response:**
```json
{
  "status": "healthy",
  "version": "1.0.0",
  "uptime": "2h30m15s"
}
```

---

### Projects

#### List Projects

```http
GET /projects
```

**Query Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `sort` | string | `created` | Sort by: name, created, updated |
| `order` | string | `desc` | Order: asc, desc |

**Response:**
```json
{
  "projects": [
    {
      "id": 1,
      "name": "Exam Manager",
      "slug": "exam-manager",
      "author": "John Doe",
      "version": "1.0.0",
      "createdAt": "2026-02-01T10:00:00Z",
      "lastGeneratedAt": "2026-02-01T12:30:00Z"
    }
  ],
  "total": 1
}
```

#### Create Project

```http
POST /projects
```

**Request Body:**
```json
{
  "name": "Exam Manager",
  "author": "John Doe",
  "authorEmail": "john@example.com",
  "website": "https://example.com",
  "description": "Exam management plugin"
}
```

**Response:**
```json
{
  "id": 1,
  "name": "Exam Manager",
  "slug": "exam-manager",
  "dbPath": "~/.wpb/projects/exam-manager.sqlite",
  "createdAt": "2026-02-01T10:00:00Z"
}
```

#### Get Project

```http
GET /projects/:slug
```

**Response:**
```json
{
  "id": 1,
  "name": "Exam Manager",
  "slug": "exam-manager",
  "author": "John Doe",
  "authorEmail": "john@example.com",
  "website": "https://example.com",
  "description": "Exam management plugin",
  "version": "1.0.0",
  "textDomain": "exam-manager",
  "namespace": "ExamManager",
  "createdAt": "2026-02-01T10:00:00Z",
  "updatedAt": "2026-02-01T12:30:00Z",
  "generationCount": 5
}
```

#### Delete Project

```http
DELETE /projects/:slug
```

**Query Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `keepFiles` | boolean | `false` | Keep generated files |

**Response:**
```json
{
  "success": true,
  "message": "Project deleted"
}
```

#### Clone Project

```http
POST /projects/:slug/clone
```

**Request Body:**
```json
{
  "targetName": "Quiz Maker",
  "includeHistory": true
}
```

#### Export Project

```http
GET /projects/:slug/export
```

**Query Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `format` | string | `sqlite` | Export format: sqlite, zip |
| `includeFiles` | boolean | `true` | Include generated files (zip only) |

**Response:** Binary file download

#### Import Project

```http
POST /projects/import
Content-Type: multipart/form-data
```

**Form Fields:**
| Field | Type | Description |
|-------|------|-------------|
| `file` | file | SQLite database or zip file |
| `name` | string | Override project name (optional) |
| `overwrite` | boolean | Overwrite if exists |

---

### Presets

#### List Presets

```http
GET /presets
```

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `category` | string | Filter by category |

**Response:**
```json
{
  "presets": [
    {
      "id": 1,
      "name": "wordpress-core-standards",
      "category": "core",
      "chunkCount": 15,
      "isActive": true
    }
  ]
}
```

#### Import Preset

```http
POST /presets
Content-Type: multipart/form-data
```

**Form Fields:**
| Field | Type | Description |
|-------|------|-------------|
| `file` | file | Markdown preset file |
| `name` | string | Override preset name |
| `category` | string | Preset category |

#### Apply Preset to Project

```http
POST /projects/:slug/presets
```

**Request Body:**
```json
{
  "presetName": "wordpress-security"
}
```

---

### Specifications

#### List Specifications

```http
GET /projects/:slug/specs
```

**Response:**
```json
{
  "specifications": [
    {
      "id": 1,
      "name": "exam-crud.md",
      "format": "markdown",
      "importedAt": "2026-02-01T11:00:00Z"
    }
  ]
}
```

#### Import Specification

```http
POST /projects/:slug/specs
Content-Type: multipart/form-data
```

**Form Fields:**
| Field | Type | Description |
|-------|------|-------------|
| `file` | file | Markdown, folder (zip), or zip file |
| `format` | string | auto, md, zip, folder |

---

### Generation

#### Generate Code

```http
POST /projects/:slug/generate
```

**Request Body:**
```json
{
  "specId": 1,
  "component": "admin",
  "options": {
    "validate": true,
    "overwriteMode": "backup",
    "dryRun": false
  }
}
```

**Response:**
```json
{
  "success": true,
  "files": [
    {
      "path": "admin/class-exam-manager-admin.php",
      "action": "created",
      "size": 5432
    }
  ],
  "stats": {
    "filesGenerated": 3,
    "duration": "2.5s"
  }
}
```

#### Stream Generation

```http
POST /projects/:slug/generate/stream
```

Uses Server-Sent Events (SSE) for streaming output.

**Response (SSE):**
```
event: chunk
data: {"content": "<?php\n/**\n * Admin class\n */"}

event: chunk
data: {"content": "\nclass Exam_Manager_Admin {"}

event: file
data: {"path": "admin/class-exam-manager-admin.php", "action": "created"}

event: done
data: {"filesGenerated": 3, "duration": "2.5s"}
```

---

### Validation

#### Validate Code

```http
POST /projects/:slug/validate
```

**Request Body:**
```json
{
  "specId": 1,
  "path": "./plugins/exam-manager"
}
```

**Response:**
```json
{
  "valid": true,
  "warnings": [
    "Missing text domain in line 45"
  ],
  "errors": []
}
```

---

## Error Responses

All errors follow this format:

```json
{
  "code": 10305,
  "message": "Project not found",
  "details": {
    "slug": "nonexistent-project"
  }
}
```

---

## Rate Limiting

When rate limiting is enabled:

```http
HTTP/1.1 429 Too Many Requests
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1706792400

{
  "code": 10703,
  "message": "Rate limit exceeded",
  "details": {
    "retryAfter": 30
  }
}
```

---

## CORS

When CORS is enabled, the following headers are sent:

```http
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS
Access-Control-Allow-Headers: Authorization, Content-Type
```

---

## OpenAPI Specification

Available at:

```http
GET /openapi.json
```

---

## See Also

- [CLI Interface](./02-cli-interface.md)
- [Error Handling](./10-error-handling.md)
- [Configuration](./03-configuration.md)
