# 26-05 — Flags and Configuration

## powershell.json Changes

### Remove Implicit QUpload Exclusion

Currently, `Get-UploadablePlugins` in `plugin-helpers.ps1` hardcodes QUpload exclusion:

```powershell
# CURRENT (to be removed):
$quploadSlug = "qupload"
if ($Config.wpPlugins -and $Config.wpPlugins.defaultQUploader) {
    $quploadSlug = $Config.wpPlugins.defaultQUploader
}
$skipList = @($quploadSlug)  # ← Hardcoded exclusion
```

**After refactor:** The `$skipList` is populated ONLY from `wpPlugins.skipPlugins`. If QUpload should be excluded from `-uas`, add it to `skipPlugins` explicitly.

### skipPlugins Array

The `skipPlugins` array in `powershell.json` is the **sole mechanism** for excluding plugins from bulk operations (`-za`, `-ua`, `-uas`):

```json
{
  "wpPlugins": {
    "skipPlugins": ["plugins-onboard"],
    ...
  }
}
```

- QUpload is **NOT** in `skipPlugins` by default → it WILL be zipped and uploaded
- `plugins-onboard` remains excluded (development/onboarding tool)
- Users can add any plugin slug to exclude it

### Schema Update

`powershell.schema.json` already defines `skipPlugins` as `string[]`. No schema changes needed.

---

## CLI Flags

### Existing Flags (unchanged)

| Flag | Description |
|------|-------------|
| `-uas` | Upload all plugins to all enabled sites |
| `-sync` | Sequential mode (real-time console output per operation) |
| `-site 'Name'` | Target a specific site |
| `-xs 'Name'` | Exclude specific site(s) from multi-site upload |

### New/Modified Behavior

| Flag | Change |
|------|--------|
| `-uas` | Now uploads ALL plugins (including QUpload) unless in `skipPlugins` |
| `-xs` | Can also accept plugin slugs to exclude from the current run (overrides config for this session only) |

### Plugin Exclusion via -xs

The `-xs` flag currently excludes sites. With this refactor, it gains dual-purpose exclusion:

```powershell
# Exclude a site:
.\run.ps1 -uas -xs "Test V2"

# Exclude a plugin (detected by checking if value matches a plugin slug):
.\run.ps1 -uas -xs "qupload"

# Exclude both (comma-separated, auto-detected):
.\run.ps1 -uas -xs "Test V2,qupload"
```

Detection logic:
```powershell
$excludeItems = @($exclude -split ',' | ForEach-Object { $_.Trim() })
$allPluginSlugs = @($pluginFolders | ForEach-Object { $_.Name })
$allSiteNames = @($Config.wpPlugins.sites | ForEach-Object { $_.name })

$excludedSites = @($excludeItems | Where-Object { $_ -in $allSiteNames })
$excludedPlugins = @($excludeItems | Where-Object { $_ -in $allPluginSlugs })
$unmatched = @($excludeItems | Where-Object { $_ -notin $allSiteNames -and $_ -notin $allPluginSlugs })

if ($unmatched.Count -gt 0) {
    Write-Host "WARNING: Unrecognized exclusion(s): $($unmatched -join ', ')" -ForegroundColor Yellow
}
```

---

## Backward Compatibility

| Scenario | Before | After |
|----------|--------|-------|
| `.\run.ps1 -uas` | Uploads only non-QUpload plugins | Uploads ALL plugins (including QUpload) |
| `.\run.ps1 -ua` | Uploads non-QUpload plugins to default site | No change (uses same `Get-UploadablePlugins`) |
| `.\run.ps1 -za` | ZIPs all except `skipPlugins` | No change (already doesn't exclude QUpload) |
| `.\run.ps1 -z` | ZIPs default plugin | No change |

### Migration Note

The only behavioral change is that `-uas` and `-ua` will now include QUpload in the upload set. If a project wants to preserve the old behavior, add `"qupload"` to `skipPlugins`:

```json
{
  "wpPlugins": {
    "skipPlugins": ["plugins-onboard", "qupload"]
  }
}
```

---

## Exit Codes

| Code | Meaning |
|------|---------|
| 0 | All uploads successful |
| 1 | One or more uploads failed |

The exit code reflects the worst-case outcome across all operations.
