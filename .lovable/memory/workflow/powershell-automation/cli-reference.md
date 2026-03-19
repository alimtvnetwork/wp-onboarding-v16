# Memory: workflow/powershell-automation/cli-reference
Updated: 2026-03-16

The PowerShell automation suite (`run.ps1`) uses a modular architecture with dot-sourced components from `wp-plugins/scripts/modules/`. Configuration is loaded from `powershell.json`.

## Flag Reference

### Build & Run
| Flag | Alias | Description |
|------|-------|-------------|
| `-buildonly` | `-b` | Build frontend only, don't start server |
| `-skipbuild` | `-s` | Skip frontend build, only run backend |
| `-skippull` | `-p` | Skip git pull step |
| `-force` | `-f` | Clean build: remove caches, dependencies, databases |
| `-install` | | Install/update dependencies |
| `-rebuild` | `-r` | Complete clean reinstall (`-force` + `-install`) |
| `-openfirewall` | `-fw` | Add Windows Firewall inbound rules |
| `-test` | `-t` | Run Go backend tests and exit |
| `-verbose` | `-v` | Show detailed debug output |
| `-debug` | `-d` | Enable debug logging (endpoints, paths, responses) |

### Upload — Single
| Flag | Alias | Description |
|------|-------|-------------|
| `-upload` | `-u` | Upload default plugin via Riseup Asia API |
| `-qupload` | `-q` | Upload default plugin via QUpload API |
| `-u -q` | | Upload Riseup Asia Uploader itself via QUpload |
| `-pluginpath` | `-pp` | Override plugin folder path |

### Upload — Bulk
| Flag | Alias | Description |
|------|-------|-------------|
| `-uploadall` | `-ua` | ZIP + upload ALL plugins via QUpload |
| `-uas` | | Upload ALL plugins to ALL sites (parallel) |
| `-u -as` | | Upload DEFAULT plugin to ALL sites (parallel) |
| `-sync` | | Sequential mode (use with `-uas` or `-u -as`) |
| `-site 'name'` | | Target specific site by name (fuzzy match) |
| `-index N` | `-i` | Target site by 1-based index (e.g., `-i 1,2`) |
| `-exclude 'name'` | `-xs` | Exclude sites/plugins by name |

### Log Management
| Flag | Alias | Description |
|------|-------|-------------|
| `-clearlogs` | `-cl` | Clear logs on default site (both plugins) |
| `-clearlogsall` | `-cla` | Clear logs on ALL sites (both plugins) |
| `-logplugin 'q'` | | Filter to specific plugin (`q`\|`qupload`\|`r`\|`riseup`) |
| `-logtype 'err'` | | Clear specific log type (`log`\|`err`\|`stack`\|`files`\|`db`\|`all`) |
| `-audit` | | Clear audit/activity logs (plugins-onboard DB) instead of file logs |
| `-purge` | | Clear ALL logs + audit logs in one command (all sites by default) |

### Machine Management
| Flag | Alias | Description |
|------|-------|-------------|
| `-approvemachine` | `-am` | Approve machine on ALL sites |
| `-approvemachinename` | `-machine`, `-mn` | Specify machine name (defaults to $env:COMPUTERNAME) |

### ZIP
| Flag | Alias | Description |
|------|-------|-------------|
| `-zip` | `-z` | ZIP default plugin |
| `-za` | | ZIP ALL plugins |
| `-zipqupload` | `-zq` | ZIP QUpload only |

### Diagnostics
| Flag | Alias | Description |
|------|-------|-------------|
| `-check` | | Preflight readiness check across all sites (read-only) |

### Info
| Flag | Alias | Description |
|------|-------|-------------|
| `-listsites` | `-ls`, `-lr` | List configured sites |
| `-help` | `-h` | Show help |

## Deployment Order (First-Time)

1. `.\run.ps1 -q` — Bootstrap QUpload via its own API
2. `.\run.ps1 -uas` — Deploy all plugins to all sites
3. `.\run.ps1 -am` — Authorize machine for restricted operations
4. `.\run.ps1 -cla` — Verify log clearing works (6/6 success)

## Key Behaviors

- **Self-lint:** Script validates its own syntax via PowerShell parser before execution
- **Git pull:** Automatic on startup (skip with `-p`)
- **pnpm PnP:** Default node linker; falls back to `isolated` for Node v24+ / cross-drive store
- **skipPlugins:** `powershell.json` → `wpPlugins.skipPlugins` excludes plugins from bulk operations
- **Multi-namespace fallback:** Upload scripts try `riseup-asia-api/v1`, `riseup-asia-uploader/v1`, `riseup-uploader/v1` for resilience during plugin updates
- **Preflight checks:** `-am` queries `/status` and requires v2.17.0+ before attempting approval
