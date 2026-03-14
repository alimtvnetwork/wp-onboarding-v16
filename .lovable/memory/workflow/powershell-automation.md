# Memory: workflow/powershell-automation
Updated: 2026-03-14

The PowerShell automation suite (run.ps1, upload-plugin-U-Q.ps1) provides integrated zipping and deployment using shorthand aliases (e.g., -u, -q, -z, -pp, -ua). Key flags:

- **Upload:** `-u` (Riseup Asia API), `-q` (QUpload API), `-u -q` (upload Riseup via QUpload), `-ua` (ZIP + upload all plugins via QUpload API)
- **Multi-site (all plugins):** `-uas` (upload ALL plugins INCLUDING QUpload to all enabled sites, parallel by default), `-uas -sync` (sequential mode), `-uas -site 'name'` (target specific site), `-uas -xs 'name'` (exclude sites and/or plugin slugs)
- **Multi-site (default plugin only):** `-u -as` (upload ONLY the default uploader to all enabled sites, parallel by default), `-u -as -sync` (sequential), `-u -as -site 'name'` (target specific site), `-u -as -xs 'name'` (exclude sites)
- **ZIP:** `-z` (default plugin), `-za` (all plugins), `-zq` (QUpload plugin). **All ZIP operations automatically clean old ZIPs first** — no `-c` flag needed (kept for legacy but redundant).
- **Skip list:** `wpPlugins.skipPlugins` in `powershell.json` is the **sole exclusion mechanism** for bulk operations. QUpload is NOT in skipPlugins (it IS included in `-uas`). `plugins-onboard` is in this list.
- **Build:** `-b` (build only), `-s` (skip build), `-r` (rebuild), `-f` (force clean), `-i` (install deps)

**CRITICAL: `git pull` MUST always run first before any command** — including upload, ZIP, and build modes. In run.ps1, `Invoke-GitPull` is called immediately after the banner, before all early-exit paths (ZIP, upload). The `-p` / `-skippull` flag skips it. Upload script (upload-plugin-U-Q.ps1) is called from run.ps1 which handles the pull.

## Flag Behavior Summary

| Command | Plugins Uploaded | Sites |
|---------|-----------------|-------|
| `-uas` | ALL (including QUpload) | All enabled |
| `-u -as` | Default uploader only | All enabled |
| `-ua` | All except skipPlugins | Default site only |
| `-u` | Default uploader | Default site only |

## Modular Architecture (2026-03-14)

run.ps1 is a thin orchestrator that dot-sources modules from `wp-plugins/scripts/modules/`:

| Module | Contents |
|--------|----------|
| `helpers.ps1` | Format-ElapsedTime, Test-Command, Test-IsAdmin, Refresh-Path, version helpers, Decode-Base64, Resolve-RelativePath |
| `pnpm.ps1` | Configure-PnpmStore, Enable-PnpmPnpNodeOptions |
| `install.ps1` | Install-NodeJS, Install-Go, Install-Pnpm |
| `firewall.ps1` | Ensure-FirewallRules |
| `git.ps1` | Invoke-GitPull |
| `plugin-helpers.ps1` | Get-PluginVersion, New-PluginZip, Get-DefaultUploaderPath, Get-DefaultQUploaderPath, Get-DefaultSiteCredential, Show-ConfiguredSites, Clear-PluginZips, Get-UploadablePlugins |
| `zip-single.ps1` | Invoke-SinglePluginZip (atomic: ZIP one plugin, returns ZipResult) |
| `zip-parallel.ps1` | Invoke-ParallelPluginZip, Invoke-SequentialPluginZip (orchestrator) |
| `upload-single.ps1` | Invoke-SinglePluginUpload (atomic: upload one plugin to one site) |
| `upload-parallel.ps1` | Invoke-ParallelPluginUpload, Invoke-SequentialPluginUpload (orchestrator) |
| `summary-printer.ps1` | Write-UploadSummary (grouped by site), Write-ZipSummary |
| `mode-zip.ps1` | Invoke-ZipMode, Invoke-ZipAllMode, Invoke-ZipQUploadMode |
| `mode-upload.ps1` | Invoke-UploadMode, Invoke-QUploadMode, Invoke-UploadComboMode |
| `mode-upload-all.ps1` | Invoke-UploadAllMode |
| `mode-upload-all-sites.ps1` | Invoke-UploadAllSitesMode (thin orchestrator using zip-parallel, upload-parallel, summary-printer) |
| `mode-upload-default-all-sites.ps1` | Invoke-UploadDefaultAllSitesMode (upload only default uploader to all sites) |
| `mode-list-sites.ps1` | Invoke-ListSitesMode |
| `mode-test.ps1` | Invoke-TestMode |
| `mode-clear-logs.ps1` | Invoke-ClearLogsMode |

All module paths use `Join-Path $ScriptDir "wp-plugins" "scripts" "modules"` for safe resolution.

## Parallel Architecture (spec/26-parallel-powershell-scripts/)

The `-uas` pipeline follows a 3-phase architecture:
1. **ZIP Phase:** `Invoke-ParallelPluginZip` fans out background jobs, each calling `Invoke-SinglePluginZip`. Results collected and sorted by pre-assigned index.
2. **Upload Phase:** `Invoke-ParallelPluginUpload` launches ALL plugin x site combinations as simultaneous background jobs. Uses Indexed Result Array Pattern for deterministic output.
3. **Summary Phase:** `Write-UploadSummary` groups results by site, shows per-plugin status with duration.

The `-u -as` mode reuses the same 3-phase architecture but with a single plugin (the default uploader).

The `-xs` flag supports dual-purpose exclusion: site names AND plugin slugs (auto-detected).

Deployment logic (`-q` / `-qupload`) prioritizes `defaultQUploader` from `powershell.json` before falling back to `defaultUploader`. All scripts include self-linting headers to detect syntax errors before execution. Scripts must use UTF-8 (no BOM) with straight ASCII quotes.
