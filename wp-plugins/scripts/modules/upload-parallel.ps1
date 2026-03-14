# Module: upload-parallel.ps1
# Parallel upload orchestrator: launches all plugin x site combinations as simultaneous jobs.
# Dot-sourced by run.ps1 - expects upload-single.ps1, plugin-helpers.ps1 loaded.
# Expects: $ScriptDir, $Config

function Invoke-ParallelPluginUpload {
    param(
        [Parameter(Mandatory)][array]$TargetSites,
        [Parameter(Mandatory)][array]$PluginFolders,
        [Parameter(Mandatory)][hashtable]$ZipByPlugin,
        [Parameter(Mandatory)][hashtable]$VersionByPlugin,
        [Parameter(Mandatory)][string]$QUploadScript,
        [Parameter(Mandatory)][string]$UploadLogsDir,
        [Parameter(Mandatory)][string]$LogStamp,
        [switch]$Sequential
    )

    if ($Sequential) {
        return Invoke-SequentialPluginUpload -TargetSites $TargetSites -PluginFolders $PluginFolders -ZipByPlugin $ZipByPlugin -VersionByPlugin $VersionByPlugin -QUploadScript $QUploadScript -UploadLogsDir $UploadLogsDir -LogStamp $LogStamp
    }

    $totalJobs = $TargetSites.Count * $PluginFolders.Count
    Write-Host "  Uploading $($PluginFolders.Count) plugin(s) to $($TargetSites.Count) site(s) in parallel ($totalJobs job(s))..." -ForegroundColor Yellow
    Write-Host ""

    $uploadJobs = @()
    $preAllocated = @()
    $jobIndex = 0

    foreach ($targetSite in $TargetSites) {
        $siteName = $targetSite.name
        $siteUrl = $targetSite.url
        $cred = Get-DefaultSiteCredential $targetSite

        if (-not $cred) {
            Write-Host "  [$siteName] SKIPPED: No valid credentials" -ForegroundColor Red
            foreach ($folder in $PluginFolders) {
                $pluginVersion = if ($VersionByPlugin.ContainsKey($folder.Name)) { $VersionByPlugin[$folder.Name] } else { "unknown" }
                $preAllocated += @{
                    Index    = $jobIndex
                    Site     = $siteName
                    SiteUrl  = $siteUrl
                    Plugin   = $folder.Name
                    Version  = $pluginVersion
                    Status   = "SKIPPED (no creds)"
                    ExitCode = 1
                    Output   = ""
                    Error    = "No valid credentials"
                    Duration = 0
                }
                $jobIndex++
            }
            continue
        }

        $decodedUsername = $cred.Username
        $decodedPassword = $cred.Password

        foreach ($folder in $PluginFolders) {
            $pluginName = $folder.Name
            $pluginFullPath = $folder.FullName
            $prebuiltZipPath = $ZipByPlugin[$pluginName]
            $pluginVersion = if ($VersionByPlugin.ContainsKey($pluginName)) { $VersionByPlugin[$pluginName] } else { "unknown" }
            $currentIndex = $jobIndex

            if (-not $prebuiltZipPath) {
                Write-Host "  [$pluginName->$siteName] SKIPPED: No ZIP available" -ForegroundColor Yellow
                $preAllocated += @{
                    Index    = $currentIndex
                    Site     = $siteName
                    SiteUrl  = $siteUrl
                    Plugin   = $pluginName
                    Version  = $pluginVersion
                    Status   = "SKIPPED (no ZIP)"
                    ExitCode = 1
                    Output   = ""
                    Error    = "No ZIP file available"
                    Duration = 0
                }
                $jobIndex++
                continue
            }

            $jobName = "upload-$currentIndex-$pluginName-$siteName"
            Write-Host "    [$pluginName->$siteName] ZIP: $prebuiltZipPath" -ForegroundColor DarkGray

            $preAllocated += @{
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

            $uploadJobs += Start-Job -Name $jobName -ScriptBlock {
                param($QUploadScript, $PluginPath, $PrebuiltZipPath, $SiteUrl, $Username, $Password, $PluginName, $SiteName, $PluginVersion, $Index)

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
            } -ArgumentList $QUploadScript, $pluginFullPath, $prebuiltZipPath, $siteUrl, $decodedUsername, $decodedPassword, $pluginName, $siteName, $pluginVersion, $currentIndex

            $jobIndex++
        }
    }

    # Collect job results
    $completedResults = @()

    # Add pre-resolved skipped entries
    $skippedResults = @($preAllocated | Where-Object { $_.Status -match "SKIP" })
    $completedResults += $skippedResults

    foreach ($job in $uploadJobs) {
        $result = Receive-Job -Job $job -Wait | Select-Object -First 1

        if ($null -eq $result) {
            $idx = [int](($job.Name -split '-')[1])
            $fallback = $preAllocated | Where-Object { $_.Index -eq $idx } | Select-Object -First 1
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

        $completedResults += $result

        $isSuccess = ($result.ExitCode -eq 0)
        $color = if ($isSuccess) { "Green" } else { "Red" }
        $vLabel = if ($result.Version -and $result.Version -ne "unknown") { " v$($result.Version)" } else { "" }
        $icon = if ($isSuccess) { "OK" } else { "FAILED" }
        Write-Host "  [$($result.Site)] $($result.Plugin)${vLabel}: $icon" -ForegroundColor $color

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
            Write-Host "    Log file: $failureLogPath" -ForegroundColor Yellow

            $outputLines = $result.Output -split "`n" | Where-Object { $_ -match '(failed|error|FAILED|Error|REST message|Root cause|Status:)' }
            foreach ($line in $outputLines) {
                $trimmed = $line.Trim()
                if ($trimmed -ne "") { Write-Host "    $trimmed" -ForegroundColor Yellow }
            }
        }

        Remove-Job -Job $job -Force
    }

    # Sort by index for deterministic display order
    $orderedResults = @($completedResults | Sort-Object { $_.Index })
    return $orderedResults
}

# ── Sequential upload (one site+plugin at a time with full console output) ──
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

    $results = @()
    $jobIndex = 0

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
                $pluginVersion = if ($VersionByPlugin.ContainsKey($folder.Name)) { $VersionByPlugin[$folder.Name] } else { "unknown" }
                $results += @{
                    Index    = $jobIndex
                    Site     = $siteName
                    SiteUrl  = $siteUrl
                    Plugin   = $folder.Name
                    Version  = $pluginVersion
                    Status   = "SKIPPED (no creds)"
                    ExitCode = 1
                    Output   = ""
                    Error    = "No valid credentials"
                    Duration = 0
                }
                $jobIndex++
            }
            continue
        }

        foreach ($folder in $PluginFolders) {
            $pluginName = $folder.Name
            $pluginFullPath = $folder.FullName
            $prebuiltZipPath = $ZipByPlugin[$pluginName]
            $pluginVersion = if ($VersionByPlugin.ContainsKey($pluginName)) { $VersionByPlugin[$pluginName] } else { "unknown" }

            Write-Host ""
            Write-Host "    Plugin: $pluginName" -ForegroundColor Yellow
            Write-Host "    ZIP:    $prebuiltZipPath" -ForegroundColor DarkGray

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
