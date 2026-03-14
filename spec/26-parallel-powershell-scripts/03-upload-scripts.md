# 26-03 — Upload Scripts

## upload-single.ps1 — Atomic Upload Operation

### Purpose

Uploads exactly one plugin ZIP to exactly one WordPress site via the QUpload API. This is the smallest unit of upload work.

### Function Signature

```powershell
function Invoke-SinglePluginUpload {
    param(
        [Parameter(Mandatory)][string]$QUploadScript,    # Path to upload-plugin-U-Q.ps1
        [Parameter(Mandatory)][string]$PluginPath,       # Absolute path to plugin folder
        [Parameter(Mandatory)][string]$ZipPath,          # Absolute path to pre-built ZIP
        [Parameter(Mandatory)][string]$SiteUrl,          # WordPress site URL
        [Parameter(Mandatory)][string]$Username,         # Decoded username
        [Parameter(Mandatory)][string]$Password,         # Decoded app password
        [Parameter(Mandatory)][string]$PluginSlug,       # Plugin folder name
        [Parameter(Mandatory)][string]$SiteName,         # Display name of the site
        [Parameter(Mandatory)][string]$PluginVersion,    # Version string
        [switch]$Quiet                                    # Suppress Write-Host output
    )
    # Returns: [hashtable] UploadResult
}
```

### Behavior

1. Construct JSON config for `upload-plugin-U-Q.ps1`:
   ```powershell
   $uploadConfig = @{
       pluginFolderPath     = $PluginPath
       outputZipPath        = $ZipPath
       wordPressSiteURL     = $SiteUrl.TrimEnd("/")
       username             = $Username
       appPassword          = $Password
       activateAfterInstall = $true
       deleteZipAfterUpload = $false
   }
   ```
2. Invoke `upload-plugin-U-Q.ps1 -jc $jsonConfigStr -a`
3. Capture stdout+stderr via `2>&1 | Out-String`
4. Resolve exit code (handle `$null` LASTEXITCODE)
5. Return `UploadResult` hashtable

### Return Value

```powershell
@{
    Site     = "Atto Property Demo"
    SiteUrl  = "https://demoat.attoproperty.com.au"
    Plugin   = "riseup-asia-uploader"
    Version  = "2.12.0"
    Status   = "OK"                    # or "FAILED (exit 1)"
    ExitCode = 0
    Output   = "..."                   # Full captured output
    Error    = $null                    # Exception message if invocation crashed
    Duration = 3.2                     # Seconds
}
```

### Error Handling

- Wrap invocation in `try/catch` — never throw, always return a result
- If `$LASTEXITCODE` is `$null` and no exception, treat as success (exit 0)
- Never calls `exit`

---

## upload-parallel.ps1 — Parallel Upload Orchestrator

### Purpose

Launches all `plugin × site` upload combinations as simultaneous background jobs. This is the core of the new `-uas` parallel behavior.

### Function Signature

```powershell
function Invoke-ParallelPluginUpload {
    param(
        [Parameter(Mandatory)][array]$TargetSites,      # Site config objects from powershell.json
        [Parameter(Mandatory)][array]$PluginFolders,     # DirectoryInfo objects
        [Parameter(Mandatory)][hashtable]$ZipByPlugin,   # slug → ZIP path lookup
        [Parameter(Mandatory)][hashtable]$VersionByPlugin, # slug → version lookup
        [Parameter(Mandatory)][string]$QUploadScript,    # Path to upload-plugin-U-Q.ps1
        [Parameter(Mandatory)][string]$UploadLogsDir,    # Directory for failure logs
        [Parameter(Mandatory)][string]$LogStamp,         # Timestamp prefix for log files
        [switch]$Sequential                               # Use sequential mode
    )
    # Returns: [hashtable[]] Array of UploadResult objects (sorted by index)
}
```

### Parallel Execution Matrix

For 2 plugins × 3 sites = 6 simultaneous jobs:

```
                    Site A          Site B          Site C
Plugin 1     ┌─── Job[0] ───┐ ┌─── Job[1] ───┐ ┌─── Job[2] ───┐
             │  Upload P1→A │ │  Upload P1→B │ │  Upload P1→C │
             └──────────────┘ └──────────────┘ └──────────────┘
Plugin 2     ┌─── Job[3] ───┐ ┌─── Job[4] ───┐ ┌─── Job[5] ───┐
             │  Upload P2→A │ │  Upload P2→B │ │  Upload P2→C │
             └──────────────┘ └──────────────┘ └──────────────┘

All 6 jobs run simultaneously. No dependency between them.
```

### Index Assignment

Site-major ordering for natural grouping in summary:

```powershell
$jobIndex = 0
foreach ($site in $TargetSites) {
    foreach ($plugin in $PluginFolders) {
        # Index $jobIndex assigned to this site×plugin pair
        $jobIndex++
    }
}
```

### Pre-Flight Checks

Before launching jobs:
1. Resolve credentials for each site via `Get-DefaultSiteCredential`
2. Skip sites with no valid credentials (add `SKIPPED` result for each plugin)
3. Skip plugins with no ZIP (add `SKIPPED` result)
4. Log skips to console immediately

### Result Collection

```powershell
$completedResults = @()
foreach ($job in $uploadJobs) {
    $result = Receive-Job -Job $job -Wait | Select-Object -First 1
    # Fallback for null results (job crash)
    if ($null -eq $result) {
        $idx = [int]($job.Name -replace '^upload-', '')
        $result = $preAllocated[$idx]
        $result.Status = "FAILED (job crashed)"
    }
    $completedResults += $result
    Remove-Job -Job $job -Force
}

# Sort by index for deterministic display
$orderedResults = $completedResults | Sort-Object { $_.Index }
```

### Failure Logging

For each failed upload, write a detailed log file:
```
logs/uas-upload/{timestamp}-{plugin}-{site}.log
```

Contents include: timestamp, site, plugin, ZIP path, exit code, full captured output.

### Real-Time Progress (Optional Enhancement)

Using a synchronized hashtable, the main thread can poll completion count:

```powershell
$progress = [hashtable]::Synchronized(@{ Completed = 0; Total = $totalJobs })

# In each job's finally block:
$progress.Completed++

# In main thread polling loop:
Write-Host "`r  Progress: $($progress.Completed)/$($progress.Total)" -NoNewline
```

This is an optional enhancement — the initial implementation can collect results after all jobs complete.

### Sequential Fallback

When `-Sequential` is set, iterate sites then plugins, calling the upload logic directly (no background jobs), with full console output streamed in real-time.
