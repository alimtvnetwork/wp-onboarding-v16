# Info-Object Pattern for Go Enums

**Version:** 1.0.0
**Status:** Complete
**Updated:** 2026-04-09

---

## Purpose

Define the **preferred pattern** for attaching structured metadata to enum variants using a map-based lookup that returns an info struct. This replaces scattered `switch` statements with a centralised data structure.

---

## Problem

When a variant needs labels, descriptions, and additional metadata, naive implementations duplicate branching:

### ❌ Anti-Pattern — Multiple Switches

```go
// FORBIDDEN — scattered metadata
func (v Variant) Label() string {
    switch v {
    case Active:  return "Currently active"
    case Archived: return "Archived"
    default: return "Invalid"
    }
}

func (v Variant) Icon() string {
    switch v {  // Duplicate branching
    case Active:  return "✅"
    case Archived: return "📦"
    default: return "?"
    }
}
```

---

## Solution — Info Struct with Map Lookup

### Step 1: Define the Info Struct

```go
// EnumInfo holds structured metadata for a single variant.
type EnumInfo struct {
    Label   string
    Details string
}
```

### Step 2: Define the Map

```go
var variantInfo = map[Variant]EnumInfo{
    Active: {
        Label:   "Currently active",
        Details: "This item is live and visible.",
    },
    Inactive: {
        Label:   "Inactive",
        Details: "Disabled but can be re-activated.",
    },
    Archived: {
        Label:   "Archived",
        Details: "Read-only, cannot be modified.",
    },
}
```

### Step 3: Expose `Info()` Method

```go
// Info returns the structured metadata for this variant.
func (v Variant) Info() EnumInfo {
    if info, isFound := variantInfo[v]; isFound {
        return info
    }

    return EnumInfo{Label: variantLabels[Invalid]}
}
```

### Step 4: Delegate `Label()` to `Info()`

```go
// Label returns the human-readable label — delegates to Info().
func (v Variant) Label() string {
    return v.Info().Label
}
```

---

## Resolution Flow

```
┌──────────────┐     ┌──────────────────┐     ┌──────────────┐
│  Variant      │────▶│  Info()           │────▶│  EnumInfo     │
│  (e.g. Active)│     │  Lookup in map    │     │  .Label       │
└──────────────┘     └──────────────────┘     │  .Details     │
                                               └──────────────┘
                                                      │
                      ┌──────────────────┐            │
                      │  Label()          │◀───────────┘
                      │  return Info.Label│
                      └──────────────────┘
```

---

## Complete Example

```go
package status

type Variant byte

const (
    Invalid  Variant = iota
    Active
    Inactive
    Archived
)

var variantLabels = [...]string{
    Invalid:  "Invalid",
    Active:   "Active",
    Inactive: "Inactive",
    Archived: "Archived",
}

// EnumInfo holds structured metadata for a single variant.
type EnumInfo struct {
    Label   string
    Details string
}

var variantInfo = map[Variant]EnumInfo{
    Active: {
        Label:   "Currently active",
        Details: "This item is live and visible.",
    },
    Inactive: {
        Label:   "Inactive",
        Details: "Disabled but can be re-activated.",
    },
    Archived: {
        Label:   "Archived",
        Details: "Read-only, cannot be modified.",
    },
}

func (v Variant) String() string {
    if !v.IsValid() {
        return variantLabels[Invalid]
    }

    return variantLabels[v]
}

func (v Variant) Info() EnumInfo {
    if info, isFound := variantInfo[v]; isFound {
        return info
    }

    return EnumInfo{Label: variantLabels[Invalid]}
}

func (v Variant) Label() string {
    return v.Info().Label
}

func (v Variant) IsValid() bool {
    return v > Invalid && v < Variant(len(variantLabels))
}
```

---

## When to Use

| Scenario | Use Info-Object? | Why |
|----------|-----------------|-----|
| Enum only needs `String()` | ❌ No | `variantLabels` array is sufficient |
| Enum needs `Label()` only (= `String()`) | ❌ No | Delegate to `String()` |
| Enum has `Label()` + `Details()` | ✅ Yes | Avoids duplicate switches |
| Enum has 3+ metadata fields | ✅ Mandatory | Scattered switches unmaintainable |
| Enum has 10+ variants with metadata | ✅ Mandatory | Map is easier to audit |

---

## Rules

### R1: Map is Single Source of Truth

All metadata lives in the `variantInfo` map. No metadata may be derived via separate `switch` statements.

### R2: `Label()` Delegates — Never Contains Logic

```go
// ✅ Correct
func (v Variant) Label() string { return v.Info().Label }

// ❌ Forbidden
func (v Variant) Label() string { switch v { ... } }
```

### R3: Graceful Fallback for Invalid

`Info()` must return a valid `EnumInfo` for `Invalid` — never panic.

### R4: `String()` Remains Independent

`String()` uses `variantLabels` (the identity table). It does NOT call `Info()`. The `Info().Label` may contain a longer human-readable description, while `String()` returns the PascalCase identifier.

---

## Cross-References

- [01-enum-pattern.md](01-enum-pattern.md) — core byte-based enum pattern
- [02-required-methods.md](02-required-methods.md) — mandatory methods
- [PHP Info-Object Pattern](../../18-how-to-write-wordpress-plugin/02-enums-and-coding-style/02-enum-info-object-pattern.md) — same pattern for PHP

---

*Map-based enum metadata pattern for Go.*
