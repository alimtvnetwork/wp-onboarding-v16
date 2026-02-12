# Generic Enforce — Golang

**Applies to**: All `.go` files. Requires Go 1.18+.

---

## Mechanism: `type` Definitions

Go 1.18+ supports type definitions for generic instantiations:

```go
// Base generic
type Envelope[T any] struct {
    Status     EnvelopeStatus     `json:"Status"`
    Attributes EnvelopeAttributes `json:"Attributes"`
    Results    []T                `json:"Results"`
    Errors     *EnvelopeErrors    `json:"Errors,omitempty"`
}

// ✅ Named instantiations
type PluginEnvelope = Envelope[Plugin]
type SiteEnvelope = Envelope[Site]
type SettingsEnvelope = Envelope[Settings]
```

---

## Prohibited Patterns

```go
// ❌ NEVER: interface{} or any as field type
type SessionInfo struct {
    Metadata interface{} `json:"metadata"`
}

// ❌ NEVER: map[string]interface{} 
type ApiError struct {
    Context map[string]interface{} `json:"context"`
}

// ❌ NEVER: Raw generic in function signatures (when used repeatedly)
func GetTeacher() Student[BasicRights, int] { ... }  // if used in 3+ places
```

## Required Replacements

### `map[string]interface{}` → Named Struct

```go
// BEFORE (prohibited)
type ApiError struct {
    Context map[string]interface{} `json:"context"`
}

// AFTER (required)
type ErrorContext struct {
    Endpoint  string `json:"endpoint,omitempty"`
    StatusCode int   `json:"statusCode,omitempty"`
    RequestId string `json:"requestId,omitempty"`
    PluginId  int    `json:"pluginId,omitempty"`
    SessionId string `json:"sessionId,omitempty"`
}

type ApiError struct {
    Context *ErrorContext `json:"context,omitempty"`
}
```

### `interface{}` → Constrained Generic or Concrete Type

```go
// BEFORE (prohibited)
func Process(data interface{}) error { ... }

// AFTER: Use a type constraint
type Processable interface {
    Plugin | Site | Settings
}

func Process[T Processable](data T) error { ... }
```

---

## The Student-Teacher Pattern in Go

```go
// Base generic
type Student[TRights any, TKey comparable] struct {
    ID        TKey     `json:"id"`
    Rights    TRights  `json:"rights"`
    Name      string   `json:"name"`
    EnrolledAt string  `json:"enrolledAt"`
}

// Rights types
type BasicRights struct {
    CanRead  bool `json:"canRead"`
    CanWrite bool `json:"canWrite"`
}

type BasicRightsV2 struct {
    BasicRights
    CanAdmin  bool `json:"canAdmin"`
    CanExport bool `json:"canExport"`
}

// ✅ Named instantiations (REQUIRED)
type TeacherBasicRights = Student[BasicRights, int]
type TeacherBasicRightsV2 = Student[BasicRightsV2, int]
type StudentByUUID = Student[BasicRights, string]

// ✅ Usage
func GetTeacher(id int) TeacherBasicRights { ... }
func GetTeacherV2(id int) TeacherBasicRightsV2 { ... }
```

---

## Go-Specific Notes

1. **Type aliases (`=`) vs type definitions**: Use `type X = Y[A, B]` (alias) to preserve method sets. Use `type X Y[A, B]` (definition) when you need to add methods.
2. **`any` keyword**: Go 1.18 introduced `any` as alias for `interface{}`. Both are prohibited in struct fields and function params — use concrete types or constrained generics.
3. **Constraint interfaces**: Define constraint interfaces (`Processable`, `Identifiable`) to replace `any` in generic type parameters.
