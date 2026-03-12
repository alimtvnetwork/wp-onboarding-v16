# Memory: workflow/powershell-automation
Updated: 2026-03-12

The PowerShell automation suite (run.ps1, upload-plugin-U-Q.ps1) provides integrated zipping and deployment using shorthand aliases (e.g., -u, -q, -z, -pp, -ua). Key flags:

- **Upload:** `-u` (Riseup Asia API), `-q` (QUpload API), `-u -q` (upload Riseup via QUpload), `-ua` (ZIP + upload all plugins except QUpload via QUpload API)
- **ZIP:** `-z` (default plugin), `-za` (all plugins), `-zq` (QUpload plugin). **All ZIP operations automatically clean old ZIPs first** — no `-c` flag needed (kept for legacy but redundant).
- **Skip list:** `wpPlugins.skipPlugins` in `powershell.json` lists plugins excluded from `-za` and `-ua` operations. `plugins-onboard` is in this list.
- **Build:** `-b` (build only), `-s` (skip build), `-r` (rebuild), `-f` (force clean), `-i` (install deps)

**CRITICAL: `git pull` MUST always run first before any command** — including upload, ZIP, and build modes. In run.ps1, `Invoke-GitPull` is called immediately after the banner, before all early-exit paths (ZIP, upload). The `-p` / `-skippull` flag skips it. Upload script (upload-plugin-U-Q.ps1) is called from run.ps1 which handles the pull.

The `-ua` (upload-all) flag scans `wp-plugins/` for directories with valid WordPress plugin headers, excludes QUpload (the transport layer), ZIPs each plugin, then uploads via `POST /wp-json/qupload-api/v1/upload`. A summary table shows success/failure for each plugin.

Deployment logic (`-q` / `-qupload`) prioritizes `defaultQUploader` from `powershell.json` before falling back to `defaultUploader`. All scripts include self-linting headers to detect syntax errors before execution. Scripts must use UTF-8 (no BOM) with straight ASCII quotes.
