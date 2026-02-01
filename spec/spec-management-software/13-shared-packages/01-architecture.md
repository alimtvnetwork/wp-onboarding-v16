# Shared Packages Architecture

**Version:** 1.0.0  
**Status:** Active  
**Updated:** 2026-01-30  

---

## Microservices Context

The shared packages serve as the foundation for the SpecBuilder Pro microservices architecture:

```
┌─────────────────────────────────────────────────────────────────┐
│                     SpecBuilder Pro                              │
├─────────────────────────────────────────────────────────────────┤
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌────────┐ │
│  │ Gateway  │ │SpecMgr   │ │ AI-Bridge│ │Chronicle │ │ Scout  │ │
│  │ :8080    │ │ :8081    │ │ :8082    │ │ :8083    │ │ :8084  │ │
│  └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬─────┘ └───┬────┘ │
│       │            │            │            │           │      │
│       └────────────┴────────────┴────────────┴───────────┘      │
│                              │                                   │
│  ┌───────────────────────────┴───────────────────────────────┐  │
│  │                    Shared Packages (pkg/)                  │  │
│  │  ┌─────────┐ ┌──────────┐ ┌─────────┐ ┌────────┐ ┌───────┐│  │
│  │  │ errors  │ │ database │ │ logging │ │ config │ │ types ││  │
│  │  └─────────┘ └──────────┘ └─────────┘ └────────┘ └───────┘│  │
│  └───────────────────────────────────────────────────────────┘  │
│                                                                  │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │                   Nexus-Flow Engine                        │  │
│  │                        :9000                               │  │
│  └───────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Dependency Graph

```
                    ┌─────────────────┐
                    │   pkg/errors    │  ◄── Zero dependencies
                    └────────┬────────┘
                             │
                             ▼
                    ┌─────────────────┐
                    │   pkg/types     │  ◄── Depends on errors
                    └────────┬────────┘
                             │
              ┌──────────────┼──────────────┐
              │              │              │
              ▼              ▼              ▼
     ┌─────────────┐ ┌─────────────┐ ┌─────────────┐
     │ pkg/logging │ │ pkg/config  │ │    ...      │
     └──────┬──────┘ └──────┬──────┘ └─────────────┘
            │               │
            └───────┬───────┘
                    ▼
           ┌─────────────────┐
           │  pkg/database   │  ◄── Depends on all above
           └─────────────────┘
```

---

## Package Responsibilities

### pkg/errors

**Purpose:** Centralized error handling with typed error codes

**Exports:**
- `AppError` struct with code, message, details
- Error factory functions (`NewValidation`, `NewDatabase`, etc.)
- Error code constants (1xxx-9xxx ranges)
- HTTP response helpers

**Does NOT:**
- Handle logging (that's `pkg/logging`)
- Handle recovery (service-level concern)

---

### pkg/types

**Purpose:** Shared type definitions and DTOs

**Exports:**
- Typed IDs (`ProjectID`, `SpecID`, `ConversationID`)
- Pagination types (`PageRequest`, `PageResponse`)
- API response wrappers (`Response[T]`, `ErrorResponse`)
- Common enums (`Status`, `Priority`, `Severity`)

**Does NOT:**
- Contain business logic
- Import external packages

---

### pkg/logging

**Purpose:** Structured logging with context propagation

**Exports:**
- `Logger` interface
- `NewLogger` factory with options
- HTTP middleware for request logging
- Context extraction utilities

**Does NOT:**
- Write to external services (that's service-level)
- Handle log rotation (OS-level concern)

---

### pkg/config

**Purpose:** Configuration loading and validation

**Exports:**
- `Load` function for config files
- `Config` struct definitions
- Environment variable binding
- Validation functions

**Does NOT:**
- Handle secrets (use environment variables)
- Provide hot-reload (future feature)

---

### pkg/database

**Purpose:** SQLite connection management and utilities

**Exports:**
- `DB` wrapper with connection pooling
- Migration runner
- Transaction helpers
- Query builder utilities
- Health check functions

**Does NOT:**
- Define schemas (service-level concern)
- Handle application-level queries

---

## Cross-Package Patterns

### Error Wrapping

All packages use consistent error wrapping:

```go
// In pkg/database
if err := db.Ping(); err != nil {
    return errors.NewDatabase(
        errors.ErrDatabaseConnection,
        "failed to ping database",
        map[string]any{"path": db.path},
    ).WithCause(err)
}
```

### Context Usage

All operations accept context for:
- Cancellation propagation
- Request tracing
- Timeout enforcement

```go
func (db *DB) QueryRow(ctx context.Context, query string, args ...any) *Row {
    // Context used for query timeout
}
```

### Logging Integration

All packages integrate with logging:

```go
// Logger is injected, not imported
type DB struct {
    conn   *sql.DB
    logger logging.Logger
}
```

---

## Module Structure

```
github.com/specbuilder/pkg
├── go.mod
├── go.sum
├── errors/
├── types/
├── logging/
├── config/
└── database/
```

### go.mod

```go
module github.com/specbuilder/pkg

go 1.22

require (
    github.com/spf13/viper v1.18.0
    modernc.org/sqlite v1.28.0
)
```

---

## Versioning Strategy

Shared packages follow semantic versioning:

- **Major:** Breaking API changes
- **Minor:** New features, backward compatible
- **Patch:** Bug fixes only

All microservices pin to a specific `pkg` version to prevent cascade failures.
