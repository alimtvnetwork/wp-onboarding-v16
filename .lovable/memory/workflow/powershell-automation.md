# Memory: workflow/powershell-automation
Updated: 2026-03-14

The PowerShell automation suite (run.ps1, upload-plugin-U-Q.ps1) provides integrated zipping and deployment using shorthand aliases (e.g., -u, -q, -z, -pp, -ua). Key flags:

- **Upload:** `-u` (Riseup Asia API), `-q` (QUpload API), `-u -q` (upload Riseup via QUpload), `-ua` (ZIP + upload all plugins except QUpload via QUpload API)
- **Multi-site:** `-uas` (upload all plugins to all enabled sites, parallel by default), `-uas -sync` (sequential mode with full console output per site), `-uas -site 'name'` (target specific site), `-uas -xs 'name'` (exclude sites)
- **ZIP:** `-z` (default plugin), `-za` (all plugins), `-zq` (QUpload plugin). **All ZIP operations automatically clean old ZIPs first** — no `-c` flag needed (kept for legacy but redundant).
- **Skip list:** `wpPlugins.skipPlugins` in `powershell.json` lists plugins excluded from `-za` and `-ua` operations. `plugins-onboard` is in this list.
- **Build:** `-b` (build only), `-s` (skip build), `-r` (rebuild), `-f` (force clean), `-i` (install deps)

**CRITICAL: `git pull` MUST always run first before any command** — including upload, ZIP, and build modes. In run.ps1, `Invoke-GitPull` is called immediately after the banner, before all early-exit paths (ZIP, upload). The `-p` / `-skippull` flag skips it. Upload script (upload-plugin-U-Q.ps1) is called from run.ps1 which handles the pull.

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
| `mode-zip.ps1` | Invoke-ZipMode, Invoke-ZipAllMode, Invoke-ZipQUploadMode |
| `mode-upload.ps1` | Invoke-UploadMode, Invoke-QUploadMode, Invoke-UploadComboMode |
| `mode-upload-all.ps1` | Invoke-UploadAllMode |
| `mode-upload-all-sites.ps1` | Invoke-UploadAllSitesMode, Invoke-UasSyncUpload, Invoke-UasParallelUpload |
| `mode-list-sites.ps1` | Invoke-ListSitesMode |
| `mode-test.ps1` | Invoke-TestMode |

All module paths use `Join-Path $ScriptDir "wp-plugins" "scripts" "modules"` for safe resolution.

The `-ua` (upload-all) flag scans `wp-plugins/` for directories with valid WordPress plugin headers, excludes QUpload (the transport layer), ZIPs each plugin, then uploads via `POST /wp-json/qupload-api/v1/upload`. A summary table shows success/failure for each plugin.

Deployment logic (`-q` / `-qupload`) prioritizes `defaultQUploader` from `powershell.json` before falling back to `defaultUploader`. All scripts include self-linting headers to detect syntax errors before execution. Scripts must use UTF-8 (no BOM) with straight ASCII quotes.
