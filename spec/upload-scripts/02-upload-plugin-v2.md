# Upload Plugin V2 — Enhanced Pipeline

> **Script:** `wp-plugins/scripts/upload-plugin-v2.ps1`  
> **Lines:** 657  
> **Status:** Active (primary upload script)

---

## Purpose

Enhanced uploader that adds **Git Pull**, **version comparison**, and **smart publish** logic on top of V1. This is the primary upload script used by both `run.ps1` and `upload-plugin-v3.ps1`.

---

## Pipeline (7 Steps)

| Step | Description |
|------|-------------|
| 1/7 | **Git Pull** — Auto-detect `.git` root (up to 10 levels), pull current branch |
| 2/7 | **Read local version** — Parse from `includes/constants.php` (`RISEUP_VERSION`) or plugin header |
| 3/7 | **Get remote version** — Query `/status` endpoint via Riseup Asia Uploader API |
| 4/7 | **Version comparison** — Determine action: upgrade, downgrade, reinstall, or fresh install |
| 5/7 | **Create ZIP** — Package plugin with proper directory structure |
| 6/7 | **REST API health check** — Verify REST API reachability and namespace availability |
| 7/7 | **Publish** — Upload via Riseup Asia Uploader API with envelope unwrapping |

---

## Additional Parameters (vs V1)

| Parameter | Type | Description |
|-----------|------|-------------|
| `-SkipGitPull` | Switch | Skip the git pull step |

All V1 parameters are also supported.

---

## Version Detection

### Local Version

1. **Primary:** Parse `RISEUP_VERSION` constant from `includes/constants.php`
2. **Fallback:** Parse `Version:` header from main `.php` file

### Remote Version

Query status endpoint with envelope unwrapping:

```powershell
$statusResponse = Invoke-RestMethod -Uri "$SiteUrl/wp-json/riseup-asia-uploader/v1/status"
# Unwrap envelope
if ($statusResponse.Results -and $statusResponse.Results.Count -gt 0) {
    $statusData = $statusResponse.Results[0]
}
# Support both PascalCase (envelope) and lowercase (legacy)
$detectedVersion = if ($statusData.Version) { $statusData.Version } 
                   elseif ($statusData.version) { $statusData.version }
```

---

## Version Comparison Logic

```
┌─────────────────────────────────────────────┐
│  Local > Remote  →  ▲ UPGRADE               │
│  Local < Remote  →  ▼ DOWNGRADE             │
│  Local = Remote  →  ═ REINSTALL              │
│  Remote missing  →  ★ FRESH INSTALL          │
└─────────────────────────────────────────────┘
```

Comparison is numeric, splitting on `.` and comparing each segment.

---

## Upload & Envelope Handling

### Request Body

```json
{
  "plugin_zip": "<base64>",
  "slug": "my-plugin",
  "activate": true,
  "upload_source": "upload_script"
}
```

**Note:** V2 includes `upload_source: "upload_script"` for audit log attribution (see `spec/wordpress-plugin-development/`).

### Response Unwrapping

```powershell
$resultData = $response
if ($response.Results -and $response.Results.Count -gt 0) {
    $resultData = $response.Results[0]
}
```

### Success Output

```
===============================================
  ✓ PUBLISH COMPLETE!
===============================================

  Plugin:     my-plugin
  Version:    1.2.3
  Action:     upgrade
  Is Update:  True
  Activated:  True
```

---

## Fallback Mechanism

If the Riseup Asia Uploader API fails, V2 falls back to V1:

```powershell
$basicScript = Join-Path $ScriptDir "upload-plugin.ps1"
& $basicScript -JsonConfig $fallbackConfig
```

This ensures deployment succeeds even if the companion plugin has issues.

---

## Quiet Mode Output

When `-Quiet` is set, only a JSON result is written to stdout:

```json
{
  "success": true,
  "plugin": "my-plugin",
  "localVersion": "1.2.3",
  "remoteVersion": "1.2.0",
  "action": "upgrade",
  "activated": true
}
```

This enables machine-readable output for V3 parallel job processing.

---

## Exit Codes

| Code | Meaning |
|------|---------|
| 0 | Success |
| 1 | Missing parameters, plugin not found, ZIP failed, all upload methods failed |

---

*V2 specification created: 2026-02-09*
