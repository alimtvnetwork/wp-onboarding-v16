# Custom Plugin Upload — External Plugin Zipper & Uploader

> **Script:** `wp-plugins/scripts/upload-custom-plugin.ps1`  
> **Config:** `wp-plugins/scripts/custom-plugins.json`  
> **CLI Flag:** `-ucp` / `-upload-custom-plugin`  
> **Status:** Spec Draft

---

## Purpose

Upload external WordPress plugins (plugins outside the managed `wp-plugins/` tree) by reading their folder paths from a JSON config, zipping with best compression, and uploading via the existing Riseup Asia Uploader pipeline. Supports OS-aware paths (Windows / Unix) and multi-site deployment.

---

## CLI Usage

```powershell
# Upload a single plugin to the default site (Test V2)
.\run.ps1 -ucp alim

# Upload a single plugin to ALL configured sites
.\run.ps1 -ucp alim -a
.\run.ps1 -ucp alim -all

# Upload a single plugin to a specific site
.\run.ps1 -ucp alim -site "Test V1"

# List all registered custom plugins
.\run.ps1 -ucp -list

# Help
.\run.ps1 -ucp -help
```

### Flag Reference

| Flag | Alias | Description |
|------|-------|-------------|
| `-ucp <slug>` | `-upload-custom-plugin <slug>` | Upload the named custom plugin |
| `-a` | `-all` | Upload to all configured sites instead of default |
| `-site <name>` | — | Upload to a specific site by name |
| `-list` | — | List all registered custom plugins and their paths |
| `-help` | — | Show custom plugin upload help |

---

## Configuration Schema

**File:** `wp-plugins/scripts/custom-plugins.json`  
**Not committed to git** (may contain local paths).

```json
{
  "defaultSite": "Test V2",
  "sites": [
    {
      "name": "Test V2",
      "url": "https://testv2.developers-organism.com",
      "username": "test-plugins@pxdmail.net",
      "appPassword": "xxxx xxxx xxxx xxxx"
    },
    {
      "name": "Test V1",
      "url": "https://testv1.developers-organism.com",
      "username": "test-plugins@pxdmail.net",
      "appPassword": "xxxx xxxx xxxx xxxx"
    },
    {
      "name": "Atto Property Demo",
      "url": "https://demoat.attoproperty.com.au",
      "username": "admin",
      "appPassword": "xxxx xxxx xxxx xxxx"
    }
  ],
  "plugins": [
    {
      "slug": "alim",
      "name": "Alim Plugin",
      "paths": {
        "windows": "D:\\wp-work\\riseup-asia\\wp-alim\\alim",
        "unix": "/Users/dev/wp-work/riseup-asia/wp-alim/alim"
      },
      "activate": true
    }
  ]
}
```

### Schema Field Reference

#### Root

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `defaultSite` | String | **Yes** | Name of the default target site (must match a `sites[].name`) |
| `sites` | Array | **Yes** | List of WordPress site targets |
| `plugins` | Array | **Yes** | List of external plugins to manage |

#### `sites[]`

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | String | **Yes** | Human-readable site identifier |
| `url` | String | **Yes** | WordPress site URL |
| `username` | String | **Yes** | WordPress admin username |
| `appPassword` | String | **Yes** | WordPress Application Password |

#### `plugins[]`

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `slug` | String | **Yes** | Short identifier used in CLI (e.g., `alim`) |
| `name` | String | No | Display name for logs |
| `paths.windows` | String | **Yes** | Absolute Windows path to plugin folder |
| `paths.unix` | String | **Yes** | Absolute Unix/macOS path to plugin folder |
| `activate` | Boolean | No | Activate after upload (default: `true`) |

---

## Behavior

### 1. OS Detection & Path Resolution

```
if ($IsWindows -or $env:OS -eq "Windows_NT") → use paths.windows
else → use paths.unix
```

If the resolved path does not exist, abort with a clear error.

### 2. ZIP Creation

- Use `[System.IO.Compression.ZipFile]::CreateFromDirectory()` with `CompressionLevel::SmallestSize`
- Root folder inside ZIP must be the plugin `slug`
- Output ZIP to `$env:TEMP\<slug>.zip` (or OS temp equivalent)
- Consistent with project ZIP creation rules (see `mem://architecture/backend/zip-creation-rules`)

### 3. Upload

- Delegate to `upload-plugin-v2.ps1` with direct parameters:
  ```
  -PluginPath <zipPath> -SiteUrl <url> -User <username> -Password <appPassword> -Quiet
  ```
- If `-a` / `-all`: loop through all `sites[]` sequentially
- If `-site <name>`: find matching site by name
- Default: use the site matching `defaultSite`

### 4. Cleanup

- Delete temp ZIP after successful upload
- Preserve ZIP on failure for debugging

---

## Integration with `run.ps1`

The `-ucp` flag is handled in `run.ps1` by dot-sourcing `upload-custom-plugin.ps1`:

```powershell
# In run.ps1 param block:
[switch]$ucp,              # or $uploadCustomPlugin
[string]$ucpSlug = "",     # plugin slug
[switch]$all,              # upload to all sites

# In run.ps1 body:
if ($ucp) {
    . "$PSScriptRoot/wp-plugins/scripts/upload-custom-plugin.ps1"
    Invoke-CustomPluginUpload -Slug $ucpSlug -All:$all -Site $site
    exit $LASTEXITCODE
}
```

---

## Exit Codes

| Code | Description |
|------|-------------|
| 0 | Success |
| 1 | Config file not found or invalid JSON |
| 2 | Plugin slug not found in config |
| 3 | Plugin folder path does not exist |
| 4 | ZIP creation failed |
| 5 | Upload failed (inherits from V2 script) |

---

## Example Workflow

```
PS> .\run.ps1 -ucp alim

[CustomUpload] Reading config: custom-plugins.json
[CustomUpload] Plugin: alim (Alim Plugin)
[CustomUpload] Path (Windows): D:\wp-work\riseup-asia\wp-alim\alim
[CustomUpload] Target: Test V2 (https://testv2.developers-organism.com)
[CustomUpload] Zipping... alim.zip (SmallestSize)
[CustomUpload] Uploading to Test V2...
[CustomUpload] SUCCESS — alim uploaded and activated on Test V2
```

```
PS> .\run.ps1 -ucp alim -a

[CustomUpload] Uploading alim to ALL sites (3 targets)...
  [1/3] Test V2 ... SUCCESS
  [2/3] Test V1 ... SUCCESS
  [3/3] Atto Property Demo ... SUCCESS
[CustomUpload] 3/3 sites completed successfully
```

---

## Security Notes

- `custom-plugins.json` must **never** be committed to git (contains credentials)
- Add to `.gitignore`: `wp-plugins/scripts/custom-plugins.json`
- Application passwords should be per-user, generated in WP Admin

---

*Custom plugin upload specification created: 2026-04-16*
