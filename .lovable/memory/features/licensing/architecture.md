# Phase 7D: Licensing Server — Architecture Document

> Status: **Architecture approved** | Updated: 2026-03-03  
> Implementation: Deferred (future phase)

---

## 1. Overview

A standalone, self-hosted Go licensing server that issues, validates, and manages license keys for the Riseup Asia plugin ecosystem. It runs as a separate binary alongside the existing `wp-plugin-publish` backend, sharing no code but following the same coding standards.

### Design Principles
- **Zero external dependencies** — SQLite for storage, no Redis/Postgres required
- **Horizontally simple** — single-binary deployment, no microservices
- **Offline-tolerant** — PHP plugin caches license state locally; re-validates on cron
- **Secure by default** — HMAC-signed requests, rate-limited, bcrypt-hashed admin credentials

---

## 2. Repository Structure

```
licensing/                          # New Go module at repo root
├── go.mod                          # module riseup-licensing
├── go.sum
├── cmd/
│   └── server/
│       └── main.go                 # Entry point, config loading, server start
├── internal/
│   ├── config/
│   │   └── Config.go               # Env-based config (port, DB path, HMAC secret, etc.)
│   ├── database/
│   │   ├── Database.go             # SQLite connection + migrations
│   │   └── migrations/
│   │       └── 001_initial.sql     # Schema creation
│   ├── enums/
│   │   ├── licensestatus/
│   │   │   └── Variant.go          # Active, Expired, Suspended, Revoked
│   │   ├── licensetype/
│   │   │   └── Variant.go          # Standard, Professional, Enterprise
│   │   └── producttype/
│   │       └── Variant.go          # RiseupUploader, future products
│   ├── handlers/
│   │   ├── AdminHandlers.go        # License CRUD (admin-only)
│   │   ├── PublicHandlers.go       # Validate, Activate, Deactivate
│   │   └── Middleware.go           # HMAC verification, rate limiting, admin auth
│   ├── models/
│   │   ├── License.go              # License entity
│   │   ├── Activation.go           # Domain activation entity
│   │   └── AuditLog.go             # Audit trail entity
│   ├── services/
│   │   ├── LicenseService.go       # Core business logic
│   │   ├── ActivationService.go    # Activation/deactivation logic
│   │   ├── KeyGenerator.go         # Cryptographic key generation
│   │   └── ValidationService.go    # License validation rules
│   └── router/
│       └── Router.go               # Route registration
├── pkg/
│   ├── hmac/
│   │   └── Signer.go               # HMAC-SHA256 request signing/verification
│   └── ratelimit/
│       └── Limiter.go              # Token-bucket rate limiter (in-memory)
└── tests/
    ├── license_test.go
    ├── activation_test.go
    └── integration_test.go
```

---

## 3. Database Schema (SQLite)

```sql
-- 001_initial.sql

CREATE TABLE licenses (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    key         TEXT    NOT NULL UNIQUE,          -- RISEUP-XXXX-XXXX-XXXX-XXXX
    email       TEXT    NOT NULL,
    product     TEXT    NOT NULL,                 -- producttype enum value
    type        TEXT    NOT NULL DEFAULT 'standard', -- licensetype enum value
    status      TEXT    NOT NULL DEFAULT 'active',   -- licensestatus enum value
    max_activations INTEGER NOT NULL DEFAULT 1,
    notes       TEXT,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at  DATETIME,                        -- NULL = never expires
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE activations (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    license_id  INTEGER NOT NULL REFERENCES licenses(id) ON DELETE CASCADE,
    domain      TEXT    NOT NULL,                 -- e.g. "example.com"
    ip_address  TEXT,
    user_agent  TEXT,
    activated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deactivated_at DATETIME,                     -- NULL = currently active
    UNIQUE(license_id, domain)
);

CREATE TABLE audit_log (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    license_id  INTEGER REFERENCES licenses(id) ON DELETE SET NULL,
    action      TEXT    NOT NULL,                 -- "created", "activated", "deactivated", "validated", "expired", "revoked"
    domain      TEXT,
    ip_address  TEXT,
    details     TEXT,                             -- JSON blob
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_licenses_key ON licenses(key);
CREATE INDEX idx_licenses_email ON licenses(email);
CREATE INDEX idx_licenses_status ON licenses(status);
CREATE INDEX idx_activations_license ON activations(license_id);
CREATE INDEX idx_activations_domain ON activations(domain);
CREATE INDEX idx_audit_license ON audit_log(license_id);
CREATE INDEX idx_audit_action ON audit_log(action);
```

---

## 4. API Contract

### 4.1 Public Endpoints (HMAC-signed)

All public endpoints require an `X-Signature` header with HMAC-SHA256 of the request body using a shared secret.

#### `GET /api/v1/licenses/{key}/validate`

Validates a license key without consuming an activation slot.

**Response 200:**
```json
{
  "valid": true,
  "license": {
    "key": "RISEUP-XXXX-XXXX-XXXX-XXXX",
    "product": "riseup-uploader",
    "type": "professional",
    "status": "active",
    "expires_at": "2027-03-03T00:00:00Z",
    "activations_used": 2,
    "activations_max": 5
  }
}
```

**Response 404:**
```json
{ "valid": false, "error": "license_not_found" }
```

**Error codes:** `license_not_found`, `license_expired`, `license_suspended`, `license_revoked`

#### `POST /api/v1/licenses/{key}/activate`

Activates a license on a domain. Idempotent — re-activating the same domain returns success.

**Request body:**
```json
{
  "domain": "example.com",
  "plugin_version": "1.57.0"
}
```

**Response 200:**
```json
{
  "activated": true,
  "activation_id": 42,
  "activations_used": 3,
  "activations_max": 5
}
```

**Error codes:** `license_not_found`, `activation_limit_reached`, `license_expired`, `license_suspended`

#### `POST /api/v1/licenses/{key}/deactivate`

Removes a domain activation (soft-delete: sets `deactivated_at`).

**Request body:**
```json
{ "domain": "example.com" }
```

**Response 200:**
```json
{ "deactivated": true, "activations_used": 2, "activations_max": 5 }
```

#### `GET /api/v1/licenses/{key}/status`

Full license details including all active domains.

**Response 200:**
```json
{
  "license": {
    "key": "RISEUP-XXXX-XXXX-XXXX-XXXX",
    "email": "user@example.com",
    "product": "riseup-uploader",
    "type": "professional",
    "status": "active",
    "max_activations": 5,
    "expires_at": "2027-03-03T00:00:00Z",
    "created_at": "2026-03-03T00:00:00Z"
  },
  "activations": [
    { "domain": "site1.com", "activated_at": "2026-03-03T12:00:00Z" },
    { "domain": "site2.com", "activated_at": "2026-03-04T08:30:00Z" }
  ]
}
```

### 4.2 Admin Endpoints (Bearer token auth)

Admin endpoints require `Authorization: Bearer <admin_token>` header.

#### `POST /api/v1/admin/licenses`

Create a new license.

**Request body:**
```json
{
  "email": "customer@example.com",
  "product": "riseup-uploader",
  "type": "professional",
  "max_activations": 5,
  "expires_at": "2027-03-03T00:00:00Z",
  "notes": "Purchased via Gumroad order #12345"
}
```

**Response 201:**
```json
{
  "license": {
    "id": 1,
    "key": "RISEUP-A1B2-C3D4-E5F6-G7H8",
    "email": "customer@example.com",
    "product": "riseup-uploader",
    "type": "professional",
    "status": "active",
    "max_activations": 5,
    "expires_at": "2027-03-03T00:00:00Z"
  }
}
```

#### `GET /api/v1/admin/licenses`

List all licenses with pagination and filters.

**Query params:** `?page=1&per_page=50&status=active&product=riseup-uploader&email=user@example.com`

#### `PATCH /api/v1/admin/licenses/{id}`

Update license fields (status, max_activations, expires_at, notes).

#### `DELETE /api/v1/admin/licenses/{id}`

Revoke and permanently delete a license and all activations.

#### `GET /api/v1/admin/audit`

Query audit log with filters.

**Query params:** `?license_id=1&action=activated&from=2026-01-01&to=2026-12-31`

---

## 5. License Key Format

Format: `RISEUP-XXXX-XXXX-XXXX-XXXX` (25 chars including prefix)

Each `XXXX` segment is 4 alphanumeric characters (uppercase, no ambiguous chars: `0O1IlL` excluded).

**Generation:** `crypto/rand` → base32 encoding → segment formatting.

**Collision prevention:** DB unique constraint + retry loop (max 3 attempts).

---

## 6. Security Model

### 6.1 HMAC Request Signing

Public API calls from PHP plugins sign the request using a shared secret:

```
signature = HMAC-SHA256(shared_secret, timestamp + ":" + request_body)
```

Headers:
- `X-Signature: <hex-encoded signature>`
- `X-Timestamp: <unix timestamp>`

Server validates:
1. Timestamp within ±5 minutes (replay prevention)
2. Signature matches recomputed HMAC

### 6.2 Rate Limiting

| Endpoint | Limit |
|----------|-------|
| `/validate` | 60/min per IP |
| `/activate` | 10/min per IP |
| `/deactivate` | 10/min per IP |
| `/status` | 30/min per IP |
| Admin endpoints | 120/min per token |

In-memory token bucket per IP. Resets on server restart (acceptable for single-instance).

### 6.3 Admin Authentication

- Single admin token stored as env var `LICENSING_ADMIN_TOKEN`
- Bearer token auth on all `/admin/*` endpoints
- Future: multi-user admin with bcrypt-hashed passwords

---

## 7. Integration Points

### 7.1 PHP Plugin (Riseup Asia Uploader)

**New files:**
```
wp-plugins/riseup-asia-uploader/includes/
├── Enums/
│   └── LicenseStatusType.php       # Active, Expired, Invalid, Unchecked
├── Licensing/
│   ├── LicenseClient.php           # HTTP client for licensing server
│   ├── LicenseManager.php          # Orchestrates check/activate/deactivate
│   └── LicenseCache.php            # wp_options-based cache with TTL
└── Hooks/
    └── LicenseHooksTrait.php       # Activation/deactivation/cron hooks
```

**Flow — Plugin Activation:**
1. User enters license key in admin settings
2. `LicenseClient::activate($key, $domain)` → POST to licensing server
3. On success: store key + status in `wp_options`, set daily cron for re-validation
4. On failure: show admin notice, plugin remains functional (grace period: 7 days)

**Flow — Daily Re-validation (Cron):**
1. `LicenseClient::validate($key)` → GET to licensing server
2. Update cached status in `wp_options`
3. If expired/revoked: show persistent admin notice, disable premium features after grace period

**Flow — Plugin Deactivation:**
1. `LicenseClient::deactivate($key, $domain)` → POST to licensing server
2. Clear cached license data from `wp_options`

**Graceful Degradation:**
- If licensing server is unreachable: use cached status (valid for 7 days)
- If cache expired and server unreachable: show warning but don't disable plugin
- Premium features gated by license type (standard vs professional vs enterprise)

### 7.2 Go Backend (wp-plugin-publish)

**New files:**
```
backend/internal/services/licensing/
├── Service.go                      # License check client
├── Cache.go                        # In-memory TTL cache for license status
└── Middleware.go                   # HTTP middleware for premium endpoints
```

**Integration:**
- `LicensingMiddleware` wraps premium API handlers
- Checks cached license status for the requesting site
- Falls back to licensing server if cache miss
- Non-premium endpoints remain unprotected

### 7.3 React Frontend

**New components:**
```
src/components/licensing/
├── LicenseKeyInput.tsx             # License key entry form
├── LicenseStatus.tsx               # Status badge (active/expired/etc.)
└── LicenseManagement.tsx           # Activate/deactivate actions
```

**Integration:**
- Settings page includes license key management section
- Status shown in sidebar/header
- Premium features conditionally rendered based on license status

---

## 8. Configuration

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `LICENSING_PORT` | `8090` | HTTP server port |
| `LICENSING_DB_PATH` | `./data/licensing.db` | SQLite database file path |
| `LICENSING_HMAC_SECRET` | (required) | Shared secret for HMAC signing |
| `LICENSING_ADMIN_TOKEN` | (required) | Admin API bearer token |
| `LICENSING_RATE_LIMIT` | `60` | Default requests/min per IP |
| `LICENSING_GRACE_DAYS` | `7` | Grace period after expiration |
| `LICENSING_LOG_LEVEL` | `info` | Log verbosity |

---

## 9. Deployment

### Single-binary deployment:
```bash
cd licensing
go build -o licensing-server ./cmd/server
./licensing-server
```

### Docker (optional):
```dockerfile
FROM golang:1.22-alpine AS builder
WORKDIR /app
COPY . .
RUN go build -o licensing-server ./cmd/server

FROM alpine:3.19
COPY --from=builder /app/licensing-server /usr/local/bin/
COPY --from=builder /app/data /data
EXPOSE 8090
CMD ["licensing-server"]
```

### Reverse proxy:
- Licensing server behind nginx/caddy alongside the main backend
- `/licensing/` prefix proxied to `localhost:8090`

---

## 10. Future Enhancements (Out of Scope for v1)

1. **Webhook notifications** — notify external systems on license events (Gumroad, Stripe)
2. **Usage analytics** — track feature usage per license for business intelligence
3. **Multi-product support** — license bundles, upgrade/downgrade paths
4. **Admin SPA** — React dashboard for license management (currently API-only + simple templates)
5. **Distributed rate limiting** — Redis-backed if scaling beyond single instance
6. **License key migration** — import from third-party licensing services

---

## 11. Acceptance Criteria (Architecture Phase)

- [x] Architecture document complete with all sections
- [x] Database schema defined with indexes
- [x] API contract documented with request/response examples
- [x] Security model specified (HMAC, rate limiting, admin auth)
- [x] Integration points identified in PHP, Go, and React codebases
- [x] Configuration and deployment strategy documented
- [ ] Team review and approval (pending)
