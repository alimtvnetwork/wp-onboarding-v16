# Undefined PHP Enum Cases — Runtime Fatal Errors

> **Created:** 2026-03-21  
> **Status:** ✅ Resolved  

---

## Root Cause

Two `ResponseMessageType` enum cases were referenced in code but **never declared** in the enum definition, causing PHP fatal errors (`Undefined constant`) at runtime when those code paths were hit.

| Missing Case | Referenced In | Error |
|---|---|---|
| `MissingPluginSlug` | `wp-plugins/riseup-asia-uploader/includes/Traits/Plugin/PluginLifecycleHelpersTrait.php:51,79` | HTTP 500 on `POST /plugins/enable` — `Undefined constant RiseupAsia\Enums\ResponseMessageType::MissingPluginSlug` |
| `SelfActionProhibited` | `wp-plugins/riseup-asia-uploader/includes/Traits/Plugin/PluginLifecycleHelpersTrait.php:86` | Would trigger HTTP 500 when attempting to enable/disable/delete the managing plugin itself |

### Why It Happened

The enum cases were consumed in `PluginLifecycleHelpersTrait` (added in v2.0.0) but were never added to the `ResponseMessageType` enum definition. PHP enums are strict — referencing an undeclared case is a fatal error, not a warning.

---

## Fix

Added both missing cases to the enum definition:

**File:** `wp-plugins/riseup-asia-uploader/includes/Enums/ResponseMessageType.php`

```php
case MissingPluginSlug    = 'Missing required plugin slug parameter';
case SelfActionProhibited = 'Cannot perform this action on the managing plugin itself';
```

---

## Audit Results

A full audit of all `ResponseMessageType::*` usages across both PHP plugins confirmed **20 unique cases referenced**, all now defined:

| Case | Defined | Used In |
|---|---|---|
| `Success` | ✅ | Multiple traits |
| `Unauthorized` | ✅ | `AdminFeedbackAjaxTrait` |
| `Forbidden` | ✅ | (available) |
| `InvalidRequest` | ✅ | `UploadParserTrait` |
| `PluginNotFound` | ✅ | `PluginLifecycleHelpersTrait`, `SyncPushTrait` |
| `UploadFailed` | ✅ | `UploadZipTrait` |
| `ActivationFailed` | ✅ | `PluginLifecycleEnableTrait` |
| `DeactivationFailed` | ✅ | `PluginLifecycleEnableTrait` |
| `DeleteFailed` | ✅ | `PluginLifecycleDeleteTrait` |
| `FileIgnored` | ✅ | `SyncPushTrait` |
| `InvalidRequestBody` | ✅ | (available) |
| `ConnectionSuccessful` | ✅ | `UpdateResolverWpHooksTrait` |
| `SnapshotNotFound` | ✅ | `SnapshotCrudListTrait` |
| `SnapshotProviderMissing` | ✅ | `SnapshotCrudListTrait`, `ManagerCoreTrait` |
| `ProviderMissing` | ✅ | `ManagerCoreTrait`, `ManagerTableRestoreTrait` |
| `SnapshotFileMissing` | ✅ | (available) |
| `UploadedFileMissing` | ✅ | (available) |
| `ZipCreateFailed` | ✅ | (available) |
| `TempDirCreateFailed` | ✅ | (available) |
| `InvalidFileTypeZip` | ✅ | (available) |
| `MissingPluginSlug` | ✅ | `PluginLifecycleHelpersTrait:51,79` |
| `SelfActionProhibited` | ✅ | `PluginLifecycleHelpersTrait:86` |

No other undefined cases remain.

---

## Prevention

- Run `grep -rn 'ResponseMessageType::' --include='*.php' | grep -oP 'ResponseMessageType::\w+' | sort -u` after adding new enum references
- Cross-reference with the enum definition file before deploying

---

*Undefined PHP enum cases audit — 2026-03-21*
