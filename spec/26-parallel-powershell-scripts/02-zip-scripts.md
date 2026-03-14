# 26-02 — ZIP Scripts

## zip-single.ps1 — Atomic ZIP Operation

### Purpose

Creates a ZIP archive for exactly one WordPress plugin. This is the smallest unit of ZIP work — used directly by `-z` mode and called by the parallel orchestrator.

### Function Signature

```powershell
function Invoke-SinglePluginZip {
    param(
        [Parameter(Mandatory)][string]$PluginPath,    # Absolute path to plugin folder
        [switch]$Quiet                                 # Suppress Write-Host output
    )
    # Returns: [hashtable] ZipResult
}
```

### Behavior

1. Resolve plugin name from folder: `Split-Path $PluginPath -Leaf`
2. Extract version from PHP header using `Get-PluginVersion`
3. Construct ZIP filename: `{slug}-v{version}.zip`
4. Output path: parent directory of `$PluginPath` (i.e., `wp-plugins/`)
5. Remove existing ZIP if present
6. Create temp directory, copy plugin files, create ZIP using `[System.IO.Compression.ZipFile]`
7. Clean up temp directory
8. Return `ZipResult` hashtable

### Return Value

```powershell
@{
    Slug     = "riseup-asia-uploader"
    Version  = "2.12.0"
    Path     = "D:\...\wp-plugins\riseup-asia-uploader-v2.12.0.zip"
    SizeKB   = 520.6
    Status   = "OK"          # or "FAILED"
    Error    = $null          # or error message string
}
```

### Error Handling

- If `$PluginPath` does not exist → return `Status = "FAILED"`, `Error = "Plugin folder not found"`
- If ZIP creation throws → catch, return `Status = "FAILED"`, `Error = $_.Exception.Message`
- Never calls `exit` — always returns a result object

### Relationship to Existing Code

This function replaces the inline ZIP logic currently in `New-PluginZip` (plugin-helpers.ps1). `New-PluginZip` should be refactored to call `Invoke-SinglePluginZip` internally, preserving backward compatibility for `-z`, `-za`, `-zq` modes.

---

## zip-parallel.ps1 — Parallel ZIP Orchestrator

### Purpose

ZIPs multiple plugins in parallel using PowerShell background jobs. Each job calls `Invoke-SinglePluginZip`.

### Function Signature

```powershell
function Invoke-ParallelPluginZip {
    param(
        [Parameter(Mandatory)][array]$PluginFolders,   # Array of DirectoryInfo objects
        [switch]$Sequential                             # Use sequential mode instead of parallel
    )
    # Returns: [hashtable[]] Array of ZipResult objects
}
```

### Parallel Strategy

```
Plugin A ──→ [Job 0] Invoke-SinglePluginZip → ZipResult[0]
Plugin B ──→ [Job 1] Invoke-SinglePluginZip → ZipResult[1]
Plugin C ──→ [Job 2] Invoke-SinglePluginZip → ZipResult[2]
          ↓
     Wait-Job -Job $allJobs
          ↓
     Collect + sort by index
```

### Background Job Constraint

Because `Invoke-SinglePluginZip` is defined in a dot-sourced module, background jobs cannot directly call it. Each job must either:

1. **Inline the ZIP logic** in the `-ScriptBlock` (current approach), or
2. **Dot-source the module** inside the job: `. $ModulePath` then call the function

Option 2 is preferred for maintainability. The orchestrator passes `$ModulePath` as an argument:

```powershell
$job = Start-Job -ScriptBlock {
    param($ModulePath, $PluginPath, $Index)
    . $ModulePath  # Load zip-single.ps1 + dependencies
    $result = Invoke-SinglePluginZip -PluginPath $PluginPath -Quiet
    $result.Index = $Index
    return $result
} -ArgumentList $zipSinglePath, $folder.FullName, $jobIndex
```

### Console Output

During ZIP phase:
```
  Zipping 3 plugin(s) in parallel...
    [ZIP] Starting: riseup-asia-uploader
    [ZIP] Starting: qupload
    [ZIP] Starting: my-other-plugin
    [ZIP] Done: riseup-asia-uploader-v2.12.0 (520.6 KB) [1.2s]
    [ZIP] Done: qupload-v2.12.0 (180.3 KB) [0.8s]
    [ZIP] Done: my-other-plugin-v1.0.0 (45.2 KB) [0.5s]
```

### Sequential Fallback

When `$Sequential` is set (from `-sync` flag), iterate `$PluginFolders` sequentially, calling `Invoke-SinglePluginZip` directly (no background jobs).

### Failure Handling

- If any ZIP fails, include it in the results with `Status = "FAILED"`
- Do NOT abort the entire operation — let other ZIPs complete
- The upload phase will skip plugins without a valid ZIP path
