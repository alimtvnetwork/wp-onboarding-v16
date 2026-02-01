# Helper Naming Guidelines

**Version:** 1.0.0  
**Status:** Active  
**Updated:** 2026-01-27  

---

## Overview

This document defines the naming conventions for utility helper functions across the codebase. Helpers must be organized by their **behavioral domain**, not by their return type.

---

## Core Principle

> **Helpers must match the domain of their behavior, not their return type.**

### Anti-Pattern

```go
// ❌ WRONG: BooleanHelpers used for non-boolean domain logic
BooleanHelpers.IsFalsy(value)
BooleanHelpers.IsNotFunctionExists("func")
BooleanHelpers.IsNotEmpty(arr)
```

### Correct Pattern

```go
// ✓ CORRECT: Domain-specific helper classes
ValueHelpers.IsFalsy(value)
FunctionHelpers.IsFunctionMissing("func")
ArrayHelpers.HasItems(arr)
```

---

## Helper Domain Categories

| Domain | Package | Purpose |
|--------|---------|---------|
| Value/Type | `ValueHelpers` | General value checks (falsy, nil, empty) |
| Function | `FunctionHelpers` | Function existence and signature checks |
| String | `StringHelpers` | String validation and manipulation |
| Number | `NumberHelpers` | Numeric range and validation |
| File | `FileHelpers` | File existence and properties |
| Path | `PathHelpers` | Path validation and security |
| DateTime | `DateTimeHelpers` | Date/time comparisons |
| Array | `ArrayHelpers` | Collection checks and operations |
| Config | `ConfigHelpers` | Configuration validation |
| Auth | `AuthHelpers` | Authentication and authorization |
| Hash | `HashHelpers` | Hashing and comparison |
| JSON | `JSONHelpers` | JSON parsing and validation |

---

## Method Naming Conventions

### Verb Phrases

| Verb Prefix | Usage | Example |
|-------------|-------|---------|
| `Is` | Boolean check for positive state | `IsValid`, `IsEmpty`, `IsExpired` |
| `IsMissing` | Check for absence | `IsFileMissing`, `IsFunctionMissing` |
| `IsInvalid` | Validation failure | `IsInvalidEmail`, `IsInvalidPath` |
| `Has` | Presence/ownership check | `HasItems`, `HasPermission` |
| `Can` | Capability check | `CanWrite`, `CanExecute` |
| `Should` | Conditional behavior | `ShouldRetry`, `ShouldCache` |

### Avoid Double Negatives

```go
// ❌ WRONG: Double negative is confusing
IsNotEmpty(arr)
IsNotFunctionExists("func")

// ✓ CORRECT: Positive phrasing
HasItems(arr)
IsFunctionMissing("func")
```

---

## Canonical Examples

### ValueHelpers

```go
package helpers

// ValueHelpers provides checks for general values
type ValueHelpers struct{}

// IsFalsy returns true if value is nil, zero, empty string, or false
func (ValueHelpers) IsFalsy(value interface{}) bool {
    if value == nil {
        return true
    }
    switch v := value.(type) {
    case bool:
        return !v
    case string:
        return v == ""
    case int, int32, int64:
        return v == 0
    case float32, float64:
        return v == 0.0
    default:
        return false
    }
}

// IsPresent returns true if value is non-nil and non-zero
func (ValueHelpers) IsPresent(value interface{}) bool {
    return !ValueHelpers{}.IsFalsy(value)
}

// IsNullOrEmpty returns true if value is nil or empty string
func (ValueHelpers) IsNullOrEmpty(value *string) bool {
    return value == nil || *value == ""
}
```

### FunctionHelpers

```go
package helpers

import "reflect"

// FunctionHelpers provides checks for functions
type FunctionHelpers struct{}

// IsFunctionMissing returns true if the function doesn't exist in target
func (FunctionHelpers) IsFunctionMissing(target interface{}, name string) bool {
    t := reflect.TypeOf(target)
    _, exists := t.MethodByName(name)
    return !exists
}

// IsFunctionDefined returns true if the function exists
func (FunctionHelpers) IsFunctionDefined(target interface{}, name string) bool {
    return !FunctionHelpers{}.IsFunctionMissing(target, name)
}
```

### StringHelpers

```go
package helpers

import (
    "strings"
    "unicode"
)

// StringHelpers provides string validation
type StringHelpers struct{}

// IsBlank returns true if string is empty or only whitespace
func (StringHelpers) IsBlank(s string) bool {
    return strings.TrimSpace(s) == ""
}

// IsTooLong returns true if string exceeds max length
func (StringHelpers) IsTooLong(s string, maxLen int) bool {
    return len(s) > maxLen
}

// IsAlphanumeric returns true if string contains only letters and numbers
func (StringHelpers) IsAlphanumeric(s string) bool {
    for _, r := range s {
        if !unicode.IsLetter(r) && !unicode.IsDigit(r) {
            return false
        }
    }
    return true
}
```

### FileHelpers

```go
package helpers

import "os"

// FileHelpers provides file system checks
type FileHelpers struct{}

// IsFileMissing returns true if file doesn't exist
func (FileHelpers) IsFileMissing(path string) bool {
    _, err := os.Stat(path)
    return os.IsNotExist(err)
}

// IsFilePresent returns true if file exists
func (FileHelpers) IsFilePresent(path string) bool {
    return !FileHelpers{}.IsFileMissing(path)
}

// IsDirectoryEmpty returns true if directory has no entries
func (FileHelpers) IsDirectoryEmpty(path string) bool {
    entries, err := os.ReadDir(path)
    if err != nil {
        return true
    }
    return len(entries) == 0
}
```

### PathHelpers

```go
package helpers

import (
    "path/filepath"
    "strings"
)

// PathHelpers provides path validation and security checks
type PathHelpers struct{}

// HasTraversalAttempt returns true if path contains directory traversal
func (PathHelpers) HasTraversalAttempt(path string) bool {
    cleanPath := filepath.Clean(path)
    return strings.Contains(cleanPath, "..")
}

// IsAbsolute returns true if path is absolute
func (PathHelpers) IsAbsolute(path string) bool {
    return filepath.IsAbs(path)
}

// IsTooLong returns true if path exceeds max length
func (PathHelpers) IsTooLong(path string, maxLen int) bool {
    return len(path) > maxLen
}
```

### ArrayHelpers

```go
package helpers

// ArrayHelpers provides collection checks
type ArrayHelpers struct{}

// HasItems returns true if slice is non-nil and has elements
func (ArrayHelpers) HasItems[T any](arr []T) bool {
    return arr != nil && len(arr) > 0
}

// IsEmpty returns true if slice is nil or has no elements
func (ArrayHelpers) IsEmpty[T any](arr []T) bool {
    return arr == nil || len(arr) == 0
}

// ContainsDuplicates returns true if slice has duplicate values
func (ArrayHelpers) ContainsDuplicates[T comparable](arr []T) bool {
    seen := make(map[T]bool)
    for _, item := range arr {
        if seen[item] {
            return true
        }
        seen[item] = true
    }
    return false
}
```

### AuthHelpers

```go
package helpers

import "time"

// AuthHelpers provides authentication checks
type AuthHelpers struct{}

// IsTokenExpired returns true if token expiry is in the past
func (AuthHelpers) IsTokenExpired(expiresAt time.Time) bool {
    return time.Now().After(expiresAt)
}

// HasPermission checks if user has required permission
func (AuthHelpers) HasPermission(userPermissions []string, required string) bool {
    for _, p := range userPermissions {
        if p == required || p == "*" {
            return true
        }
    }
    return false
}
```

---

## Acceptance Criteria

- [ ] No `BooleanHelpers` usage anywhere in codebase
- [ ] All helpers follow `{Domain}Helpers` naming pattern
- [ ] Verb phrases are consistent (IsMissing, not IsNotExists)
- [ ] No double negatives in method names
- [ ] Each helper file includes package-level documentation

---

## Related Specs

- [Database Schema](../07-database-design/01-schema.md)
- [Implementation Guidelines](../06-implementation-guidelines.md)
- [Coding Standards](../../general-spec/01-foundation/01-coding-standards-foundation.md)
