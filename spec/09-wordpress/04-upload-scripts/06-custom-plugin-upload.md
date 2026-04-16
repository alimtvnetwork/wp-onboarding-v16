# Custom Plugin Upload — External Plugin Zipper & Uploader

> **Script:** `wp-plugins/scripts/upload-custom-plugin.ps1`  
> **Module:** `wp-plugins/scripts/modules/mode-custom-upload.ps1`  
> **Config:** `wp-plugins/scripts/custom-plugins.json`  
> **CLI Flag:** `-ucp` / `-uploadcustomplugin`  
> **Version:** 1.1.0  
> **Status:** Active

---

## Purpose

Upload external WordPress plugins (plugins outside the managed `wp-plugins/` tree) by reading their folder paths from a JSON config, zipping with best compression, and uploading via the existing Riseup Asia Uploader pipeline. Supports OS-aware paths (Windows / Unix), multi-site deployment, comma-separated batch uploads, direct folder paths, and post-upload ping verification.

---

## CLI Usage

```powershell
# Upload a single plugin to the default site (Test V2)
.\run.ps1 -ucp alim

# Upload a single plugin to ALL configured sites
.\run.ps1 -ucp alim -a

# Upload a single plugin to a specific site
.\run.ps1 -ucp alim -site "Test V1"

# Upload multiple plugins (comma-separated)
.\run.ps1 -ucp alim,other-plugin
.\run.ps1 -ucp alim,other-plugin -a

# Direct folder path (slug derived from folder name)
.\run.ps1 -ucp "D:\wp-work\riseup-asia\wp-alim\alim"
.\run.ps1 -ucp "/home/dev/wp-alim/alim" -a

# List all registered custom plugins
.\run.ps1 -ucp -list

# Help
.\run.ps1 -ucp -help
```

### Flag Reference

| Flag | Alias | Description |
|------|-------|-------------|
| `-ucp <slug>` | `-uploadcustomplugin <slug>` | Upload the named custom plugin |
| `-ucp <path>` | — | Upload from a direct folder path (slug = folder name) |
| `-ucp s1,s2` | — | Upload multiple plugins (comma-separated slugs) |
| `-a` | `-allcustomsites` | Upload to all configured sites instead of default |
| `-site <name>` | — | Upload to a specific site by name |
| `-list` | — | List all registered custom plugins and their paths |
| `-help` | — | Show custom plugin upload help |

---

## Configuration Schema

**File:** `wp-plugins/scripts/custom-plugins.json`  
**Not committed to git** (contains local paths and credentials).

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
      "activate": true,
      "pingEndpoint": "/wp-json/alim/v1/status"
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
| `pingEndpoint` | String | No | REST API endpoint suffix for post-upload verification |

---

## Behavior

### 1. Slug Resolution (Config Lookup → Direct Path Fallback)

```
1. Look up $Slug in config.plugins[].slug
2. If found → use OS-aware path from config
3. If NOT found → check if $Slug is an existing folder path
   3a. If folder exists → use it directly, derive slug from folder name
   3b. If not → error with available slugs
```

### 2. OS Detection & Path Resolution

```
if ($IsWindows -or $env:OS -eq "Windows_NT") → use paths.windows
else → use paths.unix
```

If the resolved path does not exist, abort with a clear error.

### 3. Git Pull (auto-detected)

Before zipping, the script checks if the plugin folder is inside a Git repository:

1. Run `git -C <pluginFolder> rev-parse --is-inside-work-tree` to detect if it's a Git repo
2. If **not a Git repo** → skip silently, proceed to PHP syntax check
3. If **is a Git repo** → run `git -C <pluginFolder> pull --rebase`
   - **On success:** show current branch + short commit hash, proceed
   - **On failure:** print a **warning** (not fatal) and continue with upload
4. If `-skipgitpull` flag is set → skip Git pull entirely with a notice

```
  Git repo detected (branch: main)
  Running git pull --rebase...
  Git pull OK (main @ abc1234)
```

```
  Git pull WARNING: <error message>
  Continuing with upload...
```

**Rationale:** Ensures the latest code is always uploaded. Failure is non-fatal because the developer may be working offline or on a local-only branch.

### 4. PHP Syntax Check (skip vendor folder)

- Run `php -l` on all `.php` files in the plugin folder
- Skip `vendor/` folder and any folders listed in `settings.json → phpCheck.skipFolders`
- Abort with exit code 4 if any syntax errors are found

### 5. ZIP Creation

- Use `[System.IO.Compression.ZipFile]::CreateFromDirectory()` with `CompressionLevel::SmallestSize`
- Root folder inside ZIP must be the plugin `slug`
- Output ZIP to `$env:TEMP\<slug>.zip` (or OS temp equivalent)
- Consistent with project ZIP creation rules (see `mem://architecture/backend/zip-creation-rules`)

### 6. Upload

- Delegate to `upload-plugin-v2.ps1` with direct parameters:
  ```
  -PluginPath <folderPath> -SiteUrl <url> -User <username> -Password <appPassword> -Slug <slug> -Quiet -DeleteZip
  ```
- If `-a`: loop through all `sites[]` sequentially
- If `-site <name>`: find matching site by name
- Default: use the site matching `defaultSite`

### 7. Post-Upload Ping Verification

If the plugin has a `pingEndpoint` configured:

1. Construct full URL: `{siteUrl}{pingEndpoint}` (e.g., `https://testv2.example.com/wp-json/alim/v1/status`)
2. Send GET request with Basic Auth headers (`Authorization: Basic <base64>`)
3. Report HTTP status and response fields (`Status`, `Version`)
4. Ping failure is **non-fatal** — it's a verification step, not a blocker

```
  Pinging https://testv2.example.com/wp-json/alim/v1/status ...
  PING OK - Test V2 responded
    Status: Active
    Version: 1.0.0
```

### 8. Multi-Plugin Batch Upload

When comma-separated slugs are provided (e.g., `alim,other`):

- The `mode-custom-upload.ps1` module splits on commas and invokes the upload script once per slug
- Each plugin is uploaded sequentially with its own ZIP + upload + ping cycle
- A batch summary is printed at the end showing success/fail counts
- **Path detection**: Values containing `\`, `/`, or `:` are treated as folder paths and never comma-split

```powershell
# Two plugins to default site
.\run.ps1 -ucp alim,other-plugin

# Two plugins to ALL sites
.\run.ps1 -ucp alim,other-plugin -a
```

### 9. Timing

Every major step is timed using `[System.Diagnostics.Stopwatch]`:

| Step | Timer |
|------|-------|
| Git pull | Elapsed time for pull --rebase |
| PHP syntax check | Elapsed time for all `php -l` runs |
| ZIP creation | Elapsed time for compression |
| Upload (per site) | Elapsed time per site upload |
| **Total** | End-to-end elapsed from start to finish |

All timings are displayed in human-readable format (e.g., `1.23s`, `45.6s`).

```
  Git pull OK (main @ abc1234) — 0.8s
  PHP syntax OK: 42 files checked — 1.2s
  ZIP created: C:\...\alim.zip (1.23 MB) — 0.5s
  [1/3] SUCCESS - alim uploaded to Test V2 — 3.4s
  ...
  Total elapsed: 8.2s
```

### 10. Cleanup

- Delete temp ZIP after successful upload
- Preserve ZIP on failure for debugging

---

## Integration with `run.ps1`

The `-ucp` flag is handled via `mode-custom-upload.ps1` module (dot-sourced by `run.ps1`):

```powershell
# In run.ps1 param block:
[Alias('ucp')][string]$uploadcustomplugin = "",
[Alias('a')][switch]$allcustomsites

# In run.ps1 early exit modes:
if ($isUcpActive) {
    Invoke-CustomPluginUploadMode `
        -PluginSlug $ucpSlugValue `
        -AllSites:$allcustomsites `
        -SiteName $site `
        -VerboseMode:$verbose
}
```

---

## Exit Codes

| Code | Description |
|------|-------------|
| 0 | Success |
| 1 | Config file not found, invalid JSON, or site not found |
| 2 | Plugin slug not found in config AND not a valid folder path |
| 3 | Plugin folder path does not exist |
| 4 | ZIP creation failed |
| 5 | Upload failed (inherits from V2 script) or batch partial failure |

---

## Example Workflows

### Single plugin (config lookup)

```
PS> .\run.ps1 -ucp alim

========================================
  Custom Plugin Upload
========================================
  Plugin:  Alim Plugin (alim)
  Path:    D:\wp-work\riseup-asia\wp-alim\alim
  Target:  Test V2
  Ping:    /wp-json/alim/v1/status

  Zipping with SmallestSize compression...
  ZIP created: C:\...\alim.zip (1.23 MB)

  Uploading to Test V2 (https://testv2.developers-organism.com)...
  SUCCESS - alim uploaded to Test V2
  Pinging https://testv2.developers-organism.com/wp-json/alim/v1/status ...
  PING OK - Test V2 responded
    Version: 1.0.0

========================================
  alim: 1/1 sites completed successfully
========================================
```

### Direct folder path

```
PS> .\run.ps1 -ucp "D:\wp-work\my-new-plugin"

  [DirectPath] Folder detected, using slug: my-new-plugin

========================================
  Custom Plugin Upload [DirectPath]
========================================
  Plugin:  my-new-plugin (my-new-plugin)
  Path:    D:\wp-work\my-new-plugin
  Target:  Test V2

  Zipping with SmallestSize compression...
  ...
```

### Multi-plugin batch to all sites

```
PS> .\run.ps1 -ucp alim,other-plugin -a

========================================
  Batch Custom Plugin Upload (2 plugins)
========================================
  Plugins: alim, other-plugin

--- [1/2] alim ---
  ...
  SUCCESS - alim uploaded to Test V2
  PING OK - Test V2 responded
  SUCCESS - alim uploaded to Test V1
  PING OK - Test V1 responded
  SUCCESS - alim uploaded to Atto Property Demo
  ...

--- [2/2] other-plugin ---
  ...

========================================
  Batch: 2/2 plugins completed successfully
========================================
```

### All sites upload

```
PS> .\run.ps1 -ucp alim -a

========================================
  Custom Plugin Upload
========================================
  Plugin:  Alim Plugin (alim)
  Path:    D:\wp-work\riseup-asia\wp-alim\alim
  Target:  3 sites

  [1/3] Uploading to Test V2 (https://testv2.developers-organism.com)...
  [1/3] SUCCESS - alim uploaded to Test V2
  [1/3] PING OK - Test V2 responded

  [2/3] Uploading to Test V1 (https://testv1.developers-organism.com)...
  [2/3] SUCCESS - alim uploaded to Test V1

  [3/3] Uploading to Atto Property Demo (https://demoat.attoproperty.com.au)...
  [3/3] SUCCESS - alim uploaded to Atto Property Demo

========================================
  alim: 3/3 sites completed successfully
========================================
```

---

## Security Notes

- `custom-plugins.json` must **never** be committed to git (contains credentials)
- Add to `.gitignore`: `wp-plugins/scripts/custom-plugins.json`
- Application passwords should be per-user, generated in WP Admin

---

*Custom plugin upload specification v1.1.0 — updated: 2026-04-16*
