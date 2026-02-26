# Memory: coding-standards/go-json-conventions
Updated: 2026-02-26

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

## 3. Internal vs External Key Classification

### Internal APIs (NO JSON tags)

The **Riseup Asia Uploader PHP plugin** is our own codebase. Its REST API responses use PascalCase keys (via `ResponseKeyType` enum). Go structs parsing these responses must use **matching PascalCase field names with no JSON tags** — they are NOT external keys.

```go
// ✅ CORRECT — our own PHP plugin API, PascalCase matches ResponseKeyType
type SnapshotCleanupResult struct {
    IsSuccess bool
    Retention CleanupRetentionResult
    Orphans   CleanupOrphansResult
    Duration  float64
    Errors    []string
}

// ❌ WRONG — treating our own API as external with snake_case tags
type SnapshotCleanupResult struct {
    Success        bool `json:"success"`                   // NOT external!
    OrphansCleaned int  `json:"orphans_cleaned,omitempty"` // NOT external!
}
```

### External APIs (JSON tags required)

Only truly external APIs (WordPress Core REST API, third-party services, version.json) use `// external key` annotations:

```go
// ✅ CORRECT — WordPress Core REST API is genuinely external
type RemotePlugin struct {
    Plugin  string `json:"plugin"`  // external key (WordPress REST API)
    Slug    string `json:"slug"`    // external key
    Version string `json:"version"` // external key
}
```

### Classification Guide

| Source | Classification | JSON Tags? |
|--------|---------------|------------|
| Riseup Asia Uploader PHP API | **Internal** | No tags — PascalCase fields match |
| WordPress Core REST API (`/wp/v2/`) | **External** | Required + `// external key` |
| Third-party APIs | **External** | Required + `// external key` |
| `version.json`, `config.json` | **External** | Required + `// external key` |

### External Key Annotation Requirement

When a struct parses JSON from an **external** source, the tags are **required** and each tagged field **must** have an `// external key` comment:

```go
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
| Riseup Asia Uploader API tagged as `// external key` | Remove tags — it's internal, use PascalCase fields |
| `json:"snake_case"` on Riseup Asia Uploader response struct | Remove tag, rename field to PascalCase |

---

*This convention applies to all Go files in `backend/`. The frontend must be updated to match PascalCase keys.*
