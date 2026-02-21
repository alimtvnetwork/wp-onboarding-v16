# Required Methods

**Version:** 2.0.0  
**Status:** Complete  
**Updated:** 2026-02-11

---

## Mandatory Methods

Every enum MUST implement these methods:

---

### 1. String() string

Returns the lowercase string representation for serialization and logging.

```go
func (v Variant) String() string {
    if !v.IsValid() {
        return "invalid"
    }
    return variantStrings[v]
}
```

---

### 2. Label() string

Returns a human-readable label for UI display.

```go
func (v Variant) Label() string {
    if !v.IsValid() {
        return "Invalid"
    }
    return variantLabels[v]
}
```

---

### 3. Is{Value}() bool

One method per variant for type checking. Enables clean conditional logic.

```go
func (v Variant) IsSerpAPI() bool {
    return v == SerpAPI
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
if provider.IsSerpAPI() {
    // ...
}

// ❌ Verbose
if provider == provider.SerpAPI {
    // ...
}
```

---

### 4. All() []Variant

Returns all valid variants (excludes Invalid).

```go
func All() []Variant {
    return []Variant{
        SerpAPI,
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
    if i < 0 || i >= len(variantStrings) {
        return Invalid
    }
    return Variant(i)
}
```

**Usage:**
```go
p := provider.ByIndex(1) // Returns SerpAPI
```

---

### 6. Parse(s string) (Variant, error)

Parses a string to variant. Case-insensitive.

```go
func Parse(s string) (Variant, error) {
    lower := strings.ToLower(strings.TrimSpace(s))
    for i, str := range variantStrings {
        if str == lower {
            return Variant(i), nil
        }
    }
    return Invalid, fmt.Errorf("invalid provider: %q", s)
}
```

**Usage:**
```go
p, err := provider.Parse("serpapi")
if err != nil {
    return err
}
```

---

### 7. IsValid() bool

Checks if the variant is a valid, non-Invalid value.

```go
func (v Variant) IsValid() bool {
    return v > Invalid && v < Variant(len(variantStrings))
}
```

**Usage:**
```go
if !p.IsValid() {
    return errors.New("invalid provider")
}
```

---

### 8. MarshalJSON() ([]byte, error)

**Mandatory.** Serializes the enum as its string representation for JSON output.

```go
func (v Variant) MarshalJSON() ([]byte, error) {
    return json.Marshal(v.String())
}
```

---

### 9. UnmarshalJSON(data []byte) error

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
    result := make([]string, 0, len(variantStrings)-1)
    for _, s := range variantStrings[1:] { // Skip Invalid
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

// BaseURL returns the platform's base URL
func (v Variant) BaseURL() string {
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
// RequiresAPIKey returns true if the provider needs an API key
func (v Variant) RequiresAPIKey() bool {
    switch v {
    case SerpAPI:
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
    case SerpAPI:
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
    SerpAPI
    MapsScraper
    Colly
)

var variantStrings = [...]string{
    Invalid:     "invalid",
    SerpAPI:     "serpapi",
    MapsScraper: "maps_scraper",
    Colly:       "colly",
}

var variantLabels = [...]string{
    Invalid:     "Invalid Provider",
    SerpAPI:     "SerpAPI",
    MapsScraper: "Google Maps Scraper",
    Colly:       "Colly Web Scraper",
}

func (v Variant) String() string {
    if !v.IsValid() {
        return variantStrings[Invalid]
    }
    return variantStrings[v]
}

func (v Variant) Label() string {
    if !v.IsValid() {
        return variantLabels[Invalid]
    }
    return variantLabels[v]
}

func (v Variant) IsValid() bool {
    return v > Invalid && v < Variant(len(variantStrings))
}

func (v Variant) IsSerpAPI() bool     { return v == SerpAPI }
func (v Variant) IsMapsScraper() bool { return v == MapsScraper }
func (v Variant) IsColly() bool       { return v == Colly }
func (v Variant) IsInvalid() bool     { return v == Invalid }

func All() []Variant {
    return []Variant{SerpAPI, MapsScraper, Colly}
}

func ByIndex(i int) Variant {
    if i < 0 || i >= len(variantStrings) {
        return Invalid
    }
    return Variant(i)
}

func Parse(s string) (Variant, error) {
    lower := strings.ToLower(strings.TrimSpace(s))
    for i, str := range variantStrings {
        if str == lower {
            return Variant(i), nil
        }
    }
    return Invalid, fmt.Errorf("invalid provider: %q", s)
}

func Values() []string {
    result := make([]string, 0, len(variantStrings)-1)
    for _, s := range variantStrings[1:] {
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

*Required methods for enum compliance.*
