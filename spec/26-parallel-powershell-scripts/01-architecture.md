# 26-01 — Module Architecture

## File Layout

All new modules live in `wp-plugins/scripts/modules/` alongside existing modules:

```
wp-plugins/scripts/modules/
  ├── helpers.ps1                  # (existing) Utilities
  ├── plugin-helpers.ps1           # (modified) Discovery — remove hardcoded QUpload exclusion
  ├── mode-upload-all-sites.ps1    # (refactored) Thin orchestrator, delegates to new modules
  ├── zip-single.ps1               # (new) Atomic: ZIP one plugin
  ├── zip-parallel.ps1             # (new) Orchestrator: parallel ZIP all plugins
  ├── upload-single.ps1            # (new) Atomic: upload one plugin to one site
  ├── upload-parallel.ps1          # (new) Orchestrator: parallel upload all plugin×site pairs
  └── summary-printer.ps1          # (new) Structured summary table output
```

## Data Flow

```
┌─────────────────────────────────────────────────────────────────┐
│ run.ps1 -uas                                                     │
│                                                                   │
│  1. Git pull                                                      │
│  2. Prerequisites check                                           │
│  3. Invoke-UploadAllSitesMode                                     │
│     │                                                             │
│     ├── Discovery ──────────────────────────────────────────────┐ │
│     │   Get-UploadablePlugins                                   │ │
│     │   → Returns: PluginInfo[] (name, path, version)           │ │
│     │   → Exclusions: only skipPlugins from config              │ │
│     │                                                           │ │
│     ├── ZIP Phase ──────────────────────────────────────────────┤ │
│     │   Invoke-ParallelPluginZip                                │ │
│     │   → For each plugin: Start-Job { Invoke-SinglePluginZip } │ │
│     │   → Returns: ZipResult[] (slug, version, path, sizeKB)   │ │
│     │                                                           │ │
│     ├── Upload Phase ───────────────────────────────────────────┤ │
│     │   Invoke-ParallelPluginUpload                             │ │
│     │   → For each plugin × site: Start-Job { upload }          │ │
│     │   → All jobs launched simultaneously                      │ │
│     │   → Returns: UploadResult[] (indexed, ordered)            │ │
│     │                                                           │ │
│     └── Summary ────────────────────────────────────────────────┘ │
│         Write-UploadSummary                                       │
│         → Groups by site, shows plugin status per site            │
│         → Final totals: sites, plugins, success, failed           │
└─────────────────────────────────────────────────────────────────┘
```

## Result Objects

### ZipResult

```powershell
@{
    Index    = [int]       # Pre-allocated slot index
    Slug     = [string]    # Plugin folder name (e.g., "riseup-asia-uploader")
    Version  = [string]    # Extracted from PHP header (e.g., "2.12.0")
    Path     = [string]    # Absolute path to generated ZIP
    SizeKB   = [double]    # File size in KB
    Status   = [string]    # "OK" | "FAILED"
    Duration = [double]    # Seconds elapsed
    Error    = [string]    # Error message if failed, $null if OK
}
```

### UploadResult

```powershell
@{
    Index    = [int]       # Pre-allocated slot index (site-major order)
    Site     = [string]    # Site display name
    SiteUrl  = [string]    # Full URL
    Plugin   = [string]    # Plugin slug
    Version  = [string]    # Plugin version
    Status   = [string]    # "OK" | "FAILED (exit N)" | "SKIPPED (reason)"
    ExitCode = [int]       # Process exit code
    Duration = [double]    # Seconds elapsed
    Output   = [string]    # Captured stdout+stderr from upload script
    Error    = [string]    # Exception message if invocation failed
}
```

## Execution Order Guarantee

1. **ZIP phase completes entirely before upload phase begins.** No uploads start until all ZIPs are confirmed.
2. **Upload phase:** All `plugin × site` combinations launch simultaneously. A plugin uploading to Site A does NOT wait for the same plugin to finish on Site B, and different plugins upload concurrently.
3. **Summary phase:** Results collected in completion order, sorted by pre-assigned index before display.

## Index Assignment (Site-Major Order)

For deterministic summary output, indices are assigned in **site-major** order:

```
Index 0: Site1 × Plugin1
Index 1: Site1 × Plugin2
Index 2: Site2 × Plugin1
Index 3: Site2 × Plugin2
Index 4: Site3 × Plugin1
Index 5: Site3 × Plugin2
```

This produces a summary grouped naturally by site.

## Module Dependencies

```
run.ps1
  ├── helpers.ps1          (no deps)
  ├── plugin-helpers.ps1   (depends: helpers.ps1)
  ├── zip-single.ps1       (depends: plugin-helpers.ps1)
  ├── zip-parallel.ps1     (depends: zip-single.ps1)
  ├── upload-single.ps1    (depends: plugin-helpers.ps1)
  ├── upload-parallel.ps1  (depends: upload-single.ps1)
  ├── summary-printer.ps1  (no deps)
  └── mode-upload-all-sites.ps1 (depends: all above)
```

All modules are dot-sourced in dependency order by `run.ps1`. Each module file MUST define functions only (no executable code at file scope).
