# Shared Error Constants

**Version:** 1.0.0  
**Status:** Active  
**Updated:** 2026-01-28  

---

## Overview

Error codes and constants shared between frontend and backend. These must be kept in sync.

**Cross-References:**
- [Error Management Overview](../00-overview.md)
- [Backend Error Codes](../backend/01-error-codes.md)
- [Frontend Error Codes](../frontend/01-error-codes.md)

---

## Shared Code Ranges

Codes in these ranges are used by both frontend and backend:

| Range | Category | Description |
|-------|----------|-------------|
| 1xxx | Validation | Input validation (forms, API requests) |
| 5xxx | Business Logic | Domain-specific errors |

---

## Shared Error Constants

### Validation Errors (1xxx)

| Code | Constant | Description |
|------|----------|-------------|
| 1001 | `ERR_VALIDATION_REQUIRED` | Required field missing |
| 1002 | `ERR_VALIDATION_FORMAT` | Invalid format |
| 1003 | `ERR_VALIDATION_RANGE` | Value out of range |
| 1004 | `ERR_VALIDATION_LENGTH` | String length violation |
| 1005 | `ERR_VALIDATION_TYPE` | Type mismatch |
| 1006 | `ERR_VALIDATION_UNIQUE` | Uniqueness constraint |
| 1010 | `ERR_VALIDATION_BATCH` | Multiple validation failures |

### Business Logic (5xxx)

| Code | Constant | Description |
|------|----------|-------------|
| 5001 | `ERR_LOGIC_STATE` | Invalid state transition |
| 5002 | `ERR_LOGIC_LIMIT` | Limit exceeded |
| 5003 | `ERR_LOGIC_CONFLICT` | Business rule conflict |
| 5010 | `ERR_SPEC_INVALID` | Invalid specification |
| 5011 | `ERR_SPEC_CIRCULAR` | Circular reference |
| 5012 | `ERR_SPEC_MISSING` | Referenced spec not found |

---

## TypeScript Definition

```typescript
// src/lib/errors/shared-codes.ts

export const SHARED_ERROR_CODES = {
  // Validation
  VALIDATION_REQUIRED: 1001,
  VALIDATION_FORMAT: 1002,
  VALIDATION_RANGE: 1003,
  VALIDATION_LENGTH: 1004,
  VALIDATION_TYPE: 1005,
  VALIDATION_UNIQUE: 1006,
  VALIDATION_BATCH: 1010,
  
  // Business Logic
  LOGIC_STATE: 5001,
  LOGIC_LIMIT: 5002,
  LOGIC_CONFLICT: 5003,
  SPEC_INVALID: 5010,
  SPEC_CIRCULAR: 5011,
  SPEC_MISSING: 5012,
} as const;
```

---

## Go Definition

```go
// internal/errors/shared.go

package errors

// Shared error codes used by both frontend and backend
const (
    // Validation (1xxx)
    ERR_VALIDATION_REQUIRED = 1001
    ERR_VALIDATION_FORMAT   = 1002
    ERR_VALIDATION_RANGE    = 1003
    ERR_VALIDATION_LENGTH   = 1004
    ERR_VALIDATION_TYPE     = 1005
    ERR_VALIDATION_UNIQUE   = 1006
    ERR_VALIDATION_BATCH    = 1010
    
    // Business Logic (5xxx)
    ERR_LOGIC_STATE    = 5001
    ERR_LOGIC_LIMIT    = 5002
    ERR_LOGIC_CONFLICT = 5003
    ERR_SPEC_INVALID   = 5010
    ERR_SPEC_CIRCULAR  = 5011
    ERR_SPEC_MISSING   = 5012
)
```

---

## Sync Process

To keep frontend and backend in sync:

1. Update this file first
2. Update backend `internal/errors/shared.go`
3. Update frontend `src/lib/errors/shared-codes.ts`
4. Run validation tests

---

## Related Specs

- [Backend Error Codes](../backend/01-error-codes.md) — Full backend codes
- [Frontend Error Codes](../frontend/01-error-codes.md) — Full frontend codes
