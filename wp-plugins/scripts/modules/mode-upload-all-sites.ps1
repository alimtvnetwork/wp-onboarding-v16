# Module: mode-upload-all-sites.ps1
# Multi-site upload mode: -uas (upload all plugins to all configured sites)
# Thin orchestrator — delegates to zip-parallel.ps1, upload-parallel.ps1, summary-printer.ps1
# Dot-sourced by run.ps1 — expects all helpers, plugin-helpers, zip-*, upload-*, summary-printer loaded.
# Expects: $site, $index, $exclude, $sync, $ScriptDir, $Config

function Invoke-UploadAllSitesMode {
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Magenta
    Write-Host "  Multi-Site Upload Mode (-uas)" -ForegroundColor Magenta
    if ($sync) {
        Write-Host "  Mode: SEQUENTIAL (-sync)" -ForegroundColor Yellow
    } else {
        Write-Host "  Mode: PARALLEL (use -sync for sequential)" -ForegroundColor DarkGray
    }
    Write-Host "========================================" -ForegroundColor Magenta
    Write-Host ""

    # Validate sites config
    if (-not $Config.wpPlugins -or -not $Config.wpPlugins.sites -or $Config.wpPlugins.sites.Count -eq 0) {
        Write-Host "ERROR: No sites configured in powershell.json (wpPlugins.sites)" -ForegroundColor Red
        Write-Host "Add a 'sites' array with Base64-encoded credentials." -ForegroundColor Yellow
        exit 1
    }

    Show-ConfiguredSites

    # ── Parse -xs for dual-purpose exclusion (sites AND plugins) ──────────
    $excludedSiteNames = @()
    $excludedPluginSlugs = @()

    if ($exclude -ne "") {
        $excludeItems = @($exclude -split ',' | ForEach-Object { $_.Trim() })
        $allSiteNames = @($Config.wpPlugins.sites | ForEach-Object { $_.name })

        # We detect plugin slugs after discovery, so collect site exclusions now
        $excludedSiteNames = @($excludeItems | Where-Object { $_ -in $allSiteNames })
        $remainingExcludes = @($excludeItems | Where-Object { $_ -notin $allSiteNames })
        # Remaining items are treated as plugin slug exclusions
        $excludedPluginSlugs = $remainingExcludes
    }

    # ── Filter target sites ────────────────────────────────────────────────
    $targetSites = @(Resolve-TargetSites -Index $index -SiteName $site -ExcludedSiteNames $excludedSiteNames -AllSites $Config.wpPlugins.sites)

    if ($targetSites.Count -eq 0) {
        Write-Host "No enabled sites found." -ForegroundColor Yellow
        exit 0
    }

    # ── Verify QUpload script ──────────────────────────────────────────────
    $quploadScript = Join-Path $ScriptDir "wp-plugins" "scripts" "upload-plugin-U-Q.ps1"
    if (-not (Test-Path $quploadScript)) {
        Write-Host "ERROR: upload-plugin-U-Q.ps1 not found at: $quploadScript" -ForegroundColor Red
        exit 1
    }

    # ── Discover plugins (skipPlugins only, no hardcoded QUpload exclusion) ──
    $discovery = Get-UploadablePlugins -ExtraSkipList $excludedPluginSlugs
    $pluginFolders = $discovery.Plugins
    $skipList = $discovery.SkipList

    if ($excludedPluginSlugs.Count -gt 0) {
        # Warn about unmatched plugin exclusions
        $actualPluginNames = @($pluginFolders | ForEach-Object { $_.Name })
        $unmatched = @($excludedPluginSlugs | Where-Object { $_ -notin $actualPluginNames -and $_ -notin $skipList })
        if ($unmatched.Count -gt 0) {
            Write-Host "  WARNING: Unrecognized exclusion(s): $($unmatched -join ', ')" -ForegroundColor Yellow
        }
    }

    if ($pluginFolders.Count -eq 0) {
        Write-Host "No plugins found to upload." -ForegroundColor Yellow
        exit 0
    }

    Write-Host ""
    Write-Host "  Preparing $($pluginFolders.Count) plugin(s):" -ForegroundColor Cyan
    foreach ($folder in $pluginFolders) {
        Write-Host "    - $($folder.Name)" -ForegroundColor Gray
    }
    Write-Host "  Excluded: $($skipList -join ', ')" -ForegroundColor DarkGray
    Write-Host ""

    Clear-PluginZips

    # ── Phase 0+1: PHP Syntax Check + ZIP (parallel) ──────────────────────
    # PHP check and ZIP run concurrently — ZIP proceeds regardless of PHP errors.
    # Failed PHP plugins are excluded from upload only, not from ZIP.
    Write-Host ""
    Write-Host "  ── Phase 0+1: PHP Check + ZIP (concurrent) ───────────────" -ForegroundColor Cyan

    if ($sync) {
        # Sequential mode: run PHP check first, then ZIP
        $phpResults = Invoke-ParallelPhpCheck -PluginFolders $pluginFolders -Sequential
        Write-Host ""
        Write-Host "  ── Phase 1: ZIP ──────────────────────────────────────────" -ForegroundColor Cyan
        $zipResults = Invoke-ParallelPluginZip -PluginFolders $pluginFolders -Sequential
    } else {
        # Parallel mode: start both as background job groups
        # Start PHP check jobs
        $phpResults = $null
        $phpJob = Start-Job -Name "phase0-php" -ScriptBlock {
            param($ModulePath, $PluginPaths)
            . $ModulePath
            # Reconstruct folder objects from paths
            $folders = @($PluginPaths | ForEach-Object { Get-Item $_ })
            Invoke-ParallelPhpCheck -PluginFolders $folders -Sequential
        } -ArgumentList (Join-Path $ScriptDir "wp-plugins" "scripts" "modules" "php-check-parallel.ps1"), @($pluginFolders | ForEach-Object { $_.FullName })

        # Run ZIP in foreground (it has its own parallel jobs)
        Write-Host ""
        Write-Host "  ── Phase 1: ZIP ──────────────────────────────────────────" -ForegroundColor Cyan
        $zipResults = Invoke-ParallelPluginZip -PluginFolders $pluginFolders

        # Collect PHP results (job output already printed the Phase 0 header and per-plugin results)
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
        Write-Host "  These plugins will be excluded from upload." -ForegroundColor Yellow
    }

    Write-ZipSummary -ZipResults $zipResults

    # Build ZIP lookup (exclude PHP-failed plugins from upload)
    $zipByPlugin = @{}
    $versionByPlugin = @{}
    foreach ($zipInfo in $zipResults) {
        if ($zipInfo.Status -eq "OK" -and $zipInfo.Slug -notin $failedSlugs) {
            $zipByPlugin[$zipInfo.Slug] = $zipInfo.Path
            $versionByPlugin[$zipInfo.Slug] = $zipInfo.Version
        }
    }

    # Filter plugin folders to only those with valid ZIPs and passing PHP check
    $uploadablePlugins = @($pluginFolders | Where-Object { $zipByPlugin.ContainsKey($_.Name) })

    if ($uploadablePlugins.Count -eq 0) {
        Write-Host "  No plugins available for upload (all failed PHP check or ZIP)." -ForegroundColor Red
        exit 1
    }

    $pluginFolders = $uploadablePlugins

    # ── Phase 2: Upload ───────────────────────────────────────────────────
    $uploadLogsDir = Join-Path $ScriptDir "logs" "uas-upload"
    if (Test-Path $uploadLogsDir) {
        $existingLogs = @(Get-ChildItem -Path $uploadLogsDir -File)
        if ($existingLogs.Count -gt 0) {
            Write-Host "  Clearing $($existingLogs.Count) previous log file(s) from $uploadLogsDir" -ForegroundColor DarkGray
            Remove-Item -Path (Join-Path $uploadLogsDir "*") -Force
        }
    } else {
        New-Item -ItemType Directory -Path $uploadLogsDir -Force | Out-Null
    }
    $logStamp = Get-Date -Format "yyyyMMdd-HHmmss"

    $globalResults = Invoke-ParallelPluginUpload -TargetSites $targetSites -PluginFolders $pluginFolders -ZipByPlugin $zipByPlugin -VersionByPlugin $versionByPlugin -QUploadScript $quploadScript -UploadLogsDir $uploadLogsDir -LogStamp $logStamp -Sequential:$sync -VerboseMode:$verbose

    # ── Phase 3: Summary ──────────────────────────────────────────────────
    $failCount = ($globalResults | Where-Object { $_.Status -match "FAIL" }).Count

    Write-UploadSummary -Results $globalResults -TotalSites $targetSites.Count -TotalPlugins $pluginFolders.Count -FailureLogsDir $(if ($failCount -gt 0) { $uploadLogsDir } else { "" })

    exit $(if ($failCount -eq 0) { 0 } else { 1 })
}
