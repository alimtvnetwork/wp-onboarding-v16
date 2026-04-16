# Module: mode-custom-upload.ps1
# Handles -ucp (upload custom plugin) mode for run.ps1
# Supports comma-separated slugs: .\run.ps1 -ucp alim,other-plugin -a
# Dot-sourced by run.ps1 — expects $ScriptDir to be set.

function Invoke-CustomPluginUploadMode {
    param(
        [string]$PluginSlug = "",
        [switch]$AllSites = $false,
        [string]$SiteName = "",
        [switch]$ListPlugins = $false,
        [switch]$AllPlugins = $false,
        [switch]$ShowHelp = $false,
        [switch]$VerboseMode = $false
    )

    $customUploadScript = Join-Path (Join-Path (Join-Path $ScriptDir "wp-plugins") "scripts") "upload-custom-plugin.ps1"

    if (-not (Test-Path $customUploadScript)) {
        Write-Host "ERROR: upload-custom-plugin.ps1 not found at: $customUploadScript" -ForegroundColor Red
        exit 1
    }

    # --- Help / List (no slug needed) ---
    if ($ShowHelp) {
        & $customUploadScript -Help
        exit 0
    }

    if ($ListPlugins) {
        & $customUploadScript -List
        exit 0
    }

    # --- All plugins mode: read all slugs from config ---
    if ($AllPlugins) {
        $configPath = Join-Path (Join-Path (Join-Path $ScriptDir "wp-plugins") "scripts") "custom-plugins.json"
        if (-not (Test-Path $configPath)) {
            Write-Host "ERROR: custom-plugins.json not found at: $configPath" -ForegroundColor Red
            exit 1
        }
        $config = Get-Content $configPath -Raw | ConvertFrom-Json
        if (-not $config.plugins -or $config.plugins.Count -eq 0) {
            Write-Host "ERROR: No plugins defined in custom-plugins.json" -ForegroundColor Red
            exit 1
        }
        $slugs = @($config.plugins | ForEach-Object { $_.slug })
        $PluginSlug = $slugs -join ','
        Write-Host ""
        Write-Host "  All plugins mode: $($slugs -join ', ') ($($slugs.Count) plugins)" -ForegroundColor Cyan
        Write-Host ""
    }

    if ([string]::IsNullOrWhiteSpace($PluginSlug)) {
        Write-Host "ERROR: Plugin slug required. Usage: .\run.ps1 -ucp <slug>" -ForegroundColor Red
        Write-Host "  Multiple: .\run.ps1 -ucp slug1,slug2" -ForegroundColor Yellow
        Write-Host "  All:      .\run.ps1 -ucp -ap" -ForegroundColor Yellow
        Write-Host "  List:     .\run.ps1 -ucp -list" -ForegroundColor Yellow
        exit 2
    }

    # --- Parse comma-separated slugs ---
    $slugs = $PluginSlug -split ',' | ForEach-Object { $_.Trim() } | Where-Object { $_ -ne "" }
    $isMulti = $slugs.Count -gt 1

    if ($isMulti) {
        Write-Host ""
        Write-Host "========================================" -ForegroundColor Cyan
        Write-Host "  Batch Custom Plugin Upload ($($slugs.Count) plugins)" -ForegroundColor Cyan
        Write-Host "========================================" -ForegroundColor Cyan
        Write-Host "  Plugins: $($slugs -join ', ')" -ForegroundColor White
        Write-Host ""
    }

    $batchSuccess = 0
    $batchFail = 0

    foreach ($slug in $slugs) {
        if ($isMulti) {
            $slugIndex = [array]::IndexOf($slugs, $slug) + 1
            Write-Host "--- [$slugIndex/$($slugs.Count)] $slug ---" -ForegroundColor Cyan
        }

        $scriptArgs = @("-Slug", $slug)

        if ($AllSites) {
            $scriptArgs += "-All"
        } elseif ($SiteName -ne "") {
            $scriptArgs += @("-Site", $SiteName)
        }

        if ($VerboseMode) {
            $scriptArgs += "-VerboseOutput"
        }

        & $customUploadScript @scriptArgs
        $exitCode = $LASTEXITCODE

        if ($exitCode -eq 0) {
            $batchSuccess++
        } else {
            $batchFail++
        }
    }

    # --- Batch summary ---
    if ($isMulti) {
        Write-Host ""
        Write-Host "========================================" -ForegroundColor Cyan
        if ($batchFail -eq 0) {
            Write-Host "  Batch: $batchSuccess/$($slugs.Count) plugins completed successfully" -ForegroundColor Green
        } else {
            Write-Host "  Batch: $batchSuccess succeeded, $batchFail failed (out of $($slugs.Count))" -ForegroundColor Red
        }
        Write-Host "========================================" -ForegroundColor Cyan
        Write-Host ""
    }

    if ($batchFail -gt 0) {
        exit 5
    }
    exit 0
}
