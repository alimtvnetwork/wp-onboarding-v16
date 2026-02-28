# Folder Structure

**Version:** 2.1.0  
**Status:** Complete  
**Updated:** 2026-02-25

---

## Standard Layout

All enums MUST be placed in the `internal/enums/` directory at the project root. Each enum package MUST use the `type` suffix (lowercase, no underscores).

```
{cli-root}/
├── cmd/
│   └── root.go
├── internal/
│   ├── enums/
│   │   ├── providertype/
│   │   │   └── Variant.go
│   │   ├── platformtype/
│   │   │   └── Variant.go
│   │   ├── enginetype/
│   │   │   └── Variant.go
│   │   ├── searchmodetype/
│   │   │   └── Variant.go
│   │   ├── outputformattype/
│   │   │   └── Variant.go
│   │   └── registry.go       # Optional: Central registry
│   ├── models/
│   ├── services/
│   └── api/
└── pkg/
```

---

## Naming Conventions

### Package Names — `type` Suffix (Mandatory)

All Go enum packages MUST end with the `type` suffix (lowercase, no underscores). This is the **convention-over-configuration** rule: by looking at the package name alone, anyone can immediately tell it is an enum package.

- **Go packages:** lowercase, no underscores, `type` suffix (e.g., `httpmethodtype`, `statustype`)
- **PHP enums:** PascalCase, `Type` suffix (e.g., `HttpMethodType`, `StatusType`)
- One enum per package

| ✅ Correct | ❌ Wrong | Reason |
|-----------|----------|--------|
| `statustype` | `status` | Missing `type` suffix |
| `httpmethodtype` | `http_method` | Underscore + missing suffix |
| `logleveltype` | `log_level` | Underscore + missing suffix |
| `backuptype` | `backup_type` | Underscore forbidden |
| `endpointtype` | `endpoint` | Missing `type` suffix |
| `actiontype` | `action` | Missing `type` suffix |

### File Names

- Always name the file `variant.go`
- Additional helper files allowed: `helpers.go`, `validation.go`

```
internal/enums/providertype/
├── Variant.go      # Main enum definition
├── Helpers.go      # Optional: Helper functions
└── Validation.go   # Optional: Validation logic
```

---

## Import Pattern

```go
import (
    "myapp/internal/enums/providertype"
    "myapp/internal/enums/platformtype"
    "myapp/internal/enums/enginetype"
)

func main() {
    p := providertype.SerpApi
    if p.IsSerpApi() {
        // ...
    }
    
    platforms := []platformtype.Variant{
        platformtype.YouTube,
        platformtype.Reddit,
    }
}
```

---

## Enum Categories by CLI

### GSearch CLI

```
internal/enums/
├── providertype/        # SerpApi, MapsScraper, Colly
├── platformtype/        # YouTube, Reddit, LinkedIn, etc.
├── enginetype/          # Google, Bing, DuckDuckGo
├── searchmodetype/      # Sequential, Parallel, RoundRobin
├── outputformattype/    # Json, Csv, Table, Markdown
├── movieprovidertype/   # Tmdb, Omdb, Trakt, ImdbScraper
├── socialmediatype/     # LinkedIn, Twitter, Instagram, etc.
└── contenttype/         # Web, Image, Video, News
```

### BRun CLI

```
internal/enums/
├── buildtype/          # Debug, Release, Test
├── runmodetype/        # Foreground, Background, Watch
├── logleveltype/       # Debug, Info, Warn, Error
└── profiletype/        # Development, Staging, Production
```

### AI Bridge CLI

```
internal/enums/
├── modelprovidertype/  # Ollama, OpenAi, Anthropic
├── reasoningmodetype/  # SinglePrompt, TwoStage, Research
├── steptype/           # Search, Fetch, Parse, Embed, etc.
├── executionstatustype/ # Pending, Running, Success, Failed
├── checkpointtype/     # Auto, Manual, Rollback
└── memoryflagtype/     # IsCritical, IsImportant, Standard
```

### Nexus Flow CLI

```
internal/enums/
├── nodetype/           # Start, End, Task, Decision, Fork, Join
├── flowstatustype/     # Draft, Active, Paused, Completed
├── triggertype/        # Manual, Scheduled, Webhook, Event
└── executionmodetype/  # Sequential, Parallel
```

### Spec Reverse CLI

```
internal/enums/
├── outputformattype/   # Markdown, Json, Yaml
├── parsertype/         # Go, TypeScript, Python
└── extractionmodetype/ # Full, Summary, Skeleton
```

### WP SEO Publish CLI

```
internal/enums/
├── contenttype/        # Post, Page, Product
├── publishstatustype/  # Draft, Pending, Published
├── seoscoretype/       # Poor, Fair, Good, Excellent
└── mediatype/          # Image, Video, Document
```

### AI Transcribe CLI

```
internal/enums/
├── audioformattype/         # Mp3, Wav, Flac, Ogg
├── transcribeprovidertype/  # Whisper, DeepGram, AssemblyAi
├── outputformattype/        # Srt, Vtt, Txt, Json
└── languagetype/            # En, Es, Fr, De, etc.
```

---

## Registry Pattern (Optional)

For CLIs with many enums, create a central registry:

```go
// internal/enums/registry.go
package enums

import (
    "myapp/internal/enums/providertype"
    "myapp/internal/enums/platformtype"
    "myapp/internal/enums/enginetype"
)

// Re-export for convenience
type (
    Provider   = providertype.Variant
    Platform   = platformtype.Variant
    Engine     = enginetype.Variant
)

// Constants re-export
const (
    ProviderSerpApi     = providertype.SerpApi
    ProviderMapsScraper = providertype.MapsScraper
    ProviderColly       = providertype.Colly
    
    PlatformYouTube     = platformtype.YouTube
    PlatformReddit      = platformtype.Reddit
    
    EngineGoogle        = enginetype.Google
    EngineBing          = enginetype.Bing
)
```

**Usage:**
```go
import "myapp/internal/enums"

p := enums.ProviderSerpApi
```

---

## File Template

```go
// internal/enums/{categorytype}/Variant.go
package {categorytype}

import (
    "encoding/json"
    "fmt"
    "strings"
)

// Variant represents a {category} type
type Variant byte

const (
    // Invalid is the zero value
    Invalid Variant = iota
    
    // Add variants here...
)

var variantLabels = [...]string{
    Invalid: "invalid",
    // Add mappings...
}

// String returns the string representation
func (v Variant) String() string {
    if !v.IsValid() {
        return variantLabels[Invalid]
    }
    return variantLabels[v]
}

// Label delegates to String
func (v Variant) Label() string {
    return v.String()
}

// IsValid checks if the variant is valid
func (v Variant) IsValid() bool {
    return v > Invalid && v < Variant(len(variantLabels))
}

// Add Is{Value}() methods for each variant...

// All returns all valid variants
func All() []Variant {
    // Return all except Invalid
}

// ByIndex returns variant by index
func ByIndex(i int) Variant {
    if i < 0 || i >= len(variantLabels) {
        return Invalid
    }
    return Variant(i)
}

// Parse parses a string to variant
func Parse(s string) (Variant, error) {
    lower := strings.ToLower(strings.TrimSpace(s))
    for i, str := range variantLabels {
        if str == lower {
            return Variant(i), nil
        }
    }
    return Invalid, fmt.Errorf("invalid {category}: %q", s)
}

// Values returns all string values
func Values() []string {
    result := make([]string, 0, len(variantLabels)-1)
    for _, s := range variantLabels[1:] {
        result = append(result, s)
    }
    return result
}

// MarshalJSON implements json.Marshaler
func (v Variant) MarshalJSON() ([]byte, error) {
    return json.Marshal(v.String())
}

// UnmarshalJSON implements json.Unmarshaler
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

*Folder structure standard for enum organization.*
