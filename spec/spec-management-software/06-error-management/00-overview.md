# Error Management

**Version:** 1.0.0  
**Status:** Active  
**Updated:** 2026-01-28  

---

## Overview

Comprehensive error handling specifications for the Spec Management Software. This folder serves as both the project-specific error implementation AND a template for other projects.

**Cross-References:**
- [General Error Management](../../general-spec/01-foundation/02-error-management-foundation.md)
- [Coding Guidelines](../04-coding-guidelines/00-overview.md)
- [Backend Error Codes](./backend/01-error-codes.md)
- [Frontend Error Boundaries](./frontend/02-error-boundaries.md)

---

## Folder Structure

```
06-error-management/
├── 00-overview.md           # This file
├── frontend/                # React/TypeScript error handling
│   ├── 01-error-codes.md    # Frontend error code ranges
│   ├── 02-error-boundaries.md
│   └── 03-user-messaging.md
├── backend/                 # Go error handling
│   ├── 01-error-codes.md    # Backend error code ranges
│   ├── 02-recovery-strategies.md
│   └── 03-logging-patterns.md
└── shared/
    └── 01-error-constants.md  # Shared codes used by both
```

---

## Error Code Ranges

| Range | Category | Owner | Description |
|-------|----------|-------|-------------|
| 1xxx | Validation | Shared | Input validation failures |
| 2xxx | Auth/Authorization | Backend | Authentication and permission errors |
| 3xxx | Database | Backend | SQLite operations, queries |
| 4xxx | External Services | Backend | LLaMA, external APIs, network |
| 5xxx | Business Logic | Shared | Domain-specific errors |
| 6xxx | File System | Backend | File operations, path errors |
| 7xxx | Configuration | Backend | Config parsing, missing values |
| 8xxx | Security/SSRF | Backend | Security violations, SSRF attempts |
| 9xxx | System | Backend | OS-level, resource exhaustion |

---

## Error Structure

### Backend Error Type (Go)

```go
type AppError struct {
    Code       int               `json:"code"`       // e.g., 3001
    Constant   string            `json:"constant"`   // e.g., "ERR_DB_QUERY"
    Message    string            `json:"message"`    // Human-readable
    Details    map[string]any    `json:"details"`    // Contextual data
    Cause      error             `json:"-"`          // Original error
    Retryable  bool              `json:"retryable"`  // Can retry?
    StatusCode int               `json:"-"`          // HTTP status
}
```

### Frontend Error Type (TypeScript)

```typescript
interface AppError {
  code: number;           // e.g., 1001
  constant: string;       // e.g., "ERR_VALIDATION_REQUIRED"
  message: string;        // User-friendly message
  details?: Record<string, unknown>;
  field?: string;         // For form validation
  retryable?: boolean;
}
```

---

## Error Handling Philosophy

### 1. Fail Fast, Recover Gracefully

- Validate inputs at the boundary
- Return meaningful errors immediately
- Provide recovery options when possible

### 2. User-Centric Messages

- Never expose internal error details to users
- Provide actionable guidance
- Localize error messages

### 3. Comprehensive Logging

- Log full error context on backend
- Include stack traces for unexpected errors
- Track error frequency for monitoring

### 4. Graceful Degradation

- Fallback to cached data when possible
- Disable features rather than crashing
- Inform users of degraded state

---

## Quick Reference

### Creating a Backend Error

```go
return errors.NewValidation(
    errors.ERR_VALIDATION_REQUIRED,
    "Email is required",
    map[string]any{"field": "email"},
)
```

### Creating a Frontend Error

```typescript
throw new AppError(
  ERROR_CODES.VALIDATION_REQUIRED,
  "Email is required",
  { field: "email" }
);
```

### Error Response Format

```json
{
  "success": false,
  "error": {
    "code": 1001,
    "constant": "ERR_VALIDATION_REQUIRED",
    "message": "Email is required",
    "details": { "field": "email" }
  }
}
```

---

## Template Usage

This folder structure can be copied to other projects:

1. Copy `06-error-management/` to new project
2. Update error code ranges in `shared/01-error-constants.md`
3. Customize frontend/backend patterns as needed
4. Update cross-references

---

## Related Specs

- [Backend Error Codes](./backend/01-error-codes.md)
- [Frontend Error Codes](./frontend/01-error-codes.md)
- [Error Code Registry](./error-code-registry.md)
- [General Error Foundation](../../general-spec/01-foundation/02-error-management-foundation.md)
