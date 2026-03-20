# PowerShell CLI Reference — `run.ps1`

The `run.ps1` script is the **single entry point** for all build, deploy, and management operations. Every invocation runs `git pull` first (skip with `-p`).

**Current Version:** `2.28.3`

---

## Table of Contents

- [Quick Start](#quick-start)
- [Build & Run](#build--run)
- [Upload — Single Plugin](#upload--single-plugin)
- [Upload — All Plugins](#upload--all-plugins)
- [Upload — Multi-Site](#upload--multi-site)
- [Log Management](#log-management)
- [Clear All Sites (-cas)](#clear-all-sites--cas)
- [Machine Authorization](#machine-authorization)
- [Diagnostics & Status](#diagnostics--status)
- [Plugin Status (-ps / -pas)](#plugin-status--ps---pas)
- [ZIP Operations](#zip-operations)
- [Version Bumping](#version-bumping)
- [Verbose Mode (-v)](#verbose-mode--v)
- [Site Targeting](#site-targeting)
- [Configuration](#configuration)
- [Architecture](#architecture)

---

## Quick Start

```powershell
.\run.ps1                  # Full pipeline: git pull → build → run
.\run.ps1 -r               # Clean reinstall + build + run
.\run.ps1 -uas             # Deploy all plugins to all sites
.\run.ps1 -pas             # Check plugin status everywhere
.\run.ps1 -h               # Show built-in help
```

---

## Build & Run

| Flag | Alias | Description |
|------|-------|-------------|
| _(none)_ | | Full pipeline: git pull → prerequisites → pnpm install → build → start Go server |
| `-buildonly` | `-b` | Build frontend only, don't start backend |
| `-skipbuild` | `-s` | Start backend only, skip frontend build |
| `-skippull` | `-p` | Skip `git pull` step |
| `-force` | `-f` | Clean build: remove caches, deps, databases |
| `-install` | | Install/update all dependencies then exit |
| `-rebuild` | `-r` | Full reset: combines `-f` + `-install` + build + run |
| `-openfirewall` | `-fw` | Add Windows Firewall inbound rules (requires Admin) |
| `-test` | `-t` | Run Go backend tests and exit |
| `-verbose` | `-v` | Show detailed debug output (applies to all commands) |

```powershell
.\run.ps1                      # Full build and run
.\run.ps1 -r                   # Clean reinstall + build + run
.\run.ps1 -s                   # Backend only (skip frontend build)
.\run.ps1 -b                   # Build only (no server)
.\run.ps1 -t                   # Run Go tests
.\run.ps1 -p -f                # Clean build without git pull
```

---

## Upload — Single Plugin

| Flag | Alias | Description |
|------|-------|-------------|
| `-upload` | `-u` | Upload default plugin via **Riseup Asia Uploader** API |
| `-qupload` | `-q` | Upload default plugin via **QUpload** API |
| `-u -q` | | Upload Riseup Asia Uploader itself via QUpload API |
| `-debug` | `-d` | Enable debug logging for uploads |
| `-pluginpath` | `-pp` | Override plugin folder path |

```powershell
.\run.ps1 -u                           # Upload via Riseup Asia API
.\run.ps1 -q                           # Upload via QUpload API
.\run.ps1 -u -q                        # Upload Riseup Asia Uploader via QUpload
.\run.ps1 -u -d                        # Upload with debug logging
.\run.ps1 -q -pp 'wp-plugins/qupload'  # Upload specific plugin via QUpload
.\run.ps1 -u -v                        # Upload with verbose JSON output
```

### When to Use Each Mode

| Scenario | Command |
|----------|---------|
| Plugin's own API is active on target | `.\run.ps1 -u` |
| First-time install (plugin API not available) | `.\run.ps1 -u -q` |
| Deploy all plugins at once | `.\run.ps1 -ua` |
| Deploy to all sites | `.\run.ps1 -uas` |

---

## Upload — All Plugins

| Flag | Alias | Description |
|------|-------|-------------|
| `-uploadall` | `-ua` | ZIP + upload **all** plugins (except QUpload and skip list) via QUpload API |
| `-ua -xs 'slug'` | | Exclude specific plugin(s) from upload |

```powershell
.\run.ps1 -ua                          # ZIP + upload all plugins
.\run.ps1 -ua -xs 'riseup-asia-uploader'  # Exclude specific plugin
.\run.ps1 -ua -v                       # Verbose JSON output
```

### Upload Pipeline (3 phases)

1. **Phase 0** — PHP syntax check + PHPStan L6 (blocks on failure)
2. **Phase 1** — Versioned ZIP creation (best compression)
3. **Phase 2** — Upload via QUpload API + activate

Phases 0 and 1 run concurrently. If a plugin fails Phase 0, it's excluded from Phase 2.

---

## Upload — Multi-Site

| Flag | Description |
|------|-------------|
| `-uas` | Upload **all** plugins to **all** configured sites (parallel) |
| `-uas -sync` | Same but sequential (no background jobs) |
| `-u -as` | Upload **default** plugin to **all** sites (parallel) |
| `-u -as -sync` | Same but sequential |

```powershell
.\run.ps1 -uas                         # All plugins → all sites (parallel)
.\run.ps1 -uas -sync                   # All plugins → all sites (sequential)
.\run.ps1 -uas -site 'Test V1'         # All plugins → specific site
.\run.ps1 -uas -i 1                    # All plugins → site #1
.\run.ps1 -uas -i 1,2                  # All plugins → sites #1 and #2
.\run.ps1 -uas -xs 'Test V1'           # All plugins → all sites except Test V1
.\run.ps1 -u -as                       # Default plugin → all sites
.\run.ps1 -u -as -site 'Test V1'       # Default plugin → specific site
.\run.ps1 -uas -v                      # Verbose JSON output for all uploads
```

### Deployment Strategy

Multi-site uploads use a **plugin-sequential, site-parallel** strategy:
1. Each plugin is fully deployed across all target sites before moving to the next
2. Within each plugin, sites are uploaded in parallel (background jobs)
3. This ensures stability when plugins depend on each other for cross-uploads

---

## Log Management

| Flag | Alias | Description |
|------|-------|-------------|
| `-clearlogs` | `-cl` | Clear logs on default site (both plugins) |
| `-clearlogsall` | `-cla` | Clear logs on **all** configured sites |
| `-logplugin` | | Filter by plugin: `q`/`qupload`/`r`/`riseup` |
| `-logtype` | | Filter by type: `log`/`err`/`stack`/`files`/`db`/`all` |
| `-audit` | | Clear audit logs (plugins-onboard DB) |

```powershell
.\run.ps1 -cl                              # Clear logs on default site
.\run.ps1 -cl -site 'Test V1'              # Clear logs on specific site
.\run.ps1 -cl -i 1,2,3                     # Clear logs on sites #1, #2, #3
.\run.ps1 -cl -xs 'Atto Property Demo'     # Clear all except named site
.\run.ps1 -cla                             # Clear logs on ALL sites
.\run.ps1 -cla -logplugin 'q'              # QUpload logs only, all sites
.\run.ps1 -cla -logtype 'err'              # Error logs only, all sites
.\run.ps1 -cla -logplugin 'r' -logtype 'stack'  # Riseup stacktraces only
.\run.ps1 -cl -audit                       # Clear audit logs on default site
.\run.ps1 -cla -audit                      # Clear audit logs on all sites
.\run.ps1 -cl -v                           # Verbose: show pre-clear log state
```

---

## Clear All Sites (-cas)

The nuclear option — clears **all** file logs, stacktraces, error logs, and audit/activity logs across **both plugins** on **all sites** in a single command.

| Flag | Alias | Description |
|------|-------|-------------|
| `-clearallsites` | `-cas` | Clear everything on all sites |
| `-purge` | | Alias for `-cas` |
| `-yes` | `-y` | Skip confirmation prompt (for automation) |

```powershell
.\run.ps1 -cas                     # Clear everything (prompts for confirmation)
.\run.ps1 -cas -yes                # Skip confirmation
.\run.ps1 -cas -site 'Test V1'     # Clear everything on specific site
.\run.ps1 -cas -i 1,2              # Clear on sites #1 and #2
.\run.ps1 -cas -xs 'Production'    # Clear all except production
.\run.ps1 -cas -v                  # Verbose JSON output
.\run.ps1 -purge                   # Same as -cas
```

⚠️ **Destructive action** — shows a confirmation prompt with site names before executing.

---

## Machine Authorization

| Flag | Alias | Description |
|------|-------|-------------|
| `-approvemachine` | `-am` | Approve current machine (`$env:COMPUTERNAME`) on ALL sites |
| `-approvemachinename` | `-machine`, `-mn` | Specify machine name |

```powershell
.\run.ps1 -am                      # Approve current machine on all sites
.\run.ps1 -am 'CI-SERVER'          # Approve specific machine name
.\run.ps1 -am -v                   # Verbose JSON output
.\run.ps1 -am -site 'Test V1'      # Approve on specific site only
```

**Preflight check:** `-am` queries each site's `/status` endpoint and only attempts approval on sites running v2.17.0+. Sites with older versions are reported as "NOT READY" and skipped.

---

## Diagnostics & Status

| Flag | Description |
|------|-------------|
| `-check` | Read-only preflight readiness check across all sites |
| `-check -v` | Verbose: shows registered routes, features, server diagnostics |

```powershell
.\run.ps1 -check                    # Check all sites
.\run.ps1 -check -site 'Test V1'    # Check specific site
.\run.ps1 -check -i 1              # Check site #1
.\run.ps1 -check -v                # Detailed endpoint availability info
```

---

## Plugin Status (-ps / -pas)

| Flag | Alias | Description |
|------|-------|-------------|
| `-pluginstatus` | `-ps` | Check plugin status on default site |
| `-pas` | | Check plugin status on **all** configured sites |
| `-errorlogs` | `-err` | Include error logs and stack traces |

```powershell
.\run.ps1 -ps                      # Status on default site
.\run.ps1 -pas                     # Status on ALL sites (parallel)
.\run.ps1 -pas -sync               # Status on ALL sites (sequential)
.\run.ps1 -pas -err                # Include error logs
.\run.ps1 -pas -v                  # Verbose: raw /status JSON response
.\run.ps1 -pas -site 'Test V1'     # Status for specific site
.\run.ps1 -pas -i 1                # Status for site #1
```

### Output includes:
- **Remote version** — version running on the server
- **Local version** — version in local codebase (auto-detected)
- **Version comparison** — "up to date", "needs deploy", or "remote is newer"
- **Environment info** — WP version, PHP version, API namespace, DB availability
- **Error logs** — line counts and file sizes (saved to `logs/plugin-status/`)

---

## ZIP Operations

| Flag | Alias | Description |
|------|-------|-------------|
| `-zip` | `-z` | ZIP default plugin (auto-cleans old ZIPs) |
| `-za` | | ZIP **all** plugins in `wp-plugins/` |
| `-zas` | | ZIP all plugins (parallel, with PHP syntax check) |
| `-zipqupload` | `-zq` | ZIP QUpload plugin only |
| `-clear` | `-c` | Explicit ZIP cleanup (redundant — all ZIP ops auto-clean) |

```powershell
.\run.ps1 -z                       # ZIP default plugin
.\run.ps1 -za                      # ZIP all plugins
.\run.ps1 -zas                     # ZIP all (parallel + PHP check)
.\run.ps1 -zq                      # ZIP QUpload only
```

---

## Version Bumping

Managed via `bump-version.ps1`. All versions are synchronized across plugin headers, `PluginConfigType.php` enums, `public/version.json`, and `run.ps1`.

```powershell
.\wp-plugins\scripts\bump-version.ps1 -Target all -Bump patch      # Bump everything
.\wp-plugins\scripts\bump-version.ps1 -Target plugin -Bump minor   # Plugin only
.\wp-plugins\scripts\bump-version.ps1 -Target app -Set "3.0.0"     # Set exact version
.\wp-plugins\scripts\bump-version.ps1 -Target all -Bump patch -DryRun  # Preview changes
```

| Target | Files Updated |
|--------|---------------|
| `app` | `public/version.json` → `version`, `releaseDate` |
| `script` | `run.ps1` header, `powershell.json`, `public/version.json` → `scriptVersion` |
| `plugin` | `PluginConfigType.php` → Version case, `public/version.json` → `wpPluginVersion` |
| `all` | All of the above |

---

## Verbose Mode (-v)

The `-v` / `-verbose` flag can be combined with **any** REST-calling command to see raw JSON request/response payloads for debugging:

```powershell
.\run.ps1 -u -v             # Upload with raw JSON
.\run.ps1 -uas -v           # Multi-site upload with raw JSON
.\run.ps1 -pas -v           # Plugin status with raw /status response
.\run.ps1 -check -v         # Preflight check with endpoint details
.\run.ps1 -am -v            # Machine approval with raw JSON
.\run.ps1 -cl -v            # Log clearing with pre-clear state
.\run.ps1 -cas -v           # Clear all with raw JSON
```

Verbose output includes:
- Full URL being called
- Request body (JSON)
- Raw response body (after PHP noise stripping)
- Parsed JSON summary

---

## Site Targeting

Most multi-site commands support targeting specific sites:

| Flag | Alias | Description |
|------|-------|-------------|
| `-site 'name'` | | Target by name (fuzzy, case-insensitive match) |
| `-index N` | `-i` | Target by 1-based index (comma-separated for multiple) |
| `-exclude 'name'` | `-xs` | Exclude by name |
| `-listsites` | `-ls`, `-lr` | List all configured sites with indexes |

```powershell
.\run.ps1 -ls                      # List all sites with their indexes
.\run.ps1 -uas -site 'Test V1'     # Target specific site
.\run.ps1 -uas -i 1,2              # Target sites #1 and #2
.\run.ps1 -uas -xs 'Production'    # Exclude production site
```

---

## Configuration

### `powershell.json`

Main configuration file for `run.ps1`:

```json
{
  "projectName": "WP Publish",
  "rootDir": ".",
  "backendDir": "backend",
  "frontendDir": ".",
  "buildCommand": "pnpm run build",
  "runCommand": "go run ./cmd/server",
  "wpPlugins": {
    "defaultUploader": "riseup-asia-uploader",
    "defaultQUploader": "qupload",
    "pluginsDir": "wp-plugins",
    "skipPlugins": ["plugins-onboard"],
    "plugins": { "..." : "..." },
    "sites": [
      {
        "name": "Site Name",
        "url": "https://example.com",
        "enabled": true,
        "credentials": [
          {
            "appName": "admin",
            "usernameBase64": "...",
            "passwordBase64": "...",
            "isDefault": true
          }
        ]
      }
    ]
  }
}
```

### Site Credentials

Credentials use Base64-encoded username and password in `powershell.json`. The scripts decode them at runtime for HTTP Basic Auth with WordPress Application Passwords.

---

## Architecture

### Module Structure

```
wp-plugins/scripts/
├── modules/
│   ├── helpers.ps1                # Core utility functions
│   ├── install.ps1                # Dependency installation
│   ├── pnpm.ps1                   # pnpm configuration
│   ├── firewall.ps1               # Windows Firewall rules
│   ├── git.ps1                    # Git operations
│   ├── plugin-helpers.ps1         # Plugin discovery & ZIP creation
│   ├── zip-single.ps1             # Single plugin ZIP
│   ├── zip-parallel.ps1           # Parallel ZIP with PHP check
│   ├── php-check-parallel.ps1     # PHP syntax validation
│   ├── upload-single.ps1          # Single-site upload logic
│   ├── upload-parallel.ps1        # Multi-site parallel upload
│   ├── summary-printer.ps1        # Upload/status summary formatting
│   ├── mode-zip.ps1               # -z, -za, -zas handlers
│   ├── mode-upload.ps1            # -u, -q handlers
│   ├── mode-upload-all.ps1        # -ua handler
│   ├── mode-upload-all-sites.ps1  # -uas handler
│   ├── mode-upload-default-all-sites.ps1  # -u -as handler
│   ├── mode-list-sites.ps1        # -ls handler
│   ├── mode-test.ps1              # -t handler
│   ├── mode-clear-logs.ps1        # -cl, -cla, -cas handlers
│   ├── mode-approve-machine.ps1   # -am handler
│   ├── mode-check.ps1             # -check handler
│   └── mode-plugin-status.ps1     # -ps, -pas handlers
├── bump-version.ps1               # Semantic version automation
├── upload-plugin-U-Q.ps1          # Upload via QUpload API
├── upload-plugin-v2.ps1           # Upload via Riseup Asia API
├── scan-plugins.ps1               # Plugin discovery utility
└── README.md                      # This file
```

### Key Design Decisions

- **`Invoke-WebRequest` only** — never `Invoke-RestMethod` (WordPress often prepends PHP deprecation notices to JSON)
- **PHP noise stripping** — all responses locate the first `{` and discard preceding content
- **Self-linting** — `run.ps1` validates its own syntax before execution
- **UTF-8 no BOM** — all scripts must use UTF-8 encoding with straight ASCII quotes
- **Version comparison** — uses `[version]` type casting for safe semver comparison

---

**Author:** MD ALIM UL KARIM · [rasia.pro](https://rasia.pro/alim-r-profile-v1)
