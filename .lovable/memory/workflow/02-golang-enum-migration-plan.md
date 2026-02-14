# Golang Enum Migration Plan

> **Version:** 1.1.0  
> **Created:** 2026-02-14  
> **Updated:** 2026-02-14  
> **Status:** In Progress  
> **Depends on:** PHP enum `isEqual()` pattern (completed)

---

## Objective

Migrate `backend/internal/wordpress/constants.go` from flat `const` string groups to typed string enums with `IsEqual()` methods, mirroring the PHP `isEqual()` pattern for cross-language consistency.

---

## Migration Progress

### ✅ Completed

| File | Type | Constants | IsEqual | Domain Helpers |
|------|------|-----------|---------|----------------|
| `endpoint_type.go` | `EndpointType` | 35 | ✅ | `IsSnapshot()` |
| `action_type.go` | `ActionType` | 16 | ✅ | `IsLifecycle()` |
| `status_type.go` | `StatusType` | 2 | ✅ | `IsSuccess()`, `IsFailed()` |
| `post_status_type.go` | `PostStatusType` | 3 | ✅ | — |
| `upload_source_type.go` | `UploadSourceType` | 4 | ✅ | — |
| `header_type.go` | `HeaderType` | 5 | ✅ | — |
| `content_type.go` | `ContentTypeValue` | 3 | ✅ | — |
| `plugin_status_type.go` | `PluginStatusType` | 2 | ✅ | `IsActive()` |

### Caller Updates Completed

- `client.go` — `setStandardHeaders` updated to use `.String()` for all header/content type constants
- `uploader.go` — `UploadPluginViaUploader` signature changed to accept `UploadSourceType`; all `ContentTypeJSON` calls use `.String()`
- `endpoint_map.go` — `WPEndpointRoute` uses `EndpointType`
- `snapshots.go` — `snapshotEndpoint()` accepts `EndpointType`

---

## Remaining (Non-Enum Constants)

These stay as plain `const` in `constants.go` — they do not qualify as enums:

| Group | Reason |
|-------|--------|
| Error messages (`ErrMsg*`) | Display strings, not discrete identifiers |
| Namespaces (`*Namespace`) | Configuration values, not switchable |
| WP Core endpoints (`WPCore*`) | External API paths, not our domain |
| Default values (`DefaultLimit`, `MaxLimit`) | Numeric config |
| `UploadIgnoreFilename` | Single config value |

---

## Pending Work

### Step 4 — Audit remaining callers for typed signatures

Search all Go callers for remaining usage of `StatusSuccess`, `StatusFailed`, `PluginStatusActive`, `PostStatusPublish`, etc. and update function signatures to accept the typed enums instead of `string` where appropriate.

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

## Cross-Language Alignment

| Concept | PHP | Go |
|---------|-----|-----|
| Type definition | `enum ActionType: string` | `type ActionType string` |
| Case/constant | `case Upload = 'upload'` | `const ActionUpload ActionType = "upload"` |
| Equality check | `$action->isEqual(ActionType::Upload)` | `action.IsEqual(ActionUpload)` |
| Value access | `$action->value` | `string(action)` or `action.String()` |
| Domain check | `$action->isSnapshot()` | `action.IsSnapshot()` |

---

*Golang enum migration plan v1.1.0 — 2026-02-14*
