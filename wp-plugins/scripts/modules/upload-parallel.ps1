# Module: upload-parallel.ps1
# Upload orchestrator: plugin-sequential, site-parallel.
# For each plugin (non-cross-upload first), uploads to ALL sites in parallel, then waits.
# This prevents cross-upload race conditions where a plugin's API is called mid-update.
# Dot-sourced by run.ps1 - expects upload-single.ps1, plugin-helpers.ps1 loaded.
# Expects: $ScriptDir, $Config

function Get-PluginUploadOrder {
    param(
        [Parameter(Mandatory)][array]$PluginFolders
    )

    # Non-cross-upload plugins first (they use the default qupload-api/v1),
    # then cross-upload plugins (they depend on the other plugin's API being stable).
    $nonCrossUpload = @()
    $crossUpload = @()

    foreach ($folder in $PluginFolders) {
        $hasCrossMapping = $script:CrossUploadMap.ContainsKey($folder.Name)
        if ($hasCrossMapping) {
            $crossUpload += $folder
        } else {
            $nonCrossUpload += $folder
        }
    }

    return @($nonCrossUpload + $crossUpload)
}

function Invoke-ParallelPluginUpload {
    param(
        [Parameter(Mandatory)][array]$TargetSites,
        [Parameter(Mandatory)][array]$PluginFolders,
        [Parameter(Mandatory)][hashtable]$ZipByPlugin,
        [Parameter(Mandatory)][hashtable]$VersionByPlugin,
        [Parameter(Mandatory)][string]$QUploadScript,
        [Parameter(Mandatory)][string]$UploadLogsDir,
        [Parameter(Mandatory)][string]$LogStamp,
        [switch]$Sequential,
        [switch]$VerboseMode
    )

    if ($Sequential) {
        return Invoke-SequentialPluginUpload -TargetSites $TargetSites -PluginFolders $PluginFolders -ZipByPlugin $ZipByPlugin -VersionByPlugin $VersionByPlugin -QUploadScript $QUploadScript -UploadLogsDir $UploadLogsDir -LogStamp $LogStamp -VerboseMode:$VerboseMode
    }

    # Sort plugins: non-cross-upload first for stability
    $orderedPlugins = Get-PluginUploadOrder -PluginFolders $PluginFolders
    $totalJobs = $TargetSites.Count * $orderedPlugins.Count

    Write-Host "  Uploading $($orderedPlugins.Count) plugin(s) to $($TargetSites.Count) site(s) — plugin-sequential, site-parallel ($totalJobs total)..." -ForegroundColor Yellow
    Write-Host "  Order: $($orderedPlugins | ForEach-Object { $_.Name }) — non-cross-upload first" -ForegroundColor DarkGray
    Write-Host ""

    $allResults = @()
    $jobIndex = 0

    # ── Pre-resolve credentials per site ──────────────────────────────────
    $siteCredentials = @{}
    foreach ($targetSite in $TargetSites) {
        $siteName = $targetSite.name
        $cred = Get-DefaultSiteCredential $targetSite
        $siteCredentials[$siteName] = $cred

        if (-not $cred) {
            Write-Host "  [$siteName] WARNING: No valid credentials — will skip all plugins" -ForegroundColor Red
        } else {
            Write-Host "    Credential: $($targetSite.credential)" -ForegroundColor DarkGray
            Write-Host "    Username:   $($cred.Username)" -ForegroundColor DarkGray
        }
    }

    # ── Plugin-sequential loop ────────────────────────────────────────────
    foreach ($folder in $orderedPlugins) {
        $pluginName = $folder.Name
        $pluginFullPath = $folder.FullName
        $prebuiltZipPath = $ZipByPlugin[$pluginName]
        $pluginVersion = if ($VersionByPlugin.ContainsKey($pluginName)) { $VersionByPlugin[$pluginName] } else { "unknown" }
        $hasCrossMapping = $script:CrossUploadMap.ContainsKey($pluginName)
        $crossLabel = if ($hasCrossMapping) { " (cross-upload)" } else { "" }

        Write-Host ""
        Write-Host "  ── Plugin: $pluginName v$pluginVersion$crossLabel ──" -ForegroundColor Cyan

        # Skip if no ZIP
        if (-not $prebuiltZipPath) {
            Write-Host "    SKIPPED: No ZIP available" -ForegroundColor Yellow
            foreach ($targetSite in $TargetSites) {
                $allResults += @{
                    Index    = $jobIndex
                    Site     = $targetSite.name
                    SiteUrl  = $targetSite.url
                    Plugin   = $pluginName
                    Version  = $pluginVersion
                    Status   = "SKIPPED (no ZIP)"
                    ExitCode = 1
                    Output   = ""
                    Error    = "No ZIP file available"
                    Duration = 0
                }
                $jobIndex++
            }
            continue
        }

        # Launch parallel jobs for all sites
        $pluginJobs = @()
        $pluginPreAllocated = @()

        foreach ($targetSite in $TargetSites) {
            $siteName = $targetSite.name
            $siteUrl = $targetSite.url
            $cred = $siteCredentials[$siteName]
            $currentIndex = $jobIndex

            if (-not $cred) {
                $pluginPreAllocated += @{
                    Index    = $currentIndex
                    Site     = $siteName
                    SiteUrl  = $siteUrl
                    Plugin   = $pluginName
                    Version  = $pluginVersion
                    Status   = "SKIPPED (no creds)"
                    ExitCode = 1
                    Output   = ""
                    Error    = "No valid credentials"
                    Duration = 0
                }
                $jobIndex++
                continue
            }

            $decodedUsername = $cred.Username
            $decodedPassword = $cred.Password

            # Determine cross-upload API namespace
            $apiNamespace = Get-UploadApiNamespace -PluginSlug $pluginName -SiteUrl $siteUrl -Username $decodedUsername -Password $decodedPassword

            $jobName = "upload-$currentIndex-$pluginName-$siteName"
            $apiLabel = if ($apiNamespace -eq "qupload-api/v1") { "" } else { " [cross-upload: $apiNamespace]" }
            Write-Host "    [$siteName] ZIP: $prebuiltZipPath$apiLabel" -ForegroundColor DarkGray

            $pluginPreAllocated += @{
                Index    = $currentIndex
                Site     = $siteName
                SiteUrl  = $siteUrl
                Plugin   = $pluginName
                Version  = $pluginVersion
                Status   = "PENDING"
                ExitCode = $null
                Output   = ""
                Error    = $null
                Duration = 0
            }

            $pluginJobs += Start-Job -Name $jobName -ScriptBlock {
                param($QUploadScript, $PluginPath, $PrebuiltZipPath, $SiteUrl, $Username, $Password, $PluginName, $SiteName, $PluginVersion, $Index, $ApiNamespace, $IsVerbose)

                $sw = [System.Diagnostics.Stopwatch]::StartNew()

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

                try {
                    $ErrorActionPreference = "Stop"
                    $global:LASTEXITCODE = $null
                    $verboseFlag = @()
                    if ($IsVerbose) { $verboseFlag += "-vb" }
                    $output = (& $QUploadScript -jc $jsonConfigStr -a -api $ApiNamespace -spc @verboseFlag 2>&1 | Out-String)
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

                $sw.Stop()

                return @{
                    Index    = $Index
                    Site     = $SiteName
                    SiteUrl  = $SiteUrl
                    Plugin   = $PluginName
                    Version  = $PluginVersion
                    Status   = if ($resolvedExitCode -eq 0) { "OK" } else { "FAILED (exit $resolvedExitCode)" }
                    ExitCode = $resolvedExitCode
                    Output   = $output
                    Error    = $null
                    Duration = $sw.Elapsed.TotalSeconds
                }
            } -ArgumentList $QUploadScript, $pluginFullPath, $prebuiltZipPath, $siteUrl, $decodedUsername, $decodedPassword, $pluginName, $siteName, $pluginVersion, $currentIndex, $apiNamespace, $VerboseMode.IsPresent

            $jobIndex++
        }

        # ── Wait for all site jobs for this plugin ────────────────────────
        # Add skipped entries first
        $skippedForPlugin = @($pluginPreAllocated | Where-Object { $_.Status -match "SKIP" })
        $allResults += $skippedForPlugin

        foreach ($job in $pluginJobs) {
            $result = Receive-Job -Job $job -Wait | Select-Object -First 1

            if ($null -eq $result) {
                $idx = [int](($job.Name -split '-')[1])
                $fallback = $pluginPreAllocated | Where-Object { $_.Index -eq $idx } | Select-Object -First 1
                if ($fallback) {
                    $fallback.Status = "FAILED (job crashed)"
                    $fallback.ExitCode = 1
                    $fallback.Error = "Background job returned no result"
                    $result = $fallback
                } else {
                    $result = @{
                        Index    = $idx
                        Site     = "Unknown"
                        SiteUrl  = ""
                        Plugin   = $job.Name
                        Version  = "unknown"
                        Status   = "FAILED (job crashed)"
                        ExitCode = 1
                        Output   = "No result object returned by upload job."
                        Error    = "Job crashed"
                        Duration = 0
                    }
                }
            }

            $allResults += $result

            $isSuccess = ($result.ExitCode -eq 0)
            $color = if ($isSuccess) { "Green" } else { "Red" }
            $vLabel = if ($result.Version -and $result.Version -ne "unknown") { " v$($result.Version)" } else { "" }
            $icon = if ($isSuccess) { "OK" } else { "FAILED" }
            Write-Host "    [$($result.Site)] $($result.Plugin)${vLabel}: $icon" -ForegroundColor $color

            # Write failure log
            if (-not $isSuccess -and $result.Status -notmatch "SKIP") {
                $safeSite = ($result.Site -replace '[^A-Za-z0-9_.-]', '_')
                $safePlugin = ($result.Plugin -replace '[^A-Za-z0-9_.-]', '_')
                $failureLogPath = Join-Path $UploadLogsDir "$LogStamp-$safePlugin-$safeSite.log"

                $failureLog = @(
                    "Timestamp: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
                    "Job: $($job.Name)"
                    "Site: $($result.Site)"
                    "Plugin: $($result.Plugin)"
                    "ZIP: (from parallel job)"
                    "ExitCode: $($result.ExitCode)"
                    "Duration: $($result.Duration)s"
                    ""
                    "Output:"
                    $result.Output
                ) -join "`r`n"

                $failureLog | Out-File -FilePath $failureLogPath -Encoding UTF8
                Write-Host "      Log: $failureLogPath" -ForegroundColor Yellow

                $outputLines = $result.Output -split "`n" | Where-Object { $_ -match '(failed|error|FAILED|Error|REST message|Root cause|Status:)' }
                foreach ($line in $outputLines) {
                    $trimmed = $line.Trim()
                    if ($trimmed -ne "") { Write-Host "      $trimmed" -ForegroundColor Yellow }
                }
            }

            Remove-Job -Job $job -Force
        }

        # Plugin batch summary
        $pluginSuccessCount = ($allResults | Where-Object { $_.Plugin -eq $pluginName -and $_.ExitCode -eq 0 }).Count
        $pluginTotalSites = $TargetSites.Count
        $summaryColor = if ($pluginSuccessCount -eq $pluginTotalSites) { "Green" } else { "Yellow" }
        Write-Host ("    -- {0}: {1}/{2} sites OK --" -f $pluginName, $pluginSuccessCount, $pluginTotalSites) -ForegroundColor $summaryColor
    }

    # Sort by index for deterministic display order
    $orderedResults = @($allResults | Sort-Object { $_.Index })
    return $orderedResults
}

# ── Sequential upload (plugin-first, then sites — full console output) ──────
function Invoke-SequentialPluginUpload {
    param(
        [Parameter(Mandatory)][array]$TargetSites,
        [Parameter(Mandatory)][array]$PluginFolders,
        [Parameter(Mandatory)][hashtable]$ZipByPlugin,
        [Parameter(Mandatory)][hashtable]$VersionByPlugin,
        [Parameter(Mandatory)][string]$QUploadScript,
        [Parameter(Mandatory)][string]$UploadLogsDir,
        [Parameter(Mandatory)][string]$LogStamp
    )

    # Sort plugins: non-cross-upload first
    $orderedPlugins = Get-PluginUploadOrder -PluginFolders $PluginFolders

    Write-Host "  Uploading $($orderedPlugins.Count) plugin(s) to $($TargetSites.Count) site(s) sequentially..." -ForegroundColor Yellow
    Write-Host "  Order: $($orderedPlugins | ForEach-Object { $_.Name }) — non-cross-upload first" -ForegroundColor DarkGray

    $results = @()
    $jobIndex = 0

    foreach ($folder in $orderedPlugins) {
        $pluginName = $folder.Name
        $pluginFullPath = $folder.FullName
        $prebuiltZipPath = $ZipByPlugin[$pluginName]
        $pluginVersion = if ($VersionByPlugin.ContainsKey($pluginName)) { $VersionByPlugin[$pluginName] } else { "unknown" }

        Write-Host ""
        Write-Host "  ── Plugin: $pluginName v$pluginVersion ──" -ForegroundColor Cyan

        if (-not $prebuiltZipPath) {
            Write-Host "    SKIPPED: No ZIP available" -ForegroundColor Yellow
            foreach ($targetSite in $TargetSites) {
                $results += @{
                    Index    = $jobIndex
                    Site     = $targetSite.name
                    SiteUrl  = $targetSite.url
                    Plugin   = $pluginName
                    Version  = $pluginVersion
                    Status   = "SKIPPED (no ZIP)"
                    ExitCode = 1
                    Output   = ""
                    Error    = "No ZIP file available"
                    Duration = 0
                }
                $jobIndex++
            }
            continue
        }

        foreach ($targetSite in $TargetSites) {
            $siteName = $targetSite.name
            $siteUrl = $targetSite.url

            Write-Host ""
            Write-Host "    Site: $siteName ($siteUrl)" -ForegroundColor Yellow
            Write-Host "    ZIP:  $prebuiltZipPath" -ForegroundColor DarkGray

            $cred = Get-DefaultSiteCredential $targetSite
            if (-not $cred) {
                Write-Host "    SKIPPED: No valid credentials" -ForegroundColor Red
                $results += @{
                    Index    = $jobIndex
                    Site     = $siteName
                    SiteUrl  = $siteUrl
                    Plugin   = $pluginName
                    Version  = $pluginVersion
                    Status   = "SKIPPED (no creds)"
                    ExitCode = 1
                    Output   = ""
                    Error    = "No valid credentials"
                    Duration = 0
                }
                $jobIndex++
                continue
            }

            $sw = [System.Diagnostics.Stopwatch]::StartNew()

            $uploadExitCode = 1
            try {
                $result = Invoke-SinglePluginUpload -QUploadScript $QUploadScript -PluginPath $pluginFullPath -ZipPath $prebuiltZipPath -SiteUrl $siteUrl -Username $cred.Username -Password $cred.Password -PluginSlug $pluginName -SiteName $siteName -PluginVersion $pluginVersion
                $uploadExitCode = $result.ExitCode
            } catch {
                Write-Host "    ERROR: $_" -ForegroundColor Red
                $uploadExitCode = 1
            }

            $sw.Stop()

            $isSuccess = ($uploadExitCode -eq 0)
            $icon = if ($isSuccess) { "OK" } else { "FAILED" }
            $color = if ($isSuccess) { "Green" } else { "Red" }
            Write-Host "    Result: $icon ({0:N1}s)" -f $sw.Elapsed.TotalSeconds -ForegroundColor $color

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
                    "Mode: SYNC (sequential)"
                ) -join "`r`n"
                $failureLog | Out-File -FilePath $failureLogPath -Encoding UTF8
                Write-Host "    Log: $failureLogPath" -ForegroundColor Yellow
            }

            $results += @{
                Index    = $jobIndex
                Site     = $siteName
                SiteUrl  = $siteUrl
                Plugin   = $pluginName
                Version  = $pluginVersion
                Status   = if ($isSuccess) { "OK" } else { "FAILED (exit $uploadExitCode)" }
                ExitCode = $uploadExitCode
                Output   = ""
                Error    = $null
                Duration = $sw.Elapsed.TotalSeconds
            }
            $jobIndex++
        }
    }

    return $results
}
