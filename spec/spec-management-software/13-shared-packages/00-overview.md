# Shared Go Packages (Phase 1)

**Version:** 1.0.0  
**Status:** Active  
**Updated:** 2026-01-30  
**Phase:** 1 of 9  

---

## Overview

This specification defines the **Shared Go Packages** (`pkg/`) that form the foundation for all SpecBuilder Pro microservices. These packages enforce consistency, reduce code duplication, and establish architectural patterns across the entire system.

**Cross-References:**
- [Error Management](../06-error-management/00-overview.md)
- [Database Design](../07-database-design/00-overview.md)
- [Coding Guidelines](../04-coding-guidelines/00-overview.md)
- [Master Architecture](./01-architecture.md)

---

## Package Directory Structure

```
pkg/
├── errors/           # Centralized error handling & registry
│   ├── codes.go      # Error code constants
│   ├── types.go      # AppError struct & interfaces
│   ├── factory.go    # Error constructor functions
│   ├── registry.go   # Error code registry & lookup
│   └── http.go       # HTTP response helpers
│
├── database/         # SQLite abstraction layer
│   ├── connection.go # Connection pool management
│   ├── migrations.go # Migration runner
│   ├── transaction.go# Transaction helpers
│   ├── query.go      # Query builder utilities
│   └── health.go     # Health check utilities
│
├── logging/          # Structured logging (slog)
│   ├── logger.go     # Logger factory & configuration
│   ├── context.go    # Context-aware logging
│   ├── middleware.go # HTTP middleware for logging
│   └── fields.go     # Standard field definitions
│
├── config/           # Configuration management (Viper)
│   ├── loader.go     # Config file loading
│   ├── validation.go # Config validation
│   ├── types.go      # Config struct definitions
│   └── env.go        # Environment variable handling
│
└── types/            # Shared DTOs & domain types
    ├── ids.go        # Typed ID definitions
    ├── pagination.go # Pagination types
    ├── response.go   # API response wrappers
    ├── metadata.go   # Common metadata types
    └── enums.go      # Shared enum definitions
```

---

## Design Principles

### 1. Zero External Dependencies in Core

Core packages (`errors`, `types`) have **zero external dependencies** beyond the Go standard library. This ensures:
- Fast compilation
- Minimal attack surface
- Easy testing

### 2. Interface-First Design

All packages expose interfaces, not concrete types:

```go
// Good: Interface-based
type Logger interface {
    Info(msg string, args ...any)
    Error(msg string, args ...any)
    With(args ...any) Logger
}

// Bad: Concrete type exposure
func NewLogger() *SlogLogger { ... }
```

### 3. Functional Options Pattern

Configuration uses functional options for extensibility:

```go
db, err := database.New(
    database.WithPath("/path/to/db.sqlite"),
    database.WithMaxConns(10),
    database.WithMigrations(migrations),
)
```

### 4. Context Propagation

All operations accept `context.Context` as the first parameter:

```go
func (db *DB) Query(ctx context.Context, query string, args ...any) (*Rows, error)
```

---

## Package Specifications

| Package | Spec Document | Priority | Dependencies |
|---------|---------------|----------|--------------|
| `pkg/errors` | [02-pkg-errors.md](./02-pkg-errors.md) | P0 | None |
| `pkg/types` | [03-pkg-types.md](./03-pkg-types.md) | P0 | `pkg/errors` |
| `pkg/logging` | [04-pkg-logging.md](./04-pkg-logging.md) | P0 | `pkg/types` |
| `pkg/config` | [05-pkg-config.md](./05-pkg-config.md) | P1 | `pkg/errors`, `pkg/types` |
| `pkg/database` | [06-pkg-database.md](./06-pkg-database.md) | P1 | All above |

---

## Implementation Order

```
Phase 1.1: pkg/errors    ─────┐
                              ├──► Phase 1.3: pkg/logging ──┐
Phase 1.2: pkg/types     ─────┘                             │
                                                            ├──► Phase 1.5: pkg/database
Phase 1.4: pkg/config    ───────────────────────────────────┘
```

---

## Testing Requirements

### Unit Test Coverage

| Package | Minimum Coverage | Test File Pattern |
|---------|------------------|-------------------|
| `pkg/errors` | 95% | `*_test.go` |
| `pkg/types` | 90% | `*_test.go` |
| `pkg/logging` | 85% | `*_test.go` |
| `pkg/config` | 90% | `*_test.go` |
| `pkg/database` | 85% | `*_test.go` |

### Integration Tests

- Database package requires SQLite integration tests
- Config package requires file system tests
- All packages require benchmark tests for hot paths

---

## Version Compatibility

| Dependency | Minimum Version | Notes |
|------------|-----------------|-------|
| Go | 1.22+ | Required for slog, generics |
| SQLite | 3.40+ | Required for JSON functions |
| Viper | v1.18+ | Config management |
| modernc.org/sqlite | Latest | Pure Go SQLite driver |

---

## Related Specifications

- [pkg/errors Specification](./02-pkg-errors.md)
- [pkg/types Specification](./03-pkg-types.md)
- [pkg/logging Specification](./04-pkg-logging.md)
- [pkg/config Specification](./05-pkg-config.md)
- [pkg/database Specification](./06-pkg-database.md)
- [Integration Patterns](./07-integration-patterns.md)
