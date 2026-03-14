# Module: mode-upload-default-all-sites.ps1
# Upload default plugin to all sites: -u -as
# ZIPs and uploads ONLY the default uploader plugin to all enabled sites (parallel).
# This excludes QUpload and other plugins — only the default uploader is deployed.
# Dot-sourced by run.ps1 — expects all helpers, plugin-helpers, zip-*, upload-*, summary-printer loaded.
# Expects: $site, $exclude, $sync, $ScriptDir, $Config

function Invoke-UploadDefaultAllSitesMode {
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  Upload Default Plugin to All Sites (-u -as)" -ForegroundColor Cyan
    if ($sync) {
        Write-Host "  Mode: SEQUENTIAL (-sync)" -ForegroundColor Yellow
    } else {
        Write-Host "  Mode: PARALLEL (use -sync for sequential)" -ForegroundColor DarkGray
    }
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""

    # Validate sites config
    if (-not $Config.wpPlugins -or -not $Config.wpPlugins.sites -or $Config.wpPlugins.sites.Count -eq 0) {
        Write-Host "ERROR: No sites configured in powershell.json (wpPlugins.sites)" -ForegroundColor Red
        Write-Host "Add a 'sites' array with Base64-encoded credentials." -ForegroundColor Yellow
        exit 1
    }

    Show-ConfiguredSites

    # ── Resolve default uploader ───────────────────────────────────────────
    $defaultUploader = $null
    if ($Config.wpPlugins -and $Config.wpPlugins.defaultUploader) {
        $defaultUploader = $Config.wpPlugins.defaultUploader
    }
    if (-not $defaultUploader -or -not $Config.wpPlugins.plugins.$defaultUploader) {
        Write-Host "ERROR: No default uploader configured in powershell.json (wpPlugins.defaultUploader)" -ForegroundColor Red
        exit 1
    }

    $pluginConfig = $Config.wpPlugins.plugins.$defaultUploader
    $pluginPath = Resolve-RelativePath $pluginConfig.path

    if (-not (Test-Path $pluginPath)) {
        Write-Host "ERROR: Plugin folder not found: $pluginPath" -ForegroundColor Red
        exit 1
    }

    # ── Parse -xs for site exclusion ───────────────────────────────────────
    $excludedSiteNames = @()
    if ($exclude -ne "") {
        $excludeItems = @($exclude -split ',' | ForEach-Object { $_.Trim() })
        $allSiteNames = @($Config.wpPlugins.sites | ForEach-Object { $_.name })
        $excludedSiteNames = @($excludeItems | Where-Object { $_ -in $allSiteNames })
        $unmatched = @($excludeItems | Where-Object { $_ -notin $allSiteNames })
        if ($unmatched.Count -gt 0) {
            Write-Host "  WARNING: Unrecognized exclusion(s): $($unmatched -join ', ')" -ForegroundColor Yellow
        }
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

    # Build plugin folder array (single item — the default uploader)
    $pluginFolder = Get-Item $pluginPath
    $pluginFolders = @($pluginFolder)

    # Determine what is excluded (everything except the default uploader)
    $wpPluginsDir = Join-Path $ScriptDir "wp-plugins"
    $allPluginDirs = @(Get-ChildItem -Path $wpPluginsDir -Directory | Where-Object {
        $_.Name -ne "scripts" -and (Test-Path (Join-Path $_.FullName "*.php"))
    })
    $excludedNames = @($allPluginDirs | Where-Object { $_.Name -ne $defaultUploader } | ForEach-Object { $_.Name })

    Write-Host ""
    Write-Host "  Preparing $($pluginFolders.Count) plugin(s):" -ForegroundColor Cyan
    Write-Host "    - $($pluginFolder.Name)" -ForegroundColor Gray
    Write-Host "  Excluded: $($excludedNames -join ', ')" -ForegroundColor DarkGray
    Write-Host ""

    Clear-PluginZips

    # ── Phase 1: ZIP ───────────────────────────────────────────────────────
    $zipResults = Invoke-ParallelPluginZip -PluginFolders $pluginFolders -Sequential:$true

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

    if (-not $zipByPlugin.ContainsKey($pluginFolder.Name)) {
        Write-Host "ERROR: Failed to ZIP $($pluginFolder.Name)" -ForegroundColor Red
        exit 1
    }

    # ── Phase 2: Upload ───────────────────────────────────────────────────
    $uploadLogsDir = Join-Path $ScriptDir "logs" "u-as-upload"
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
