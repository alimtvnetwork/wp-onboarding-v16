# WordPress Plugin Uploader V3 — Parallel Multi-Plugin Upload & Status
# Wraps upload-plugin-v2.ps1 for parallel execution across multiple plugins.
#
# Usage:
#   .\upload-plugin-v3.ps1 -p "C:\path\plugin1, C:\path\plugin2, C:\path\plugin3"
#   .\upload-plugin-v3.ps1 -p "C:\path\plugin1, C:\path\plugin2" -Activate
#   .\upload-plugin-v3.ps1 -s                          # Status of default plugin
#   .\upload-plugin-v3.ps1 -s -p "C:\path\my-plugin"   # Status of specific plugin
#
# Version: 3.0.0

param(
    [Alias('p')]
    [Parameter(Mandatory=$false)]
    [string]$PluginPaths = "",

    [Alias('s')]
    [Parameter(Mandatory=$false)]
    [switch]$Status = $false,

    [Parameter(Mandatory=$false)]
    [switch]$Activate = $false,

    [Parameter(Mandatory=$false)]
    [switch]$SkipGitPull = $false,

    [Parameter(Mandatory=$false)]
    [switch]$Quiet = $false,

    [Alias('c')]
    [Parameter(Mandatory=$false)]
    [int]$Concurrency = 4,

    [Alias('h')]
    [Parameter(Mandatory=$false)]
    [switch]$Help = $false
)

$ErrorActionPreference = "Stop"

# --- Self-lint: detect parse errors before execution ---
$_lintScriptFile = $MyInvocation.MyCommand.Path
if ($_lintScriptFile -and (Test-Path $_lintScriptFile)) {
    $_lintErrors = $null
    [void][System.Management.Automation.Language.Parser]::ParseFile(
        $_lintScriptFile, [ref]$null, [ref]$_lintErrors
    )
    if ($_lintErrors -and $_lintErrors.Count -gt 0) {
        $scriptName = Split-Path $_lintScriptFile -Leaf
        Write-Host "LINT FAILED: $scriptName has parse errors" -ForegroundColor Red
        foreach ($e in $_lintErrors) {
            Write-Host "  Line $($e.Extent.StartLineNumber): $($e.Message)" -ForegroundColor Yellow
        }
        Write-Host "Fix: Ensure UTF-8 (no BOM) encoding with straight ASCII quotes." -ForegroundColor Cyan
        exit 1
    }
}

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$V2Script = Join-Path $ScriptDir "upload-plugin-v2.ps1"
$TotalStopwatch = [System.Diagnostics.Stopwatch]::StartNew()

# ============================================================================
# HELPERS
# ============================================================================

function Write-Banner {
    param([string]$Text, [string]$Color = "Cyan")
    $line = "=" * ($Text.Length + 4)
    Write-Host ""
    Write-Host $line -ForegroundColor $Color
    Write-Host "  $Text" -ForegroundColor $Color
    Write-Host $line -ForegroundColor $Color
    Write-Host ""
}

function Write-Step {
    param([string]$Text, [string]$Color = "White")
    Write-Host "  ▸ $Text" -ForegroundColor $Color
}

function Write-Result {
    param([string]$Name, [bool]$Success, [string]$Detail = "", [double]$DurationSec = 0)
    $icon = if ($Success) { "✅" } else { "❌" }
    $color = if ($Success) { "Green" } else { "Red" }
    $timeStr = if ($DurationSec -gt 0) { " ({0:F1}s)" -f $DurationSec } else { "" }
    Write-Host "  $icon " -NoNewline
    Write-Host $Name -ForegroundColor $color -NoNewline
    if ($Detail) {
        Write-Host " — $Detail" -ForegroundColor Gray -NoNewline
    }
    Write-Host $timeStr -ForegroundColor DarkGray
}

function Get-PluginSlug {
    param([string]$Path)
    return (Split-Path $Path -Leaf)
}

function Get-WpConfig {
    $wpConfigPath = Join-Path $ScriptDir "wp-plugin-config.json"
    if (-not (Test-Path $wpConfigPath)) {
        Write-Host "  ERROR: wp-plugin-config.json not found at: $wpConfigPath" -ForegroundColor Red
        Write-Host "  Create it with site URL, username, and app password." -ForegroundColor Yellow
        return $null
    }
    return (Get-Content $wpConfigPath -Raw | ConvertFrom-Json)
}

# ============================================================================
# HELP
# ============================================================================
if ($Help) {
    Write-Banner "Upload Plugin V3 — Parallel Multi-Plugin Uploader"
    Write-Host "USAGE:" -ForegroundColor Yellow
    Write-Host "  .\upload-plugin-v3.ps1 -p ""path1, path2, path3""    # Upload multiple plugins in parallel"
    Write-Host "  .\upload-plugin-v3.ps1 -p ""path1"" -Activate         # Upload and activate"
    Write-Host "  .\upload-plugin-v3.ps1 -s                            # Check status of default plugin"
    Write-Host "  .\upload-plugin-v3.ps1 -s -p ""path""                  # Check status of specific plugin"
    Write-Host ""
    Write-Host "FLAGS:" -ForegroundColor Yellow
    Write-Host "  -p,  -PluginPaths   Comma-separated list of plugin folder paths"
    Write-Host "  -s,  -Status        Check plugin status on remote site instead of uploading"
    Write-Host "  -c,  -Concurrency   Max parallel uploads (default: 4)"
    Write-Host "       -Activate      Activate plugins after upload"
    Write-Host "       -SkipGitPull   Skip git pull in each plugin folder"
    Write-Host "  -h,  -Help          Show this help"
    Write-Host ""
    Write-Host "EXAMPLES:" -ForegroundColor Yellow
    Write-Host '  .\upload-plugin-v3.ps1 -p "C:\dev\plugin-a, C:\dev\plugin-b"'
    Write-Host '  .\upload-plugin-v3.ps1 -p "C:\dev\my-plugin" -s'
    Write-Host '  .\upload-plugin-v3.ps1 -s'
    Write-Host ""
    exit 0
}

# ============================================================================
# VALIDATE
# ============================================================================

if (-not (Test-Path $V2Script)) {
    Write-Host "  ERROR: upload-plugin-v2.ps1 not found at: $V2Script" -ForegroundColor Red
    exit 1
}

$wpConfig = Get-WpConfig
if (-not $wpConfig) { exit 1 }

# Parse plugin paths
$paths = @()
if ($PluginPaths -ne "") {
    $paths = $PluginPaths -split ',' | ForEach-Object { $_.Trim() } | Where-Object { $_ -ne "" }
}

# ============================================================================
# STATUS MODE
# ============================================================================
if ($Status) {
    Write-Banner "Plugin Status Check"
    
    $siteUrl = $wpConfig.wordPressSiteURL.TrimEnd('/')
    $authHeader = "Basic " + [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes("$($wpConfig.username):$($wpConfig.appPassword)"))
    $statusEndpoint = "$siteUrl/wp-json/riseup-asia-uploader/v1/status"

    Write-Step "Site: $siteUrl" "Gray"
    Write-Host ""

    try {
        $headers = @{
            "Authorization" = $authHeader
            "Accept" = "application/json"
        }
        $response = Invoke-RestMethod -Uri $statusEndpoint -Method GET -Headers $headers -TimeoutSec 15

        # If we have specific plugin paths, filter to those
        if ($paths.Count -gt 0) {
            $slugs = $paths | ForEach-Object { Get-PluginSlug $_ }
            Write-Step "Checking status for: $($slugs -join ', ')" "Yellow"
            Write-Host ""

            foreach ($slug in $slugs) {
                # Try to find plugin in status response
                $found = $false
                if ($response.Results) {
                    foreach ($result in $response.Results) {
                        if ($result.plugin -and $result.plugin -like "*$slug*") {
                            $statusLabel = if ($result.status -eq "active") { "ACTIVE" } else { "INACTIVE" }
                            $statusColor = if ($result.status -eq "active") { "Green" } else { "Yellow" }
                            Write-Host "  📦 " -NoNewline
                            Write-Host $slug -ForegroundColor White -NoNewline
                            Write-Host " — " -NoNewline
                            Write-Host $statusLabel -ForegroundColor $statusColor -NoNewline
                            if ($result.version) { Write-Host " v$($result.version)" -ForegroundColor Gray -NoNewline }
                            Write-Host ""
                            $found = $true
                            break
                        }
                    }
                }
                if (-not $found) {
                    Write-Host "  📦 " -NoNewline
                    Write-Host $slug -ForegroundColor White -NoNewline
                    Write-Host " — " -NoNewline
                    Write-Host "NOT FOUND" -ForegroundColor Red
                }
            }
        } else {
            # Show full status
            Write-Step "Plugin version: $($response.version)" "White"
            Write-Step "PHP version: $($response.php_version)" "Gray"
            Write-Step "WordPress version: $($response.wp_version)" "Gray"
            if ($response.plugin_name) { Write-Step "Name: $($response.plugin_name)" "Gray" }
            Write-Host ""
            Write-Host "  Status: " -NoNewline
            Write-Host "OK" -ForegroundColor Green
        }
    } catch {
        Write-Host "  ❌ Failed to query status endpoint" -ForegroundColor Red
        Write-Host "  $($_.Exception.Message)" -ForegroundColor DarkGray
        exit 1
    }

    Write-Host ""
    exit 0
}

# ============================================================================
# UPLOAD MODE — Parallel execution
# ============================================================================

if ($paths.Count -eq 0) {
    Write-Host "  ERROR: No plugin paths provided. Use -p ""path1, path2""" -ForegroundColor Red
    exit 1
}

# Validate all paths first
$validPaths = @()
foreach ($p in $paths) {
    $resolvedPath = if ([System.IO.Path]::IsPathRooted($p)) { $p } else { Join-Path (Get-Location) $p }
    if (-not (Test-Path $resolvedPath)) {
        Write-Host "  ⚠️  Path not found, skipping: $resolvedPath" -ForegroundColor Yellow
    } else {
        $validPaths += $resolvedPath
    }
}

if ($validPaths.Count -eq 0) {
    Write-Host "  ERROR: No valid plugin paths found." -ForegroundColor Red
    exit 1
}

Write-Banner "Parallel Plugin Upload ($($validPaths.Count) plugins, concurrency: $Concurrency)"

foreach ($p in $validPaths) {
    Write-Step "$(Get-PluginSlug $p)  →  $p" "Gray"
}
Write-Host ""

# Build jobs
$jobs = @()
foreach ($p in $validPaths) {
    $slug = Get-PluginSlug $p
    $configCopy = $wpConfig | ConvertTo-Json -Compress | ConvertFrom-Json
    $configCopy.pluginFolderPath = $p
    $jsonStr = $configCopy | ConvertTo-Json -Compress

    $scriptBlock = {
        param($V2Path, $JsonConfig, $DoActivate, $DoSkipGit)
        $args = @("-JsonConfig", $JsonConfig)
        if ($DoActivate) { $args += "-Activate" }
        if ($DoSkipGit) { $args += "-SkipGitPull" }
        & $V2Path @args
    }

    Write-Step "Starting job: $slug" "Cyan"
    $job = Start-Job -ScriptBlock $scriptBlock -ArgumentList $V2Script, $jsonStr, $Activate.IsPresent, $SkipGitPull.IsPresent
    $job | Add-Member -NotePropertyName PluginSlug -NotePropertyValue $slug
    $job | Add-Member -NotePropertyName PluginPath -NotePropertyValue $p
    $job | Add-Member -NotePropertyName StartTime -NotePropertyValue ([System.Diagnostics.Stopwatch]::StartNew())
    $jobs += $job

    # Throttle concurrency
    while (($jobs | Where-Object { $_.State -eq 'Running' }).Count -ge $Concurrency) {
        Start-Sleep -Milliseconds 500
    }
}

# Wait for all jobs to complete
Write-Host ""
Write-Step "Waiting for all uploads to complete..." "Yellow"
$jobs | Wait-Job | Out-Null

# Collect results
Write-Host ""
Write-Host "  ── Results ──" -ForegroundColor Cyan
Write-Host ""

$successCount = 0
$failCount = 0

foreach ($job in $jobs) {
    $output = Receive-Job -Job $job -ErrorAction SilentlyContinue
    $duration = $job.StartTime.Elapsed.TotalSeconds
    $slug = $job.PluginSlug

    if ($job.State -eq 'Completed') {
        # Check output for failure indicators
        $outputStr = ($output | Out-String)
        if ($outputStr -match "FAILED|ERROR|error" -and $outputStr -notmatch "SUCCESS|success|uploaded successfully") {
            Write-Result $slug $false "Upload reported errors" $duration
            $failCount++
        } else {
            Write-Result $slug $true "Upload complete" $duration
            $successCount++
        }
    } else {
        $errorInfo = $job.ChildJobs[0].JobStateInfo.Reason
        $detail = if ($errorInfo) { $errorInfo.Message } else { "Job state: $($job.State)" }
        Write-Result $slug $false $detail $duration
        $failCount++
    }

    Remove-Job -Job $job -Force
}

# Summary
$totalTime = $TotalStopwatch.Elapsed
Write-Host ""
Write-Host "  ── Summary ──" -ForegroundColor Cyan
Write-Host "  Total:     $($validPaths.Count) plugins" -ForegroundColor White
Write-Host "  Succeeded: $successCount" -ForegroundColor Green
if ($failCount -gt 0) {
    Write-Host "  Failed:    $failCount" -ForegroundColor Red
}
Write-Host "  Duration:  $("{0:F1}" -f $totalTime.TotalSeconds)s" -ForegroundColor Gray
Write-Host ""

if ($failCount -gt 0) { exit 1 }
exit 0
