# Unused PHP Enum Cases Audit

> **Created:** 2026-02-25  
> **Updated:** 2026-02-25  
> **Status:** ✅ Resolved — 17 unused cases removed  

---

## Methodology

Searched all `.php` files under `wp-plugins/` for direct references to every enum case declared in each `Enums/*.php` file. Cases referenced **only** in their own declaration (or only via domain-helper methods like `isRetryable()`) are flagged below.

---

## Confirmed Unused Cases

### ActionType (6 cases)

| Case | Notes |
|------|-------|
| `UpdateCheck` | Never referenced anywhere. Likely planned for future auto-update logging. |
| `UpdateResolve` | Never referenced anywhere. |
| `UpdateDownload` | Never referenced anywhere. |
| `UpdateInstall` | Never referenced anywhere. |
| `SnapshotRestorePerTable` | Never referenced anywhere. Per-table restore uses `SnapshotRestore` instead. |
| `SnapshotImportPerTable` | Never referenced anywhere. Per-table import uses `SnapshotImport` instead. |

### ActionType — Label-Only (3 cases)

These appear **only** in `AdminPagesTrait::getActionLabels()` as display labels but are never used as actual action identifiers in any logging call:

| Case | Notes |
|------|-------|
| `AgentPluginEnable` | Label mapped but never logged. Agent actions use `AgentAction` or direct API calls. |
| `AgentPluginDisable` | Label mapped but never logged. |
| `AgentPluginDelete` | Label mapped but never logged. |
| `AgentPluginUpdate` | Not even in label map. Never referenced anywhere. |

### ResponseMessageType (5 cases)

| Case | Notes |
|------|-------|
| `PostCreateFailed` | Never referenced. Post errors use inline messages instead. |
| `PostUpdateFailed` | Never referenced. |
| `CategoryCreateFailed` | Never referenced. |
| `MediaUploadFailed` | Never referenced. |
| `DbError` | Never referenced. DB errors use specific messages. |
| `ServiceNotAvailable` | Never referenced. |
| `InvalidId` | Never referenced. |

### HttpStatusType — Intentionally Available (12 cases)

These are **not directly referenced** but exist as standard HTTP status codes used via `isRetryable()`, `isRedirect()`, `isClientError()`, and `isServerError()` domain helpers. **Recommend keeping** — they're part of the HTTP status vocabulary:

| Cases |
|-------|
| `NoContent`, `MovedPermanently`, `Found`, `SeeOther`, `TemporaryRedirect`, `PermanentRedirect`, `RequestTimeout`, `Conflict`, `TooManyRequests`, `NotImplemented`, `BadGateway`, `ServiceUnavailable`, `GatewayTimeout` |

---

## Recommendation

### Safe to Remove (10 cases)
- `ActionType::UpdateCheck`, `UpdateResolve`, `UpdateDownload`, `UpdateInstall`
- `ActionType::SnapshotRestorePerTable`, `SnapshotImportPerTable`
- `ActionType::AgentPluginUpdate`
- `ResponseMessageType::PostCreateFailed`, `PostUpdateFailed`, `CategoryCreateFailed`

### Keep but Monitor (7 cases)
- `ActionType::AgentPluginEnable`, `AgentPluginDisable`, `AgentPluginDelete` — may be needed when agent plugin lifecycle logging is implemented
- `ResponseMessageType::MediaUploadFailed`, `DbError`, `ServiceNotAvailable`, `InvalidId` — generic error messages that may be useful in future error handling

### Keep (12 HttpStatusType cases)
- All redirect and server-error HTTP codes — standard vocabulary, used via domain helpers

---

*Unused PHP enum cases audit v1.0.0 — 2026-02-25*
