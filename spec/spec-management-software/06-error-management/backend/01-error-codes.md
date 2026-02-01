# Backend Error Codes

**Version:** 1.0.0  
**Status:** Active  
**Updated:** 2026-01-28  

---

## Overview

Comprehensive error codes for the Go backend, organized by category.

**Cross-References:**
- [Error Management Overview](../00-overview.md)
- [Shared Error Constants](../shared/01-error-constants.md)
- [Recovery Strategies](./02-recovery-strategies.md)

---

## Error Code Ranges

### 1xxx - Validation Errors

| Code | Constant | HTTP | Description |
|------|----------|------|-------------|
| 1001 | `ERR_VALIDATION_REQUIRED` | 400 | Required field missing |
| 1002 | `ERR_VALIDATION_FORMAT` | 400 | Invalid format |
| 1003 | `ERR_VALIDATION_RANGE` | 400 | Value out of range |
| 1004 | `ERR_VALIDATION_LENGTH` | 400 | String length violation |
| 1005 | `ERR_VALIDATION_TYPE` | 400 | Type mismatch |
| 1006 | `ERR_VALIDATION_UNIQUE` | 409 | Uniqueness constraint violated |
| 1007 | `ERR_VALIDATION_REFERENCE` | 400 | Invalid reference/FK |
| 1010 | `ERR_VALIDATION_BATCH` | 400 | Batch validation failed |

### 2xxx - Authentication/Authorization

| Code | Constant | HTTP | Description |
|------|----------|------|-------------|
| 2001 | `ERR_AUTH_FAILED` | 401 | Authentication failed |
| 2002 | `ERR_AUTH_EXPIRED` | 401 | Token/session expired |
| 2003 | `ERR_AUTH_INVALID_TOKEN` | 401 | Invalid token |
| 2004 | `ERR_AUTH_REVOKED` | 401 | Token revoked |
| 2010 | `ERR_AUTHZ_DENIED` | 403 | Permission denied |
| 2011 | `ERR_AUTHZ_ROLE` | 403 | Insufficient role |
| 2012 | `ERR_AUTHZ_RESOURCE` | 403 | No access to resource |

### 3xxx - Database Errors

| Code | Constant | HTTP | Description |
|------|----------|------|-------------|
| 3001 | `ERR_DB_QUERY` | 500 | Query execution failed |
| 3002 | `ERR_DB_NOT_FOUND` | 404 | Record not found |
| 3003 | `ERR_DB_DUPLICATE` | 409 | Duplicate key |
| 3004 | `ERR_DB_CONSTRAINT` | 400 | Constraint violation |
| 3005 | `ERR_DB_TRANSACTION` | 500 | Transaction failed |
| 3006 | `ERR_DB_LOCKED` | 503 | Database locked (SQLite) |
| 3007 | `ERR_DB_CONNECTION` | 503 | Connection failed |
| 3008 | `ERR_DB_MIGRATION` | 500 | Migration failed |

### 4xxx - External Services

| Code | Constant | HTTP | Description |
|------|----------|------|-------------|
| 4001 | `ERR_EXT_TIMEOUT` | 504 | External service timeout |
| 4002 | `ERR_EXT_UNAVAILABLE` | 503 | Service unavailable |
| 4003 | `ERR_EXT_RESPONSE` | 502 | Invalid response |
| 4010 | `ERR_LLAMA_CONNECTION` | 503 | LLaMA server connection failed |
| 4011 | `ERR_LLAMA_TIMEOUT` | 504 | LLaMA request timeout |
| 4012 | `ERR_LLAMA_RESPONSE` | 502 | Invalid LLaMA response |
| 4020 | `ERR_GIT_COMMAND` | 500 | Git command failed |
| 4021 | `ERR_GIT_CONFLICT` | 409 | Git merge conflict |

### 5xxx - Business Logic

| Code | Constant | HTTP | Description |
|------|----------|------|-------------|
| 5001 | `ERR_LOGIC_STATE` | 400 | Invalid state transition |
| 5002 | `ERR_LOGIC_LIMIT` | 400 | Limit exceeded |
| 5003 | `ERR_LOGIC_CONFLICT` | 409 | Business rule conflict |
| 5004 | `ERR_LOGIC_DEPENDENCY` | 400 | Dependency not met |
| 5010 | `ERR_SPEC_INVALID` | 400 | Invalid specification format |
| 5011 | `ERR_SPEC_CIRCULAR` | 400 | Circular reference detected |
| 5012 | `ERR_SPEC_MISSING` | 404 | Referenced spec not found |

### 6xxx - File System

| Code | Constant | HTTP | Description |
|------|----------|------|-------------|
| 6001 | `ERR_FS_NOT_FOUND` | 404 | File not found |
| 6002 | `ERR_FS_PERMISSION` | 403 | Permission denied |
| 6003 | `ERR_FS_EXISTS` | 409 | File already exists |
| 6004 | `ERR_FS_INVALID_PATH` | 400 | Invalid path |
| 6005 | `ERR_FS_TRAVERSAL` | 400 | Path traversal attempt |
| 6006 | `ERR_FS_READ` | 500 | Read operation failed |
| 6007 | `ERR_FS_WRITE` | 500 | Write operation failed |
| 6008 | `ERR_FS_DELETE` | 500 | Delete operation failed |
| 6009 | `ERR_FS_HASH_MISMATCH` | 409 | Optimistic lock failure |

### 7xxx - Configuration

| Code | Constant | HTTP | Description |
|------|----------|------|-------------|
| 7001 | `ERR_CONFIG_MISSING` | 500 | Required config missing |
| 7002 | `ERR_CONFIG_INVALID` | 500 | Invalid config value |
| 7003 | `ERR_CONFIG_PARSE` | 500 | Config file parse error |
| 7004 | `ERR_CONFIG_SCHEMA` | 500 | Schema validation failed |

### 8xxx - Security

| Code | Constant | HTTP | Description |
|------|----------|------|-------------|
| 8001 | `ERR_SEC_SSRF` | 400 | SSRF attempt blocked |
| 8002 | `ERR_SEC_XSS` | 400 | XSS attempt detected |
| 8003 | `ERR_SEC_INJECTION` | 400 | Injection attempt |
| 8004 | `ERR_SEC_RATE_LIMIT` | 429 | Rate limit exceeded |
| 8005 | `ERR_SEC_BRUTE_FORCE` | 429 | Brute force lockout |

### 9xxx - System

| Code | Constant | HTTP | Description |
|------|----------|------|-------------|
| 9001 | `ERR_SYS_INTERNAL` | 500 | Internal server error |
| 9002 | `ERR_SYS_MEMORY` | 503 | Memory exhaustion |
| 9003 | `ERR_SYS_DISK` | 503 | Disk space exhaustion |
| 9004 | `ERR_SYS_TIMEOUT` | 503 | Operation timeout |
| 9005 | `ERR_SYS_PANIC` | 500 | Recovered panic |

---

## Go Implementation

```go
// internal/errors/codes.go

package errors

const (
    // Validation (1xxx)
    ERR_VALIDATION_REQUIRED = 1001
    ERR_VALIDATION_FORMAT   = 1002
    ERR_VALIDATION_RANGE    = 1003
    // ... etc
    
    // Authentication (2xxx)
    ERR_AUTH_FAILED        = 2001
    ERR_AUTH_EXPIRED       = 2002
    // ... etc
)

// Error code to HTTP status mapping
var codeToStatus = map[int]int{
    ERR_VALIDATION_REQUIRED: 400,
    ERR_AUTH_FAILED:         401,
    ERR_AUTHZ_DENIED:        403,
    ERR_DB_NOT_FOUND:        404,
    ERR_DB_DUPLICATE:        409,
    ERR_SEC_RATE_LIMIT:      429,
    ERR_SYS_INTERNAL:        500,
    ERR_DB_LOCKED:           503,
    ERR_EXT_TIMEOUT:         504,
}
```

---

## Related Specs

- [Recovery Strategies](./02-recovery-strategies.md)
- [Logging Patterns](./03-logging-patterns.md)
- [Frontend Error Codes](../frontend/01-error-codes.md)
