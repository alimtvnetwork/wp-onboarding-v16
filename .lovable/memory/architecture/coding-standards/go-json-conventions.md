# Memory: coding-standards/go-json-conventions
Updated: 2026-02-25

## Core Rule

Go struct fields produce PascalCase JSON keys by default. **Do not add JSON tags that merely repeat or rename the field.** Tags are only permitted for `omitempty`, field exclusion (`json:"-"`), or mapping to external/third-party keys.

---

## 1. PascalCase JSON Output Keys

All JSON output from the Go backend uses Go's default PascalCase marshaling. No `json:"camelCase"` tags.

```go
// ✅ CORRECT — no tags, outputs {"PluginId":3,"SiteName":"Prod"}
type PublishEvent struct {
    PluginId int64
    SiteName string
}

// ❌ WRONG — redundant tags
type PublishEvent struct {
    PluginId int64  `json:"pluginId"`
    SiteName string `json:"siteName"`
}
```

### Permitted Tags

| Tag | When |
|-----|------|
| `json:",omitempty"` | Optional/nullable fields |
| `json:"-"` | Fields excluded from JSON |
| `json:"externalKey"` | Parsing external API responses (must have `// external key` comment) |

---

## 2. PascalCase config.json Keys

`backend/config.json` uses PascalCase keys matching Go struct field names:

```json
{
  "Server": { "Port": 8080 },
  "RemotePlugins": { "CacheEnabled": true, "CacheTTLMinutes": 60 },
  "Seed": { "Enabled": true }
}
```

No `json:"camelCase"` tags on config structs — Go unmarshals config.json case-insensitively for input, and the PascalCase keys match field names directly.

---

## 3. External Key Annotation Requirement

When a struct parses JSON from an external source (WordPress REST API, PHP responses, version.json, etc.), the tags are **required** and each tagged field **must** have an `// external key` comment:

```go
// ✅ CORRECT — external API parsing with annotation
type RemotePlugin struct {
    Plugin  string `json:"plugin"`  // external key (WordPress REST API)
    Slug    string `json:"slug"`    // external key
    Version string `json:"version"` // external key
}

// ❌ WRONG — external tags without annotation
type RemotePlugin struct {
    Plugin  string `json:"plugin"`
    Slug    string `json:"slug"`
}
```

For structs parsing our own envelope format or version.json:

```go
type Info struct {
    AppName string `json:"appName"` // external key (version.json)
    Version string `json:"version"` // external key (version.json)
}
```

---

## 4. Boolean Field Naming

Boolean fields use `Is` prefix for clarity in JSON output:

| ❌ Wrong | ✅ Correct |
|----------|-----------|
| `Success bool` | `IsSuccess bool` |
| `Enabled bool` | `IsEnabled bool` |
| `Active bool` | `IsActive bool` |

Exception: External parsing structs retain the external key's naming (e.g., WordPress returns `"success"`).

---

## 5. Frontend Alignment

The React frontend must handle PascalCase keys from API responses. All `fetch`/API client code references PascalCase field names (e.g., `response.PluginId`, not `response.pluginId`).

Go's `json.Unmarshal` is **case-insensitive** for input, so the frontend can send either PascalCase or camelCase request bodies — both will parse correctly. However, PascalCase is preferred for consistency.

---

## Prohibited Patterns

| Pattern | Replacement |
|---------|-------------|
| `` `json:"fieldName"` `` (camelCase tag on internal struct) | Remove tag entirely |
| `` `json:"FieldName"` `` (PascalCase tag matching field name) | Remove tag entirely (redundant) |
| `map[string]interface{}` | `map[string]any` |
| External parsing tag without comment | Add `// external key` comment |

---

*This convention applies to all Go files in `backend/`. The frontend must be updated to match PascalCase keys.*
