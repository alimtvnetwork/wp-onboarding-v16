# QUpload — PowerShell Upload Script

## Script Name

`upload-plugin-U-Q.ps1`

## Purpose

Upload a plugin ZIP to a WordPress site via the QUpload plugin's `/upload` endpoint.
This script is specifically for uploading plugins through QUpload (not the Riseup Asia Uploader).
It also supports ZIP-only mode for packaging without upload.

## Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `-PluginPath` | string | Yes* | Path to plugin folder to ZIP and upload |
| `-SiteUrl` | string | Yes* | WordPress site URL |
| `-User` | string | Yes* | WordPress username |
| `-Password` | string | Yes* | Application password |
| `-Slug` | string | No | Plugin slug (defaults to folder name) |
| `-Activate` | switch | No | Activate after upload (default: true) |
| `-ConfigPath` | string | No | Path to JSON config file |
| `-JsonConfig` | string | No | Inline JSON config string |
| `-Quiet` | switch | No | Suppress output (JSON-only mode) |
| `-DeleteZip` | switch | No | Delete ZIP after upload |

*Required unless using `-ConfigPath` or `-JsonConfig`.

## Config File Format

```json
{
  "pluginFolderPath": "D:\\path\\to\\plugin",
  "wordPressSiteURL": "https://example.com",
  "username": "admin",
  "appPassword": "xxxx xxxx xxxx xxxx xxxx xxxx",
  "activateAfterInstall": true,
  "deleteZipAfterUpload": false
}
```

## Differences from upload-plugin.ps1

| Feature | upload-plugin.ps1 | upload-plugin-U-Q.ps1 |
|---------|-------------------|----------------------|
| Target API | `riseup-asia-uploader/v1/upload` | `qupload/v1/upload` |
| Plugin | Riseup Asia Uploader | Quick Upload |
| Complexity | Full (status check, version detection, etc.) | Minimal (ZIP + upload + result) |

## Flow

1. Self-lint header (parse error detection)
2. Load config (CLI params / JSON config / config file)
3. Validate plugin folder exists
4. Read local plugin version from header
5. Create ZIP archive
6. Upload ZIP to `POST /wp-json/qupload/v1/upload`
7. Parse and display result
8. Clean up ZIP file (if requested)

## Output (Quiet Mode)

```json
{
  "success": true,
  "plugin": "my-plugin",
  "activated": true
}
```
