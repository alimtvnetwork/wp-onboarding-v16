# Upload Plugin V2 — Enhanced Pipeline

> **Script:** `wp-plugins/scripts/upload-plugin-v2.ps1`  
> **Version:** 2.1.0  
> **Updated:** 2026-02-10  
> **Status:** Active (primary upload script)

---

## Purpose

Enhanced uploader that adds **Git Pull**, **version comparison**, **smart publish**, **self-update OPcache flush**, and **retry with security detection** on top of V1. This is the primary upload script used by both `run.ps1` and `upload-plugin-v3.ps1`.

---

## Changelog

| Version | Date | Changes |
|---------|------|---------|
| 2.1.0 | 2026-02-10 | Self-update OPcache flush via `opcache-reset.php`, client-version priority for self-updates, `Accept: application/json` header, Imunify360 detection, ZIP staging with progress + SmallestSize compression, full cache path display |
| 2.0.0 | 2026-02-09 | Initial V2: Git pull, version comparison, smart publish, ZIP hash caching, V1 fallback, quiet mode, debug mode, retry mechanism |

---

## Pipeline (8 Steps)

| Step | Description |
|------|-------------|
| 1/8 | **Git Pull** — Auto-detect `.git` root (up to 10 levels), pull current branch |
| 2/8 | **Read local version** — Parse from `includes/constants.php` (`RISEUP_VERSION`) or plugin header |
| 3/8 | **Get remote version** — Query `/status` endpoint via Riseup Asia Uploader API |
| 4/8 | **Version comparison** — Determine action: upgrade, downgrade, reinstall, or fresh install |
| 5/8 | **Create ZIP** — Stage files with progress, compress with `SmallestSize`, display full paths and ratio |
| 6/8 | **REST API health check** — Verify REST API reachability and namespace availability |
| 7/8 | **Publish** — Upload via Riseup Asia Uploader API with envelope unwrapping |
| 8/8 | **Post-upload verify** — Self-update: flush OPcache + verify; Other: check `/status` version |

---

## Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `-PluginPath` | String | Path to plugin folder |
| `-SiteUrl` | String | WordPress site URL |
| `-User` | String | WordPress username |
| `-Password` | String | Application password |
| `-Slug` | String | Plugin slug override |
| `-Activate` | Switch | Activate after install |
| `-DeleteZip` | Switch | Delete ZIP after upload |
| `-SkipGitPull` | Switch | Skip the git pull step |
| `-Quiet` | Switch | JSON-only output for machine parsing |
| `-DebugMode` | Switch | Verbose request/response logging |
| `-JsonConfig` | String | Inline JSON config string |
| `-ConfigPath` | String | Path to JSON config file |

All V1 parameters are also supported.

---

## Version Detection

### Local Version

1. **Primary:** Parse `RISEUP_VERSION` constant from `includes/constants.php`
2. **Fallback:** Parse `Version:` header from main `.php` file

### Remote Version

Query status endpoint with envelope unwrapping:

```powershell
$statusResponse = Invoke-SafeRestRequest -Uri "$SiteUrl/wp-json/riseup-asia-uploader/v1/status" ...
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

## Request Reliability

### Headers

All requests include `Accept: application/json` to prevent servers from returning HTML.

### Retry Mechanism

`Invoke-SafeRestRequest` wraps all HTTP calls with:
- Configurable retry count (default 3) with delays
- HTML challenge detection (Imunify360, SpeedyCache, Cloudflare)
- Bot-protection block detection with actionable error messages
- Server error response extraction with stack trace rendering

### Security Block Detection

When a JSON response contains `"Access denied"` or `"bot-protection"`, the script:
1. Displays a formatted warning with cPanel whitelist instructions
2. Throws an error (does NOT falsely report success)

---

## ZIP Creation (Step 5)

### Staging & Compression

1. Copy files to `$env:TEMP\wp-plugin-upload-{random}\{slug}\` with progress display
2. Compress using `[System.IO.Compression.CompressionLevel]::SmallestSize`
3. Display full output path, cache directory, file count, and compression ratio

### Output Example

```
[5/8] Creating ZIP file...
      Copying 47 files to staging...
      Staging: 47/47 files (100%)
      Compressing (SmallestSize)... done
      ✓ ZIP created: 205.58 KB (from 1011.68 KB source, 20.3% ratio)
      Path: C:\Users\Alim\AppData\Local\Temp\RiseupUploader\1.45.0\plugin-20260211.zip
      Cache: C:\Users\Alim\AppData\Local\Temp\RiseupUploader\1.45.0
      Files: 47
      Hash: F25C936D...
```

### Hash Caching

ZIP files are cached by version in `%TEMP%\RiseupUploader\{version}\`. If the SHA256 hash matches the cached version AND the remote version matches expected, the upload is skipped. Older files are pruned (keep last 2).

---

## Upload & Envelope Handling

### Request Body

```json
{
  "plugin_zip": "<base64>",
  "slug": "my-plugin",
  "activate": true,
  "upload_source": "upload_script",
  "plugin_version": "1.45.0",
  "machine_name": "ALIM-DESKTOP"
}
```

**Fields:**
- `upload_source`: Audit log attribution
- `plugin_version`: Client-sent version for self-update accuracy
- `machine_name`: Source machine hostname via `$env:COMPUTERNAME`

### Response Unwrapping

```powershell
$resultData = $response
if ($response.Results -and $response.Results.Count -gt 0) {
    $resultData = $response.Results[0]
}
```

---

## Self-Update Flow (v2.1.0+)

When the uploaded plugin slug is `riseup-asia-uploader` (the uploader itself):

```
┌──────────────────────────────────────────────────────────┐
│  1. Upload processed by OLD code on server               │
│  2. Response version is STALE (expected)                 │
│  3. Script calls opcache-reset.php to flush OPcache      │
│  4. Script verifies /status returns NEW version          │
└──────────────────────────────────────────────────────────┘
```

### OPcache Reset

The script calls a standalone PHP file deployed with the plugin:

```
GET /wp-content/plugins/riseup-asia-uploader/opcache-reset.php
```

This file:
- Requires Basic Auth (same WordPress credentials)
- Calls `opcache_reset()` to flush all cached bytecode
- Invalidates specific plugin files via `opcache_invalidate()`
- Returns JSON: `{ "success": true, "opcache_reset": true, "files_invalidated": 2 }`

**Note:** On the first deploy of v2.1.0+, the reset script doesn't exist yet on the server, so it will fail gracefully. The second upload will work correctly.

---

## Fallback Mechanism

If the Riseup Asia Uploader API fails, V2 falls back to V1 with direct named parameters:

```powershell
$basicScript = Join-Path $ScriptDir "upload-plugin.ps1"
& $basicScript -JsonConfig $fallbackConfig -Force
```

This ensures deployment succeeds even if the companion plugin has issues.

---

## Quiet Mode Output

When `-Quiet` is set, only a JSON result is written to stdout:

```json
{
  "success": true,
  "plugin": "my-plugin",
  "localVersion": "1.45.0",
  "remoteVersion": "1.36.0",
  "deployedVersion": "1.45.0",
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
| 1 | Missing parameters, plugin not found, ZIP failed, all upload methods failed, security block |

---

*V2 specification v2.1.0 — updated: 2026-02-10*
