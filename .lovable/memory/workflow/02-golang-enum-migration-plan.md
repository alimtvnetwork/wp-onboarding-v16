# Golang Enum Migration Plan

> **Version:** 1.0.0  
> **Created:** 2026-02-14  
> **Status:** Planned  
> **Depends on:** PHP enum `isEqual()` pattern (completed)

---

## Objective

Migrate `backend/internal/wordpress/constants.go` from flat `const` string groups to typed string enums with `IsEqual()` methods, mirroring the PHP `isEqual()` pattern for cross-language consistency.

---

## Current State

`constants.go` contains **~80 flat string constants** organized in `const()` blocks:

| Domain | Constants | Example |
|--------|-----------|---------|
| Endpoints (Core) | 21 | `EndpointStatus = "/status"` |
| Endpoints (Snapshot) | 14 | `EndpointSnapshotsList = "/snapshots/list"` |
| Actions | 16 | `ActionUpload = "upload"` |
| Statuses | 2 | `StatusSuccess = "success"` |
| Post Statuses | 3 | `PostStatusPublish = "publish"` |
| Upload Sources | 4 | `UploadSourceScript = "upload_script"` |
| HTTP Headers | 5 | `HeaderAuthorization = "Authorization"` |
| Content Types | 3 | `ContentTypeJSON = "application/json"` |
| Error Messages | 6 | `ErrMsgUnauthorized = "..."` |
| Plugin Statuses | 2 | `PluginStatusActive = "active"` |
| WP Core Endpoints | 6 | `WPCoreAPIRoot = "/wp-json"` |
| Namespaces | 4 | `RiseupAsiaNamespace = "riseup-asia-uploader/v1"` |
| Defaults | 2 | `DefaultLimit = 50` |

---

## Target Architecture

### Step 1 — Create typed string enums with `IsEqual()`

Each domain gets its own type and an `IsEqual()` receiver:

```go
// endpoint_type.go
package wordpress

type EndpointType string

const (
    EndpointStatus       EndpointType = "/status"
    EndpointUpload       EndpointType = "/upload"
    EndpointPlugins      EndpointType = "/plugins"
    // ... all endpoint constants
)

func (e EndpointType) IsEqual(other EndpointType) bool {
    return e == other
}

func (e EndpointType) String() string {
    return string(e)
}
```

### Step 2 — Files to create

| File | Type | Constants Count | Has IsEqual |
|------|------|----------------|-------------|
| `endpoint_type.go` | `EndpointType` | 35 | ✅ |
| `action_type.go` | `ActionType` | 16 | ✅ |
| `status_type.go` | `StatusType` | 2 | ✅ |
| `post_status_type.go` | `PostStatusType` | 3 | ✅ |
| `upload_source_type.go` | `UploadSourceType` | 4 | ✅ |
| `header_type.go` | `HeaderType` | 5 | ✅ |
| `content_type.go` | `ContentType` | 3 | ✅ |
| `plugin_status_type.go` | `PluginStatusType` | 2 | ✅ |

### Step 3 — Constants that stay as plain `const`

These do NOT qualify as enums (not discrete "which one?" sets):

| Group | Reason |
|-------|--------|
| Error messages (`ErrMsg*`) | Display strings, not discrete identifiers |
| Namespaces | Configuration values, not switchable |
| WP Core endpoints | External API paths, not our domain |
| Default values (`DefaultLimit`, `MaxLimit`) | Numeric config |
| `UploadIgnoreFilename` | Single config value |

### Step 4 — Update all callers

Search for every usage of the old untyped constants and update function signatures to accept the typed enum. Key areas:

- `internal/wordpress/client.go` — API call methods
- `internal/services/publish/` — Upload and sync services
- `internal/services/snapshot/` — Snapshot service
- Request/response structs that store action or status values

### Step 5 — Add domain helpers (matching PHP)

```go
func (a ActionType) IsSnapshot() bool {
    return strings.HasPrefix(string(a), "snapshot_")
}

func (a ActionType) IsAgent() bool {
    return strings.HasPrefix(string(a), "agent_")
}

func (e EndpointType) IsSnapshot() bool {
    return strings.HasPrefix(string(e), "/snapshots/")
}
```

---

## Migration Order

1. **Create type files** — No breaking changes, new types coexist with old constants
2. **Update constants to typed** — Change `const EndpointStatus = "/status"` to `const EndpointStatus EndpointType = "/status"`
3. **Update function signatures** — Accept typed parameters instead of `string`
4. **Add `IsEqual()` and domain helpers** — Complete the typed API
5. **Delete old `constants.go`** — Split into domain files, remove monolithic file

---

## Cross-Language Alignment

| Concept | PHP | Go |
|---------|-----|-----|
| Type definition | `enum ActionType: string` | `type ActionType string` |
| Case/constant | `case Upload = 'upload'` | `const ActionUpload ActionType = "upload"` |
| Equality check | `$action->isEqual(ActionType::Upload)` | `action.IsEqual(ActionUpload)` |
| Value access | `$action->value` | `string(action)` or `action.String()` |
| Domain check | `$action->isSnapshot()` | `action.IsSnapshot()` |

---

*Golang enum migration plan v1.0.0 — 2026-02-14*
