# Rollback Protection & Telemetry Specification

> **ID:** spec/06-features/rollback-protection
> **Version:** 2.15.0
> **Date:** 2026-03-15
> **Status:** Implemented

---

## Overview

Both WordPress plugins (QUpload and Riseup Asia Uploader) implement automated rollback protection during plugin uploads. When an upload fails at any phase — extraction, activation, validation, or health check — the system automatically restores the previous version from a pre-upload backup. Rollback metadata is injected into the REST error response and surfaced in the PowerShell automation console.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                        Upload Pipeline                              │
├─────────┬───────────────┬──────────────┬──────────────┬─────────────┤
│ Phase 1 │ Phase 2       │ Phase 3      │ Phase 4      │ Phase 5     │
│ Backup  │ Deactivate +  │ Activation   │ Health Check │ Cleanup     │
│ Create  │ Extract ZIP   │ + Validate   │ (self only)  │ (success)   │
└────┬────┴───────┬───────┴──────┬───────┴──────┬───────┴─────────────┘
     │            │              │              │
     │       FAIL ▼         FAIL ▼         FAIL ▼
     │   ┌─────────────────────────────────────────┐
     └──►│         ROLLBACK FLOW                    │
         │  1. Restore backup to wp-content/plugins │
         │  2. Re-activate if previously active     │
         │  3. Detect restored version              │
         │  4. Inject metadata into REST response   │
         └─────────────────────────────────────────┘
```

---

## Phase-by-Phase Behavior

### Phase 1: Pre-Upload Backup

Before replacing an existing plugin, the system creates a backup:

| Plugin | Backup Location | Helper Class |
|---|---|---|
| QUpload | `wp-content/upgrade/{slug}-backup-{timestamp}/` | `UploadBackupHelper` |
| Riseup Asia | `wp-content/upgrade/{slug}-backup-{timestamp}/` | `SelfUpdateBackupHelper` |

- **New installs**: No backup created (nothing to roll back to)
- **Updates**: Full directory copy of the existing plugin
- **Self-updates** (Riseup Asia only): Mandatory backup — fails the upload if backup creation fails

### Phase 2: Deactivation + ZIP Extraction

1. If the plugin was previously active, it is deactivated via `deactivate_plugins()`
2. The existing plugin directory is deleted
3. ZIP is extracted to a temp directory, then moved to `wp-content/plugins/{slug}/`
4. **Failure triggers rollback**

### Phase 3: Activation + Validation

1. OPcache is reset to clear stale bytecode
2. Plugin file is located via `get_plugins()`
3. Syntax validation runs (self-update only, via `SelfUpdateValidator`)
4. Plugin is activated if requested or was previously active
5. **Failure triggers rollback**

### Phase 4: Post-Activation Health Check (Self-Update Only)

Riseup Asia Uploader performs a post-activation health check via `SelfUpdateHealthCheck`:
- Verifies the plugin can boot without fatal errors
- Checks critical class availability
- **Failure**: Deactivates the broken version, then triggers rollback

### Phase 5: Cleanup

On **success**, the backup directory is deleted. On **failure**, it is preserved for forensic analysis.

---

## Version Detection

Both plugins capture the `previousVersion` before any file replacement:

```php
// QUpload — detectPreviousVersion()
wp_cache_delete('plugins', 'plugins');
$allPlugins = get_plugins();
foreach ($allPlugins as $file => $data) {
    if (dirname($file) === $slug) {
        return $data['Version'] ?? null;
    }
}

// Riseup Asia — detectInstalledVersionBySlug()
// Same pattern with plugin cache invalidation
```

After rollback, the `restoredVersion` is detected using the same method to confirm the correct version was restored.

---

## REST Response Format

### QUpload Error Response (with rollback)

```json
{
  "success": false,
  "error": "Plugin activation failed",
  "RolledBack": true,
  "PreviousVersion": "2.14.0",
  "RestoredVersion": "2.14.0"
}
```

| Key | Type | Description |
|---|---|---|
| `RolledBack` | `boolean` | Whether rollback was attempted and succeeded |
| `PreviousVersion` | `string\|null` | Version that was installed before the upload |
| `RestoredVersion` | `string\|null` | Version detected after rollback (should match PreviousVersion) |

### Riseup Asia Error Response (with rollback)

#### Non-Self Update

```json
{
  "success": false,
  "error": "Plugin activation failed",
  "RollbackSuccess": true,
  "RestoredVersion": "2.14.0"
}
```

#### Self-Update (with diagnostics)

```json
{
  "success": false,
  "error": "Self-update validation failed",
  "Results": [
    {
      "SelfUpdateStatus": "RolledBack",
      "RollbackReason": "ValidationFailed",
      "rollback": {
        "RollbackAttempted": true,
        "RollbackSuccess": true,
        "RestoredVersion": "2.14.0"
      },
      "Validation": {
        "missingFiles": [],
        "syntaxErrors": []
      }
    }
  ]
}
```

| Key | Type | Context | Description |
|---|---|---|---|
| `RollbackSuccess` | `boolean` | Non-self | Whether rollback succeeded |
| `RestoredVersion` | `string\|null` | Both | Version restored after rollback |
| `SelfUpdateStatus` | `string` | Self | Outcome: `RolledBack`, `RollbackFailed` |
| `RollbackReason` | `string` | Self | Failure phase: `ExtractionFailed`, `ValidationFailed`, `PluginFileNotFound`, `ActivationException`, `HealthCheckFailed` |
| `RollbackAttempted` | `boolean` | Self | Whether a backup was available |

### ResponseKeyType Enums

**QUpload** (`QUpload\Enums\ResponseKeyType`):
- `RolledBack = 'RolledBack'`
- `PreviousVersion = 'PreviousVersion'`
- `RestoredVersion = 'RestoredVersion'`

**Riseup Asia** (`RiseupAsia\Enums\ResponseKeyType`):
- `SelfUpdateStatus = 'SelfUpdateStatus'`
- `RollbackReason = 'RollbackReason'`
- `RollbackAttempted = 'RollbackAttempted'`
- `RollbackSuccess = 'RollbackSuccess'`
- `RestoredVersion = 'RestoredVersion'`

---

## PowerShell Console Display

Both upload scripts (`upload-plugin-U-Q.ps1` and `upload-plugin-v2.ps1`) parse rollback metadata from REST responses and display a high-visibility banner:

### Banner Format

```
      ╔══════════════════════════════════════════════╗
      ║  ROLLBACK: Previous version restored        ║
      ╚══════════════════════════════════════════════╝
      Previous Version: 2.14.0
      Restored Version: 2.14.0
```

### Detection Logic

The scripts check for rollback data in three locations (in order):

1. **`Results[0]`** — Riseup Asia self-update envelope (`Results[0].RolledBack` or `Results[0].RollbackSuccess`)
2. **Top-level keys** — QUpload direct envelope (`RolledBack`, `PreviousVersion`, `RestoredVersion`)
3. **Error response body** — Parsed from exception response stream on HTTP failures

### `Get-JsonErrorSummary` Integration

The `upload-plugin-v2.ps1` error summary function appends rollback info to the diagnostic string:

```
code=activation_failed; message=Plugin activation failed; rolledBack=true; restoredVersion=2.14.0
```

### Multi-Site Summary (`-uas`)

Failed uploads with rollback show `FAILED (exit 1)` in the summary table. Detailed rollback info is available in the per-site log file under `logs/uas-upload/`.

---

## Failure Logging

### PHP Side

Both plugins log rollback events at multiple severity levels:

| Event | Level | Message Pattern |
|---|---|---|
| Rollback initiated | `warn` | `Upload failed — initiating rollback to previous version` |
| Rollback succeeded | `info` | `Rollback complete — previous version restored` |
| Rollback + reactivation | `info` | `Rolled-back plugin re-activated` |
| Rollback failed | `error` | `Rollback FAILED — plugin may be in a broken state` |
| External plugin failure | `error` | `EXTERNAL PLUGIN FAILURE [PHASE] — ...` |

QUpload additionally writes to a **stage trace file** (`PathHelper::getStageTraceFile()`) for emergency diagnostics outside the main logger path.

### PowerShell Side

- Per-upload log files in `logs/uas-upload/` contain full REST response bodies
- Console output includes the rollback banner immediately after the failure message
- `Get-JsonErrorSummary` includes `rolledBack=true` and `restoredVersion=X.Y.Z` in the summary line

---

## Edge Cases

| Scenario | Behavior |
|---|---|
| New plugin install fails | No rollback (no previous version to restore) |
| Backup creation fails (non-self) | Upload proceeds without safety net; error logged |
| Backup creation fails (self-update) | Upload aborted immediately with `BackupCreationFailed` |
| Rollback succeeds but reactivation fails | Logged as error; plugin restored but inactive |
| `previousVersion` and `restoredVersion` mismatch | Indicates corruption; both values reported for diagnosis |
| OPcache serves stale bytecode after rollback | `opcache_reset()` called before plugin detection |

---

## File Reference

| File | Role |
|---|---|
| `wp-plugins/qupload/includes/Traits/Upload/UploadExtractTrait.php` | QUpload rollback orchestration |
| `wp-plugins/qupload/includes/Helpers/UploadBackupHelper.php` | QUpload backup create/rollback/cleanup |
| `wp-plugins/qupload/includes/Enums/ResponseKeyType.php` | QUpload response keys |
| `wp-plugins/riseup-asia-uploader/includes/Traits/Upload/UploadInstallExtractTrait.php` | Riseup Asia rollback orchestration |
| `wp-plugins/riseup-asia-uploader/includes/Update/SelfUpdateBackupHelper.php` | Riseup Asia backup create/rollback/cleanup |
| `wp-plugins/riseup-asia-uploader/includes/Update/SelfUpdateValidator.php` | Pre-activation validation (self-update) |
| `wp-plugins/riseup-asia-uploader/includes/Update/SelfUpdateHealthCheck.php` | Post-activation health check (self-update) |
| `wp-plugins/riseup-asia-uploader/includes/Enums/ResponseKeyType.php` | Riseup Asia response keys |
| `wp-plugins/riseup-asia-uploader/includes/Enums/SelfUpdateStatusType.php` | Self-update outcome enum |
| `wp-plugins/scripts/upload-plugin-U-Q.ps1` | PowerShell rollback banner (QUpload path) |
| `wp-plugins/scripts/upload-plugin-v2.ps1` | PowerShell rollback banner (V2 path) |
