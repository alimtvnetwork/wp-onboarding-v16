# Module: mode-upload-all-sites.ps1
# Multi-site upload mode: -uas (upload all plugins to all configured sites)
# Supports -sync for sequential uploads (default: parallel).
# Dot-sourced by run.ps1 — expects all helpers and plugin-helpers loaded.
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

    # Filter sites
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
    } elseif ($exclude -ne "") {
        $excludeNames = @($exclude -split ',' | ForEach-Object { $_.Trim() })
        $allEnabled = @($Config.wpPlugins.sites | Where-Object { $_.enabled -ne $false })
        $targetSites = @($allEnabled | Where-Object { $_.name -notin $excludeNames })
        $excludedSites = @($allEnabled | Where-Object { $_.name -in $excludeNames })

        if ($excludedSites.Count -eq 0) {
            Write-Host "WARNING: No matching sites found to exclude: $exclude" -ForegroundColor Yellow
            Write-Host "Available sites:" -ForegroundColor Yellow
            foreach ($s in $Config.wpPlugins.sites) { Write-Host "  - $($s.name)" -ForegroundColor Gray }
        }

        Write-Host "  Target: $($targetSites.Count) site(s) (excluded: $($excludeNames -join ', '))" -ForegroundColor Cyan
    } else {
        $targetSites = @($Config.wpPlugins.sites | Where-Object { $_.enabled -ne $false })
        Write-Host "  Target: All enabled sites ($($targetSites.Count))" -ForegroundColor Cyan
    }

    if ($targetSites.Count -eq 0) {
        Write-Host "No enabled sites found." -ForegroundColor Yellow
        exit 0
    }

    # Verify QUpload script
    $quploadScript = Join-Path $ScriptDir "wp-plugins" "scripts" "upload-plugin-U-Q.ps1"
    if (-not (Test-Path $quploadScript)) {
        Write-Host "ERROR: upload-plugin-U-Q.ps1 not found at: $quploadScript" -ForegroundColor Red
        exit 1
    }

    # Discover plugins
    $discovery = Get-UploadablePlugins
    $pluginFolders = $discovery.Plugins
    $skipList = $discovery.SkipList

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

    # ── ZIP phase ──────────────────────────────────────────────────────────
    $quploadZipScript = Join-Path $ScriptDir "wp-plugins" "scripts" "upload-plugin-U-Q.ps1"

    if ($sync) {
        # Sequential ZIP
        Write-Host "  Zipping $($pluginFolders.Count) plugin(s) sequentially..." -ForegroundColor Yellow
        $zipResults = @()
        foreach ($folder in $pluginFolders) {
            Write-Host "    [ZIP] $($folder.Name)..." -ForegroundColor DarkGray
            & $quploadZipScript -ZipOnly -PluginPath $folder.FullName -Quiet
            $wpPluginsDir = Join-Path $ScriptDir "wp-plugins"
            $zipFile = Get-ChildItem $wpPluginsDir -Filter "$($folder.Name)-v*.zip" -ErrorAction SilentlyContinue | Select-Object -First 1
            if ($zipFile) {
                $sizeKB = [math]::Round($zipFile.Length / 1024, 1)
                $versionMatch = [regex]::Match($zipFile.Name, '-v(\d+\.\d+\.\d+)\.zip$')
                $version = if ($versionMatch.Success) { $versionMatch.Groups[1].Value } else { "unknown" }
                $zipResults += @{ Slug = $folder.Name; Version = $version; Path = $zipFile.FullName; SizeKB = $sizeKB }
                Write-Host "    [ZIP] Done: $($folder.Name)-v$version ($sizeKB KB)" -ForegroundColor Green
            } else {
                Write-Host "    [ZIP] FAILED: $($folder.Name)" -ForegroundColor Red
            }
        }
    } else {
        # Parallel ZIP
        Write-Host "  Zipping $($pluginFolders.Count) plugin(s) in parallel..." -ForegroundColor Yellow
        $zipJobs = @()
        foreach ($folder in $pluginFolders) {
            $folderPath = $folder.FullName
            $folderName = $folder.Name
            Write-Host "    [ZIP] Starting: $folderName" -ForegroundColor DarkGray
            $zipJobs += Start-Job -Name "zip-$folderName" -ScriptBlock {
                param($Script, $PluginPath)
                & $Script -ZipOnly -PluginPath $PluginPath -Quiet
            } -ArgumentList $quploadZipScript, $folderPath
        }

        $zipResults = @()
        foreach ($job in $zipJobs) {
            $rawOutput = Receive-Job -Job $job -Wait 2>&1
            $folderName = $job.Name -replace '^zip-', ''
            $wpPluginsDir = Join-Path $ScriptDir "wp-plugins"
            $zipFile = Get-ChildItem $wpPluginsDir -Filter "$folderName-v*.zip" -ErrorAction SilentlyContinue | Select-Object -First 1
            if ($zipFile) {
                $sizeKB = [math]::Round($zipFile.Length / 1024, 1)
                $versionMatch = [regex]::Match($zipFile.Name, '-v(\d+\.\d+\.\d+)\.zip$')
                $version = if ($versionMatch.Success) { $versionMatch.Groups[1].Value } else { "unknown" }
                $zipResults += @{ Slug = $folderName; Version = $version; Path = $zipFile.FullName; SizeKB = $sizeKB }
                Write-Host "    [ZIP] Done: $folderName-v$version ($sizeKB KB)" -ForegroundColor Green
            } else {
                Write-Host "    [ZIP] FAILED: $folderName" -ForegroundColor Red
                if ($rawOutput) { Write-Host "      $rawOutput" -ForegroundColor DarkGray }
            }
            Remove-Job -Job $job -Force
        }
    }

    Write-Host ""

    # Build ZIP lookup (path + version)
    $zipByPlugin = @{}
    $versionByPlugin = @{}
    foreach ($zipInfo in $zipResults) {
        $zipByPlugin[$zipInfo.Slug] = $zipInfo.Path
        $versionByPlugin[$zipInfo.Slug] = $zipInfo.Version
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

    # ── Upload phase ───────────────────────────────────────────────────────
    $uploadLogsDir = Join-Path $ScriptDir "logs" "uas-upload"
    if (-not (Test-Path $uploadLogsDir)) {
        New-Item -ItemType Directory -Path $uploadLogsDir -Force | Out-Null
    }
    $logStamp = Get-Date -Format "yyyyMMdd-HHmmss"

    if ($sync) {
        Write-Host "  Uploading to $($targetSites.Count) site(s) sequentially..." -ForegroundColor Yellow
        Write-Host ""
        $globalResults = Invoke-UasSyncUpload -TargetSites $targetSites -PluginFolders $pluginFolders -ZipByPlugin $zipByPlugin -VersionByPlugin $versionByPlugin -QUploadScript $quploadScript -UploadLogsDir $uploadLogsDir -LogStamp $logStamp
    } else {
        Write-Host "  Uploading to $($targetSites.Count) site(s) in parallel..." -ForegroundColor Yellow
        Write-Host ""
        $globalResults = Invoke-UasParallelUpload -TargetSites $targetSites -PluginFolders $pluginFolders -ZipByPlugin $zipByPlugin -VersionByPlugin $versionByPlugin -QUploadScript $quploadScript -UploadLogsDir $uploadLogsDir -LogStamp $logStamp
    }

    # Summary
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Magenta
    Write-Host "  Multi-Site Upload Summary" -ForegroundColor Magenta
    Write-Host "========================================" -ForegroundColor Magenta
    $successCount = ($globalResults | Where-Object { $_.Status -eq "OK" }).Count
    $failCount = $globalResults.Count - $successCount
    foreach ($r in $globalResults) {
        $color = if ($r.Status -eq "OK") { "Green" } else { "Red" }
        $vLabel = if ($r.Version -and $r.Version -ne "unknown") { " v$($r.Version)" } else { "" }
        Write-Host "  [$($r.Site)] $($r.Plugin)${vLabel}: $($r.Status)" -ForegroundColor $color
    }
    Write-Host ""
    Write-Host "  Sites: $($targetSites.Count) | Plugins: $($pluginFolders.Count) | Success: $successCount | Failed: $failCount" -ForegroundColor $(if ($failCount -eq 0) { "Green" } else { "Yellow" })
    if ($failCount -gt 0) {
        Write-Host "  Failure logs: $uploadLogsDir" -ForegroundColor Yellow
    }
    Write-Host "========================================" -ForegroundColor Magenta

    exit $(if ($failCount -eq 0) { 0 } else { 1 })
}

# ── Sequential upload (one site+plugin at a time with full console output) ──
function Invoke-UasSyncUpload {
    param(
        $TargetSites,
        $PluginFolders,
        $ZipByPlugin,
        [string]$QUploadScript,
        [string]$UploadLogsDir,
        [string]$LogStamp
    )

    $results = @()

    foreach ($targetSite in $TargetSites) {
        $siteName = $targetSite.name
        $siteUrl = $targetSite.url

        Write-Host "  ========================================" -ForegroundColor Cyan
        Write-Host "  Site: $siteName ($siteUrl)" -ForegroundColor Cyan
        Write-Host "  ========================================" -ForegroundColor Cyan

        $cred = Get-DefaultSiteCredential $targetSite
        if (-not $cred) {
            Write-Host "  [$siteName] SKIPPED: No valid credentials" -ForegroundColor Red
            foreach ($folder in $PluginFolders) {
                $results += @{ Site = $siteName; Plugin = $folder.Name; Status = "SKIPPED (no creds)"; ExitCode = 1 }
            }
            continue
        }

        foreach ($folder in $PluginFolders) {
            $pluginName = $folder.Name
            $pluginFullPath = $folder.FullName
            $prebuiltZipPath = $ZipByPlugin[$pluginName]

            Write-Host ""
            Write-Host "    Plugin: $pluginName" -ForegroundColor Yellow
            Write-Host "    ZIP:    $prebuiltZipPath" -ForegroundColor DarkGray

            $uploadConfig = @{
                pluginFolderPath     = $pluginFullPath
                outputZipPath        = $prebuiltZipPath
                wordPressSiteURL     = $siteUrl.TrimEnd("/")
                username             = $cred.Username
                appPassword          = $cred.Password
                activateAfterInstall = $true
                deleteZipAfterUpload = $false
            }
            $jsonConfigStr = ($uploadConfig | ConvertTo-Json -Compress)

            $uploadExitCode = 1
            try {
                & $QUploadScript -jc $jsonConfigStr -a
                $uploadExitCode = $LASTEXITCODE
                if ($null -eq $uploadExitCode) { $uploadExitCode = 0 }
            } catch {
                Write-Host "    ERROR: $_" -ForegroundColor Red
                $uploadExitCode = 1
            }

            $isSuccess = ($uploadExitCode -eq 0)
            $status = if ($isSuccess) { "OK" } else { "FAILED (exit $uploadExitCode)" }
            $icon = if ($isSuccess) { "OK" } else { "FAILED" }
            $color = if ($isSuccess) { "Green" } else { "Red" }

            Write-Host "    Result: $icon" -ForegroundColor $color

            if (-not $isSuccess) {
                $safeSite = ($siteName -replace '[^A-Za-z0-9_.-]', '_')
                $safePlugin = ($pluginName -replace '[^A-Za-z0-9_.-]', '_')
                $failureLogPath = Join-Path $UploadLogsDir "$LogStamp-$safePlugin-$safeSite.log"
                $failureLog = @(
                    "Timestamp: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
                    "Site: $siteName"
                    "Plugin: $pluginName"
                    "ZIP: $prebuiltZipPath"
                    "ExitCode: $uploadExitCode"
                    ""
                    "Mode: SYNC (sequential)"
                ) -join "`r`n"
                $failureLog | Out-File -FilePath $failureLogPath -Encoding UTF8
                Write-Host "    Log: $failureLogPath" -ForegroundColor Yellow
            }

            $results += @{ Site = $siteName; Plugin = $pluginName; Status = $status; ExitCode = $uploadExitCode }
        }
    }

    return $results
}

# ── Parallel upload (background jobs) ────────────────────────────────────
function Invoke-UasParallelUpload {
    param(
        $TargetSites,
        $PluginFolders,
        $ZipByPlugin,
        [string]$QUploadScript,
        [string]$UploadLogsDir,
        [string]$LogStamp
    )

    $uploadJobs = @()

    foreach ($targetSite in $TargetSites) {
        $siteName = $targetSite.name
        $siteUrl = $targetSite.url
        $cred = Get-DefaultSiteCredential $targetSite

        if (-not $cred) {
            Write-Host "  [$siteName] SKIPPED: No valid credentials" -ForegroundColor Red
            continue
        }

        $decodedUsername = $cred.Username
        $decodedPassword = $cred.Password

        foreach ($folder in $PluginFolders) {
            $pluginName = $folder.Name
            $pluginFullPath = $folder.FullName
            $prebuiltZipPath = $ZipByPlugin[$pluginName]
            $jobName = "$pluginName->$siteName"
            $zipLabel = if ($prebuiltZipPath) { $prebuiltZipPath } else { "(no prebuilt ZIP)" }
            Write-Host "      [$jobName] ZIP: $zipLabel" -ForegroundColor DarkGray

            $uploadJobs += Start-Job -Name $jobName -ScriptBlock {
                param($QUploadScript, $PluginPath, $PrebuiltZipPath, $SiteUrl, $Username, $Password, $PluginName, $SiteName)

                $uploadConfig = @{
                    pluginFolderPath     = $PluginPath
                    outputZipPath        = $PrebuiltZipPath
                    wordPressSiteURL     = $SiteUrl.TrimEnd("/")
                    username             = $Username
                    appPassword          = $Password
                    activateAfterInstall = $true
                    deleteZipAfterUpload = $false
                }
                $jsonConfigStr = ($uploadConfig | ConvertTo-Json -Compress)

                $output = ""
                $invokeSucceeded = $false
                $resolvedExitCode = 1
                $nativeExitCode = $null

                try {
                    $ErrorActionPreference = "Stop"
                    $global:LASTEXITCODE = $null
                    $output = (& $QUploadScript -jc $jsonConfigStr -a 2>&1 | Out-String)
                    $invokeSucceeded = $true
                } catch {
                    $output = ($_ | Out-String).Trim()
                    if ([string]::IsNullOrWhiteSpace($output)) {
                        $output = $_.Exception.Message
                    }
                }

                $nativeExitCode = $LASTEXITCODE
                $hasNativeExitCode = ($null -ne $nativeExitCode -and "$nativeExitCode" -match '^-?\d+$')

                if ($hasNativeExitCode) {
                    $resolvedExitCode = [int]$nativeExitCode
                } elseif ($invokeSucceeded) {
                    $resolvedExitCode = 0
                }

                return @{
                    Site                = $SiteName
                    Plugin              = $PluginName
                    ZipPath             = $PrebuiltZipPath
                    ExitCode            = $resolvedExitCode
                    NativeExitCode      = $nativeExitCode
                    InvocationSucceeded = $invokeSucceeded
                    Output              = $output
                    Status              = if ($resolvedExitCode -eq 0) { "OK" } else { "FAILED (exit $resolvedExitCode)" }
                }
            } -ArgumentList $QUploadScript, $pluginFullPath, $prebuiltZipPath, $siteUrl, $decodedUsername, $decodedPassword, $pluginName, $siteName
        }
    }

    # Collect results
    $globalResults = @()

    foreach ($job in $uploadJobs) {
        $result = Receive-Job -Job $job -Wait | Select-Object -First 1

        if ($null -eq $result) {
            $result = @{
                Site                = "Unknown"
                Plugin              = $job.Name
                ZipPath             = ""
                ExitCode            = 1
                NativeExitCode      = $null
                InvocationSucceeded = $false
                Output              = "No result object returned by upload job."
                Status              = "FAILED (exit 1)"
            }
        }

        $globalResults += $result
        $isSuccess = ($result.ExitCode -eq 0)
        $icon = if ($isSuccess) { "OK" } else { "FAILED" }
        $color = if ($isSuccess) { "Green" } else { "Red" }

        Write-Host "  [$($result.Site)] $($result.Plugin): $icon" -ForegroundColor $color

        $isFailure = ($isSuccess -eq $false)
        if ($isFailure) {
            $safeSite = ($result.Site -replace '[^A-Za-z0-9_.-]', '_')
            $safePlugin = ($result.Plugin -replace '[^A-Za-z0-9_.-]', '_')
            $failureLogPath = Join-Path $UploadLogsDir "$LogStamp-$safePlugin-$safeSite.log"

            $failureLog = @(
                "Timestamp: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
                "Job: $($job.Name)"
                "Site: $($result.Site)"
                "Plugin: $($result.Plugin)"
                "ZIP: $($result.ZipPath)"
                "ExitCode: $($result.ExitCode)"
                "NativeExitCode: $($result.NativeExitCode)"
                "InvocationSucceeded: $($result.InvocationSucceeded)"
                "PowerShellJobState: $($job.State)"
                ""
                "Output:"
                $result.Output
            ) -join "`r`n"

            $failureLog | Out-File -FilePath $failureLogPath -Encoding UTF8
            Write-Host "    Log file: $failureLogPath" -ForegroundColor Yellow

            $outputLines = $result.Output -split "`n" | Where-Object { $_ -match '(failed|error|FAILED|Error|REST message|Root cause|Status:)' }
            foreach ($line in $outputLines) {
                $trimmed = $line.Trim()
                if ($trimmed -ne "") {
                    Write-Host "    $trimmed" -ForegroundColor Yellow
                }
            }

            if ($outputLines.Count -eq 0) {
                Write-Host "    No failure keyword matches found; check log file for full output." -ForegroundColor DarkYellow
            }
        }

        Remove-Job -Job $job -Force
    }

    return $globalResults
}
