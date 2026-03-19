# 17-07 — PHP Syntax Check: Skip Folders Configuration

## Purpose

Allow per-plugin configuration of folders to skip during PHP syntax checking (`php -l`), avoiding wasted time on third-party code (e.g., `vendor/`, `node_modules/`) that is not our responsibility to validate.

---

## Configuration

### Location

Each plugin's `settings.json` file (already exists in plugin roots) gains a `phpCheck` section:

```json
{
  "phpCheck": {
    "skipFolders": ["vendor", "node_modules"]
  }
}
```

### Global Default

`powershell.json` → `wpPlugins` gains a `phpCheckSkipFolders` array that applies to **all** plugins as a baseline:

```json
{
  "wpPlugins": {
    "phpCheckSkipFolders": ["vendor"],
    ...
  }
}
```

### Merge Behavior

The effective skip list for a plugin is the **union** of:
1. Global `wpPlugins.phpCheckSkipFolders` from `powershell.json`
2. Per-plugin `phpCheck.skipFolders` from the plugin's `settings.json`

Duplicates are removed. Folder names are matched against the **relative path** from the plugin root (e.g., `vendor` matches `{pluginDir}/vendor/...`).

---

## Affected Scripts

### 1. `php-check-parallel.ps1` — `Test-PluginPhpSyntaxStandalone`

Before scanning PHP files, load skip folders:

```powershell
$skipFolders = @()

# Global defaults
if ($Config -and $Config.wpPlugins -and $Config.wpPlugins.phpCheckSkipFolders) {
    $skipFolders += @($Config.wpPlugins.phpCheckSkipFolders)
}

# Per-plugin settings.json
$settingsPath = Join-Path $PluginDir "settings.json"
if (Test-Path $settingsPath) {
    $pluginSettings = Get-Content $settingsPath -Raw | ConvertFrom-Json
    if ($pluginSettings.phpCheck -and $pluginSettings.phpCheck.skipFolders) {
        $skipFolders += @($pluginSettings.phpCheck.skipFolders)
    }
}

$skipFolders = @($skipFolders | Select-Object -Unique)
```

Then filter files:

```powershell
$phpFiles = @(Get-ChildItem -Path $PluginDir -Recurse -File -Filter "*.php" |
    Where-Object {
        $relativePath = $_.FullName.Substring($PluginDir.Length + 1)
        $isSkipped = $false
        foreach ($skip in $skipFolders) {
            if ($relativePath -like "$skip\*" -or $relativePath -like "$skip/*") {
                $isSkipped = $true
                break
            }
        }
        -not $isSkipped
    } | Sort-Object FullName)
```

### 2. `upload-plugin-U-Q.ps1` — `Test-PluginPhpSyntax`

Same filtering logic applied to the inline syntax check function. Since background jobs don't have access to `$Config`, the parallel orchestrator must pass skip folders as an argument.

### 3. `upload-plugin-v2.ps1` / `upload-plugin-v3.ps1`

Same filtering applied if they have their own `Test-PluginPhpSyntax`.

---

## Console Output

When skip folders are active, show them:

```
  ── Phase 0: PHP Syntax Check ──────────────────────────
  Skipping folders: vendor
  Checking 2 plugin(s) in parallel...
    [PHP] Starting: qupload
    [PHP] Starting: riseup-asia-uploader
    [PHP] Passed: qupload (49 files, 0 skipped) [0.8s]
    [PHP] Passed: riseup-asia-uploader (156 files, 312 skipped) [2.1s]
```

---

## Schema Update

### `powershell.schema.json`

Add under `wpPlugins.properties`:

```json
"phpCheckSkipFolders": {
  "type": "array",
  "items": { "type": "string" },
  "default": ["vendor"],
  "description": "Folder names to skip during PHP syntax checks (applied globally to all plugins)"
}
```

---

## Default Values

| Plugin | `settings.json` skipFolders | Effective (with global `["vendor"]`) |
|--------|----------------------------|--------------------------------------|
| qupload | `[]` (none) | `["vendor"]` |
| riseup-asia-uploader | `[]` (none) | `["vendor"]` |

Both plugins inherit the global `vendor` skip. Since `qupload` has no vendor folder, the filter is a no-op for it.
