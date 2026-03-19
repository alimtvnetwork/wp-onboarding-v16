# Module: mode-zip.ps1
# ZIP modes: -z, -za, -zas, -zq
# Dot-sourced by run.ps1 — expects all helpers and plugin-helpers loaded.

function Invoke-ZipMode {
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  ZIP Mode (-z)" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""

    Clear-PluginZips

    if ($pluginpath -ne "") {
        $zipPluginPath = $pluginpath
        if (-not [System.IO.Path]::IsPathRooted($zipPluginPath)) {
            $zipPluginPath = Join-Path $ScriptDir $zipPluginPath
        }
        if (-not (Test-Path $zipPluginPath)) {
            Write-Host "ERROR: Plugin folder not found: $zipPluginPath" -ForegroundColor Red
            exit 1
        }
        Write-Host "  Using custom plugin path: $zipPluginPath" -ForegroundColor Cyan
        New-PluginZip $zipPluginPath
    } else {
        $defaultPath = Get-DefaultUploaderPath
        New-PluginZip $defaultPath
    }

    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  ZIP complete!" -ForegroundColor Green
    Write-Host "========================================" -ForegroundColor Cyan

    exit 0
}

function Invoke-ZipAllMode {
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  ZIP All Mode (-za)" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""

    Clear-PluginZips

    $wpPluginsDir = Join-Path $ScriptDir "wp-plugins"
    if (-not (Test-Path $wpPluginsDir)) {
        Write-Host "ERROR: wp-plugins/ directory not found" -ForegroundColor Red
        exit 1
    }

    $skipList = @()
    if ($Config.wpPlugins -and $Config.wpPlugins.skipPlugins) {
        $skipList = @($Config.wpPlugins.skipPlugins)
    }

    $pluginFolders = Get-ChildItem $wpPluginsDir -Directory | Where-Object {
        if ($_.Name -in $skipList) { return $false }
        $phpFiles = Get-ChildItem $_.FullName -Filter "*.php" -File -ErrorAction SilentlyContinue
        $hasPluginHeader = $false
        foreach ($f in $phpFiles) {
            $head = Get-Content $f.FullName -Head 5 -ErrorAction SilentlyContinue
            if ($head -match "Plugin Name:") { $hasPluginHeader = $true; break }
        }
        $hasPluginHeader
    }

    if ($pluginFolders.Count -eq 0) {
        Write-Host "No WordPress plugins found in wp-plugins/" -ForegroundColor Yellow
        exit 0
    }

    Write-Host "  Found $($pluginFolders.Count) plugin(s) to ZIP:" -ForegroundColor Cyan
    foreach ($folder in $pluginFolders) {
        Write-Host "    - $($folder.Name)" -ForegroundColor Gray
    }
    Write-Host ""

    foreach ($folder in $pluginFolders) {
        New-PluginZip $folder.FullName
    }

    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  ZIP All complete!" -ForegroundColor Green
    Write-Host "========================================" -ForegroundColor Cyan

    exit 0
}

function Invoke-ZipAllParallelMode {
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  ZIP All Parallel Mode (-zas)" -ForegroundColor Cyan
    if ($sync) {
        Write-Host "  Mode: SEQUENTIAL (-sync)" -ForegroundColor Yellow
    } else {
        Write-Host "  Mode: PARALLEL (use -sync for sequential)" -ForegroundColor DarkGray
    }
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""

    $excludedPluginSlugs = @()
    if ($exclude -ne "") {
        $excludedPluginSlugs = @($exclude -split ',' | ForEach-Object { $_.Trim() })
    }

    $discovery = Get-UploadablePlugins -ExtraSkipList $excludedPluginSlugs
    $pluginFolders = $discovery.Plugins
    $skipList = $discovery.SkipList

    if ($pluginFolders.Count -eq 0) {
        Write-Host "No plugins found to ZIP." -ForegroundColor Yellow
        exit 0
    }

    Write-Host "  Preparing $($pluginFolders.Count) plugin(s):" -ForegroundColor Cyan
    foreach ($folder in $pluginFolders) {
        Write-Host "    - $($folder.Name)" -ForegroundColor Gray
    }
    Write-Host "  Excluded: $($skipList -join ', ')" -ForegroundColor DarkGray
    Write-Host ""

    Clear-PluginZips

    # ── Phase 0+1: PHP Check + ZIP (concurrent) ──────────────────────────
    Write-Host ""
    Write-Host "  ── Phase 0+1: PHP Check + ZIP (concurrent) ───────────────" -ForegroundColor Cyan

    if ($sync) {
        $phpResults = Invoke-ParallelPhpCheck -PluginFolders $pluginFolders -Sequential
        Write-Host ""
        Write-Host "  ── Phase 1: ZIP ──────────────────────────────────────────" -ForegroundColor Cyan
        $zipResults = Invoke-ParallelPluginZip -PluginFolders $pluginFolders -Sequential
    } else {
        $phpJob = Start-Job -Name "phase0-php" -ScriptBlock {
            param($ModulePath, $PluginPaths)
            . $ModulePath
            $folders = @($PluginPaths | ForEach-Object { Get-Item $_ })
            Invoke-ParallelPhpCheck -PluginFolders $folders -Sequential
        } -ArgumentList (Join-Path $ScriptDir "wp-plugins" "scripts" "modules" "php-check-parallel.ps1"), @($pluginFolders | ForEach-Object { $_.FullName })

        Write-Host ""
        Write-Host "  ── Phase 1: ZIP ──────────────────────────────────────────" -ForegroundColor Cyan
        $zipResults = Invoke-ParallelPluginZip -PluginFolders $pluginFolders

        $phpResults = Receive-Job -Job $phpJob -Wait
        Remove-Job -Job $phpJob -Force

        if ($null -eq $phpResults) {
            Write-Host ""
            Write-Host "  ── Phase 0: PHP Syntax Check ──────────────────────────" -ForegroundColor Cyan
            Write-Host "    [PHP] Warning: PHP check job returned no results" -ForegroundColor Yellow
            $phpResults = @()
        }
    }

    $failedSlugs = @($phpResults | Where-Object { $_.Status -eq "FAILED" } | ForEach-Object { $_.Slug })
    if ($failedSlugs.Count -gt 0) {
        Write-Host ""
        Write-Host "  PHP check failed for: $($failedSlugs -join ', ')" -ForegroundColor Red
        Write-Host "  ZIPs were still created. Errors logged above." -ForegroundColor Yellow
    }

    Write-ZipSummary -ZipResults $zipResults

    $zipFailCount = ($zipResults | Where-Object { $_.Status -ne "OK" }).Count
    $phpFailCount = $failedSlugs.Count

    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  ZIP All Parallel complete!" -ForegroundColor Green
    if ($phpFailCount -gt 0) {
        Write-Host "  PHP errors: $phpFailCount plugin(s) — review above" -ForegroundColor Yellow
    }
    Write-Host "========================================" -ForegroundColor Cyan

    exit $(if ($zipFailCount -eq 0 -and $phpFailCount -eq 0) { 0 } else { 1 })
}

function Invoke-ZipQUploadMode {
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  ZIP QUpload Mode (-zq)" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""

    Clear-PluginZips

    $qPath = Get-DefaultQUploaderPath
    New-PluginZip $qPath

    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  ZIP complete!" -ForegroundColor Green
    Write-Host "========================================" -ForegroundColor Cyan

    exit 0
}
