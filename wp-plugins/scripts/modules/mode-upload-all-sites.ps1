# Module: mode-upload-all-sites.ps1
# Multi-site upload mode: -uas (upload all plugins to all configured sites)
# Thin orchestrator — delegates to zip-parallel.ps1, upload-parallel.ps1, summary-printer.ps1
# Dot-sourced by run.ps1 — expects all helpers, plugin-helpers, zip-*, upload-*, summary-printer loaded.
# Expects: $site, $exclude, $sync, $ScriptDir, $Config

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
    $targetSites = @()
    if ($site -ne "") {
        $matchedSite = $Config.wpPlugins.sites | Where-Object { $_.name -eq $site }
        if (-not $matchedSite) {
            Write-Host "ERROR: Site '$site' not found in configuration." -ForegroundColor Red
            Write-Host "Available sites:" -ForegroundColor Yellow
            foreach ($s in $Config.wpPlugins.sites) { Write-Host "  - $($s.name)" -ForegroundColor Gray }
            exit 1
        }
        $targetSites += $matchedSite
        Write-Host "  Target site: $site" -ForegroundColor Cyan
    } elseif ($excludedSiteNames.Count -gt 0) {
        $allEnabled = @($Config.wpPlugins.sites | Where-Object { $_.enabled -ne $false })
        $targetSites = @($allEnabled | Where-Object { $_.name -notin $excludedSiteNames })
        Write-Host "  Target: $($targetSites.Count) site(s) (excluded sites: $($excludedSiteNames -join ', '))" -ForegroundColor Cyan
    } else {
        $targetSites = @($Config.wpPlugins.sites | Where-Object { $_.enabled -ne $false })
        Write-Host "  Target: All enabled sites ($($targetSites.Count))" -ForegroundColor Cyan
    }

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

    # ── Phase 1: ZIP ───────────────────────────────────────────────────────
    $zipResults = Invoke-ParallelPluginZip -PluginFolders $pluginFolders -Sequential:$sync

    Write-ZipSummary -ZipResults $zipResults

    # Build ZIP lookup
    $zipByPlugin = @{}
    $versionByPlugin = @{}
    foreach ($zipInfo in $zipResults) {
        if ($zipInfo.Status -eq "OK") {
            $zipByPlugin[$zipInfo.Slug] = $zipInfo.Path
            $versionByPlugin[$zipInfo.Slug] = $zipInfo.Version
        }
    }

    $missingZipPlugins = @()
    foreach ($folder in $pluginFolders) {
        if (-not $zipByPlugin.ContainsKey($folder.Name)) {
            $missingZipPlugins += $folder.Name
        }
    }

    if ($missingZipPlugins.Count -gt 0) {
        Write-Host "ERROR: Missing ZIP for plugin(s): $($missingZipPlugins -join ', ')" -ForegroundColor Red
        exit 1
    }

    # ── Phase 2: Upload ───────────────────────────────────────────────────
    $uploadLogsDir = Join-Path $ScriptDir "logs" "uas-upload"
    if (-not (Test-Path $uploadLogsDir)) {
        New-Item -ItemType Directory -Path $uploadLogsDir -Force | Out-Null
    }
    $logStamp = Get-Date -Format "yyyyMMdd-HHmmss"

    $globalResults = Invoke-ParallelPluginUpload -TargetSites $targetSites -PluginFolders $pluginFolders -ZipByPlugin $zipByPlugin -VersionByPlugin $versionByPlugin -QUploadScript $quploadScript -UploadLogsDir $uploadLogsDir -LogStamp $logStamp -Sequential:$sync

    # ── Phase 3: Summary ──────────────────────────────────────────────────
    $failCount = ($globalResults | Where-Object { $_.Status -match "FAIL" }).Count

    Write-UploadSummary -Results $globalResults -TotalSites $targetSites.Count -TotalPlugins $pluginFolders.Count -FailureLogsDir $(if ($failCount -gt 0) { $uploadLogsDir } else { "" })

    exit $(if ($failCount -eq 0) { 0 } else { 1 })
}
