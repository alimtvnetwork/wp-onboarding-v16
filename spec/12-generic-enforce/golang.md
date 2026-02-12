# Generic Enforce — Golang

> This file covers **Go-specific syntax and idioms only**.  
> For rules, rationale, and the canonical example, see [`README.md`](./README.md).

---

## Alias Mechanism (Go 1.18+)

```go
// Type alias — preserves method set
type AliasName = GenericType[ConcreteA, ConcreteB]

// Type definition — creates new type, can add methods
type NewType GenericType[ConcreteA, ConcreteB]
```

Use **alias** (`=`) to preserve methods. Use **definition** (no `=`) when adding methods.

---

## Student-Teacher in Go

```go
type Student[TRights any, TKey comparable] struct {
    ID        TKey    `json:"id"`
    Rights    TRights `json:"rights"`
    Name      string  `json:"name"`
    EnrolledAt string `json:"enrolledAt"`
}

// Named instantiations
type TeacherBasicRights = Student[BasicRights, int]
type TeacherBasicRightsV2 = Student[BasicRightsV2, int]
type StudentByUUID = Student[BasicRights, string]

func GetTeacher(id int) TeacherBasicRights { ... }
```

---

## Replacing `map[string]interface{}`

```go
// ❌ Prohibited
type ApiError struct {
    Context map[string]interface{} `json:"context"`
}

// ✅ Required
type ErrorContext struct {
    Endpoint  string `json:"endpoint,omitempty"`
    StatusCode int   `json:"statusCode,omitempty"`
    PluginId  int    `json:"pluginId,omitempty"`
    SessionId string `json:"sessionId,omitempty"`
}

type ApiError struct {
    Context *ErrorContext `json:"context,omitempty"`
}
```

---

## Replacing `interface{}` / `any` params

```go
// ❌ Prohibited
func Process(data interface{}) error { ... }

// ✅ Use type constraints
type Processable interface {
    Plugin | Site | Settings
}

func Process[T Processable](data T) error { ... }
```

---

## Go-Specific Notes

- `any` (Go 1.18+) is syntactic sugar for `interface{}` — equally prohibited in struct fields
- Constraint interfaces are the idiomatic replacement for `any` in generic type params
- Go has no `unknown` — the closest equivalent is `interface{}`, which is always prohibited
