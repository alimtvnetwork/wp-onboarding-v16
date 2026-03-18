# Required Methods

**Version:** 4.2.0  
**Status:** Complete  
**Updated:** 2026-02-25

---

## Mandatory Methods

Every enum MUST implement these methods:

---

### 1. String() string

Returns the string representation for serialization and logging.

```go
func (v Variant) String() string {
    if !v.IsValid() {
        return variantLabels[Invalid]
    }
    return variantLabels[v]
}
```

---

### 2. Label() string

Delegates to `String()`. Single lookup table — no separate labels array.

```go
func (v Variant) Label() string {
    return v.String()
}
```

---

### 3. Is{Value}() bool

One method per variant for type checking. Enables clean conditional logic.

```go
func (v Variant) IsSerpApi() bool {
    return v == SerpApi
}

func (v Variant) IsMapsScraper() bool {
    return v == MapsScraper
}

func (v Variant) IsColly() bool {
    return v == Colly
}

func (v Variant) IsInvalid() bool {
    return v == Invalid
}
```

**Usage:**
```go
// ✅ Clean
if provider.IsSerpApi() {
    // ...
}

// ❌ Verbose
if provider == provider.SerpApi {
    // ...
}
```

---

### 4. All() []Variant

Returns all valid variants (excludes Invalid).

```go
func All() []Variant {
    return []Variant{
        SerpApi,
        MapsScraper,
        Colly,
    }
}
```

**Usage:**
```go
for _, p := range provider.All() {
    fmt.Println(p.Label())
}
```

---

### 5. ByIndex(i int) Variant

Returns variant by index. Returns Invalid for invalid indices.

```go
func ByIndex(i int) Variant {
    if i < 0 || i >= len(variantLabels) {
        return Invalid
    }
    return Variant(i)
}
```

**Usage:**
```go
p := provider.ByIndex(1) // Returns SerpApi
```

---

### 6. Parse(s string) (Variant, error)

Parses a string to variant. Case-insensitive.

```go
func Parse(s string) (Variant, error) {
    trimmed := strings.TrimSpace(s)
    for i, str := range variantLabels {
        if strings.EqualFold(str, trimmed) {
            return Variant(i), nil
        }
    }
    return Invalid, fmt.Errorf("invalid provider: %q", s)
}
```

**Usage:**
```go
p, err := provider.Parse("SerpApi")
if err != nil {
    return err
}
```

---

### 7. IsValid() bool

Checks if the variant is a valid, non-Invalid value.

```go
func (v Variant) IsValid() bool {
    return v > Invalid && v < Variant(len(variantLabels))
}
```

**Usage:**
```go
if !p.IsValid() {
    return errors.New("invalid provider")
}
```

---

### 8. IsOther(other Variant) bool

Returns true if the receiver is NOT the given variant. The inverse of `Is{Value}()` but generic — works against any variant without needing a dedicated method.

```go
func (v Variant) IsOther(other Variant) bool {
    return v != other
}
```

**Usage:**
```go
// ✅ Positive boolean logic — no negation
if level.IsOther(loglevel.Debug) {
    // skip debug-only logic
}
```

---

### 9. IsAnyOf(others ...Variant) bool

Returns true if the receiver matches ANY of the given variants. Eliminates multi-condition OR chains.

```go
func (v Variant) IsAnyOf(others ...Variant) bool {
    for _, o := range others {
        if v == o {
            return true
        }
    }
    return false
}
```

**Usage:**
```go
// ✅ Clean multi-match
if action.IsAnyOf(action.Upload, action.UploadActive, action.FileReplace) {
    // handle upload-related actions
}

// ❌ Verbose
if action == action.Upload || action == action.UploadActive || action == action.FileReplace {
    // ...
}
```

---

### 10. MarshalJSON() ([]byte, error)

**Mandatory.** Serializes the enum as its string representation for JSON output.

```go
func (v Variant) MarshalJSON() ([]byte, error) {
    return json.Marshal(v.String())
}
```

---

### 11. UnmarshalJSON(data []byte) error

**Mandatory.** Deserializes from a JSON string back to the byte-based enum.

```go
func (v *Variant) UnmarshalJSON(data []byte) error {
    var s string
    if err := json.Unmarshal(data, &s); err != nil {
        return err
    }

    parsed, err := Parse(s)
    if err != nil {
        return err
    }

    *v = parsed

    return nil
}
```

---

## Optional Methods

These methods are recommended for specific use cases:

### Values() []string

Returns all string values for documentation or CLI help:

```go
func Values() []string {
    result := make([]string, 0, len(variantLabels)-1)
    for _, s := range variantLabels[1:] { // Skip Invalid
        result = append(result, s)
    }
    return result
}
```

---

## Domain-Specific Methods

Enums MAY include domain-specific methods:

### Platform Enum Example

```go
// SiteOperator returns the Google site: operator
func (v Variant) SiteOperator() string {
    switch v {
    case YouTube:
        return "site:youtube.com"
    case Reddit:
        return "site:reddit.com"
    case LinkedIn:
        return "site:linkedin.com"
    default:
        return ""
    }
}

// BaseUrl returns the platform's base URL
func (v Variant) BaseUrl() string {
    switch v {
    case YouTube:
        return "https://youtube.com"
    case Reddit:
        return "https://reddit.com"
    default:
        return ""
    }
}
```

### Provider Enum Example

```go
// RequiresApiKey returns true if the provider needs an API key
func (v Variant) RequiresApiKey() bool {
    switch v {
    case SerpApi:
        return true
    case MapsScraper, Colly:
        return false
    default:
        return false
    }
}

// MaxConcurrent returns the max concurrent requests
func (v Variant) MaxConcurrent() int {
    switch v {
    case SerpApi:
        return 5  // Rate limited
    case MapsScraper:
        return 10
    case Colly:
        return 50
    default:
        return 1
    }
}
```

---

## Complete Example

```go
package provider

import (
    "encoding/json"
    "fmt"
    "strings"
)

type Variant byte

const (
    Invalid Variant = iota
    SerpApi
    MapsScraper
    Colly
)

var variantLabels = [...]string{
    Invalid:     "Invalid",
    SerpApi:     "SerpApi",
    MapsScraper: "MapsScraper",
    Colly:       "Colly",
}

func (v Variant) String() string {
    if !v.IsValid() {
        return variantLabels[Invalid]
    }
    return variantLabels[v]
}

func (v Variant) Label() string {
    return v.String()
}

func (v Variant) IsValid() bool {
    return v > Invalid && v < Variant(len(variantLabels))
}

func (v Variant) IsSerpApi() bool     { return v == SerpApi }
func (v Variant) IsMapsScraper() bool { return v == MapsScraper }
func (v Variant) IsColly() bool       { return v == Colly }
func (v Variant) IsInvalid() bool     { return v == Invalid }

func (v Variant) IsOther(other Variant) bool { return v != other }

func (v Variant) IsAnyOf(others ...Variant) bool {
    for _, o := range others {
        if v == o {
            return true
        }
    }
    return false
}

func All() []Variant {
    return []Variant{SerpApi, MapsScraper, Colly}
}

func ByIndex(i int) Variant {
    if i < 0 || i >= len(variantLabels) {
        return Invalid
    }
    return Variant(i)
}

func Parse(s string) (Variant, error) {
    trimmed := strings.TrimSpace(s)
    for i, str := range variantLabels {
        if strings.EqualFold(str, trimmed) {
            return Variant(i), nil
        }
    }
    return Invalid, fmt.Errorf("invalid provider: %q", s)
}

func Values() []string {
    result := make([]string, 0, len(variantLabels)-1)
    for _, s := range variantLabels[1:] {
        result = append(result, s)
    }
    return result
}

func (v Variant) MarshalJSON() ([]byte, error) {
    return json.Marshal(v.String())
}

func (v *Variant) UnmarshalJSON(data []byte) error {
    var s string
    if err := json.Unmarshal(data, &s); err != nil {
        return err
    }
    parsed, err := Parse(s)
    if err != nil {
        return err
    }
    *v = parsed
    return nil
}
```

---

## PascalCase Label Convention

**Mandatory rule (since v4.2.0):** All `variantLabels` entries MUST use **PascalCase** strings matching the constant name. This applies to **every** enum — no exceptions.

| ❌ Forbidden | ✅ Required |
|-------------|-----------|
| `"per_table"` | `"PerTable"` |
| `"dns_check"` | `"DnsCheck"` |
| `"application/json"` | `"Json"` (label) + `"application/json"` (value) |
| `"Operation completed successfully"` | `"Success"` (label) + sentence (value) |

### Abbreviation Rule in Labels

Acronyms in variant names AND labels follow the PascalCase abbreviation standard — only the first letter is capitalized:

| ❌ Forbidden | ✅ Required |
|-------------|-----------|
| `SerpAPI: "SerpAPI"` | `SerpApi: "SerpApi"` |
| `JSON: "JSON"` | `Json: "Json"` |
| `BaseURL` | `BaseUrl` |
| `GetByID` | `GetById` |

> **Go Interface Exemption:** `MarshalJSON()`, `UnmarshalJSON()` are exempt — they MUST retain standard library spelling.

### Protocol / Wire-Value Enums

Enums whose variants carry a technical wire value (URL paths, MIME types, HTTP headers, JSON keys, display messages) MUST separate **identity** from **wire format**:

- `variantLabels` — PascalCase identity string (used by `String()`, `Label()`, `Parse()`)
- `variantValues` — technical wire string (used by `Value()`, `MarshalJSON()`)

```go
var variantLabels = [...]string{
    Invalid: "Invalid",
    Json:    "Json",
}

var variantValues = [...]string{
    Invalid: "invalid",
    Json:    "application/json",
}

func (v Variant) Value() string { return variantValues[v] }
```

`MarshalJSON()` MUST call `Value()` (not `String()`) to preserve wire compatibility.
`Parse()` MUST check **both** `variantLabels` (EqualFold) and `variantValues` (exact match) for backwards compatibility.

---

## External Switch Prohibition (Rule E1)

**FORBIDDEN:** Writing `switch` statements over enum variants **outside** the enum package to derive string values, map to other variants, or compute derived properties.

Any mapping, grouping, or derived value that depends on enum variants **must** be a method on the `Variant` type inside the enum package itself.

### Why

External switches duplicate knowledge that belongs to the enum. When new variants are added, external switches silently miss them. The enum's own methods are the single source of truth.

### ❌ Forbidden — External switch mapping

```go
// WRONG: This switch in a service package is PROHIBITED
func mapStepToStage(step publishstep.Variant) string {
    switch step {
    case publishstep.Packaging:
        return publishstep.Package.Value()
    case publishstep.Uploading:
        return publishstep.Upload.Value()
    default:
        return step.Value()
    }
}
```

### ✅ Required — Method on the enum

```go
// In publishsteptype/Variant.go
var stageMap = map[Variant]Variant{
    Packaging:  Package,
    Uploading:  Upload,
    Activating: Activate,
}

func (v Variant) Stage() string {
    if base, isFound := stageMap[v]; isFound {
        return base.Value()
    }
    return v.Value()
}
```

### What belongs in the enum package

| Pattern | Location | Example |
|---------|----------|---------|
| Variant → string value | Enum package | `v.Value()`, `v.Label()` |
| Variant → variant mapping | Enum package | `v.Stage()`, `v.Parent()` |
| Variant → derived property | Enum package | `v.IsTerminal()`, `v.RequiresAuth()` |
| Variant → grouping | Enum package | `v.IsAnyOf(...)`, `v.Category()` |
| Business logic per variant | Service layer | ✅ Permitted (calls enum methods) |

### Enforcement

- **Automated**: `grep -rn 'switch.*Variant' --include="*.go" | grep -v 'internal/enums/'` flags external enum switches
- **Manual review**: Any `switch` on an enum type outside its package must be justified or moved into the enum

---

*Required methods for enum compliance.*
