# Module: mode-plugin-status.ps1
# Plugin status check mode: -ps (single site) / -pas (all sites)
# Checks plugin health via REST status endpoints, optionally retrieves error logs.
# Dot-sourced by run.ps1 — expects helpers, plugin-helpers, summary-printer loaded.
# Expects: $site, $index, $exclude, $sync, $ScriptDir, $Config, $pluginstatus, $pluginstatusall, $error

function Invoke-PluginStatusMode {
    $isAllSites = $pluginstatusall

    Write-Host ""
    Write-Host "========================================" -ForegroundColor Magenta
    Write-Host "  Plugin Status Check $( if ($isAllSites) { '(-pas)' } else { '(-ps)' } )" -ForegroundColor Magenta
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
        exit 1
    }

    Show-ConfiguredSites

    # ── Parse exclusions ───────────────────────────────────────────────
    $excludedSiteNames = @()
    if ($exclude -ne "") {
        $excludeItems = @($exclude -split ',' | ForEach-Object { $_.Trim() })
        $allSiteNames = @($Config.wpPlugins.sites | ForEach-Object { $_.name })
        $excludedSiteNames = @($excludeItems | Where-Object { $_ -in $allSiteNames })
    }

    # ── Resolve target sites ───────────────────────────────────────────
    if ($isAllSites) {
        $targetSites = @(Resolve-TargetSites -Index $index -SiteName $site -ExcludedSiteNames $excludedSiteNames -AllSites $Config.wpPlugins.sites)
    } else {
        # Single site: use index 0 or specified site
        if ($index -ne "" -or $site -ne "") {
            $targetSites = @(Resolve-TargetSites -Index $index -SiteName $site -ExcludedSiteNames $excludedSiteNames -AllSites $Config.wpPlugins.sites)
        } else {
            # Default: first enabled site
            $targetSites = @(Resolve-TargetSites -Index "1" -SiteName "" -ExcludedSiteNames @() -AllSites $Config.wpPlugins.sites)
        }
    }

    if ($targetSites.Count -eq 0) {
        Write-Host "No enabled sites found." -ForegroundColor Yellow
        exit 0
    }

    # ── Discover plugins ───────────────────────────────────────────────
    $discovery = Get-UploadablePlugins
    $pluginFolders = $discovery.Plugins

    if ($pluginFolders.Count -eq 0) {
        Write-Host "No plugins found." -ForegroundColor Yellow
        exit 0
    }

    # ── Prepare log folder ─────────────────────────────────────────────
    $statusLogsDir = Join-Path $ScriptDir "logs" "plugin-status"
    if (Test-Path $statusLogsDir) {
        $existingLogs = @(Get-ChildItem -Path $statusLogsDir -File -ErrorAction SilentlyContinue)
        if ($existingLogs.Count -gt 0) {
            Write-Host "  Clearing $($existingLogs.Count) previous log file(s)" -ForegroundColor DarkGray
            Remove-Item -Path (Join-Path $statusLogsDir "*") -Force
        }
    } else {
        New-Item -ItemType Directory -Path $statusLogsDir -Force | Out-Null
    }

    # ── Plugin → REST namespace mapping ────────────────────────────────
    $pluginNamespaces = @{
        "qupload"               = "qupload-api/v1"
        "riseup-asia-uploader"  = "riseup-asia-api/v1"
    }

    $totalChecks = $targetSites.Count * $pluginFolders.Count
    Write-Host "  Checking $($pluginFolders.Count) plugin(s) on $($targetSites.Count) site(s) ($totalChecks checks)..." -ForegroundColor Yellow
    Write-Host ""

    # ── Run checks ─────────────────────────────────────────────────────
    if ($sync) {
        $allResults = Invoke-SequentialPluginStatusCheck -TargetSites $targetSites -PluginFolders $pluginFolders -PluginNamespaces $pluginNamespaces -StatusLogsDir $statusLogsDir
    } else {
        $allResults = Invoke-ParallelPluginStatusCheck -TargetSites $targetSites -PluginFolders $pluginFolders -PluginNamespaces $pluginNamespaces -StatusLogsDir $statusLogsDir
    }

    # ── Summary ────────────────────────────────────────────────────────
    Write-PluginStatusSummary -Results $allResults -TotalSites $targetSites.Count -TotalPlugins $pluginFolders.Count -StatusLogsDir $statusLogsDir

    $failCount = ($allResults | Where-Object { $_.Status -ne "OK" -and $_.Status -ne "SKIPPED" }).Count
    exit $(if ($failCount -eq 0) { 0 } else { 1 })
}

function Invoke-SinglePluginStatusCheck {
    param(
        [Parameter(Mandatory)][hashtable]$SiteConfig,
        [Parameter(Mandatory)][string]$PluginSlug,
        [Parameter(Mandatory)][string]$Namespace,
        [Parameter(Mandatory)][string]$StatusLogsDir,
        [switch]$IncludeErrors
    )

    $sw = [System.Diagnostics.Stopwatch]::StartNew()

    $result = @{
        Site       = $SiteConfig.Name
        SiteUrl    = $SiteConfig.Url
        Plugin     = $PluginSlug
        Version    = ""
        Status     = "ERROR"
        HttpStatus = 0
        Message    = ""
        Duration   = 0
        ErrorLog   = ""
        Stacktrace = ""
    }

    # Get credential
    $credential = Get-DefaultSiteCredential -SiteConfig $SiteConfig
    if (-not $credential) {
        $result.Status = "AUTH_FAILED"
        $result.Message = "No credentials available"
        $sw.Stop()
        $result.Duration = $sw.Elapsed.TotalSeconds
        return $result
    }

    $siteUrl = $SiteConfig.Url.TrimEnd('/')
    $statusUrl = "$siteUrl/wp-json/$Namespace/status"
    $authHeader = "Basic " + [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes("$($credential.Username):$($credential.Password)"))

    # ── Status check ──────────────────────────────────────────────────
    try {
        $response = Invoke-WebRequest -Uri $statusUrl -Method GET -Headers @{ Authorization = $authHeader } -UseBasicParsing -TimeoutSec 30 -ErrorAction Stop
        $result.HttpStatus = $response.StatusCode

        if ($response.StatusCode -eq 200) {
            $result.Status = "OK"
            try {
                $body = $response.Content | ConvertFrom-Json
                if ($body.Version) { $result.Version = $body.Version }
                elseif ($body.version) { $result.Version = $body.version }
            } catch { }
        }
    } catch {
        $statusCode = 0
        if ($_.Exception.Response) {
            $statusCode = [int]$_.Exception.Response.StatusCode
        }
        $result.HttpStatus = $statusCode

        switch ($statusCode) {
            404 { $result.Status = "NOT_INSTALLED"; $result.Message = "Plugin not found (404)" }
            401 { $result.Status = "AUTH_FAILED"; $result.Message = "Unauthorized (401)" }
            403 { $result.Status = "AUTH_FAILED"; $result.Message = "Forbidden (403)" }
            0   { $result.Status = "UNREACHABLE"; $result.Message = "Site unreachable: $($_.Exception.Message)" }
            default { $result.Status = "ERROR"; $result.Message = "HTTP $statusCode`: $($_.Exception.Message)" }
        }
    }

    # ── Error log retrieval (always when status is OK) ───────────────
    if ($result.Status -eq "OK") {
        $logsUrl = "$siteUrl/wp-json/$Namespace/logs/retrieve?include_info_log=false&include_error_log=true&include_stacktrace=true&max_lines=100"

        try {
            $logsResponse = Invoke-WebRequest -Uri $logsUrl -Method GET -Headers @{ Authorization = $authHeader } -UseBasicParsing -TimeoutSec 30 -ErrorAction Stop
            $logsBody = $logsResponse.Content | ConvertFrom-Json

            $safeSiteName = ($SiteConfig.Name -replace '[^a-zA-Z0-9_-]', '_')

            # Error log
            if ($logsBody.ErrorLog -and $logsBody.ErrorLog.Exists -and $logsBody.ErrorLog.Lines -gt 0) {
                $errorContent = $logsBody.ErrorLog.Content
                $result.ErrorLog = "$($logsBody.ErrorLog.Lines) lines"
                $errorFile = Join-Path $StatusLogsDir "$safeSiteName-$PluginSlug-error.txt"
                $errorContent | Out-File -FilePath $errorFile -Encoding UTF8
            } elseif ($logsBody.ErrorLog) {
                $result.ErrorLog = "No errors"
            }

            # Stacktrace log
            if ($logsBody.StacktraceLog -and $logsBody.StacktraceLog.Exists -and $logsBody.StacktraceLog.Lines -gt 0) {
                $stackContent = $logsBody.StacktraceLog.Content
                $result.Stacktrace = "$($logsBody.StacktraceLog.Lines) lines"
                $stackFile = Join-Path $StatusLogsDir "$safeSiteName-$PluginSlug-stacktrace.txt"
                $stackContent | Out-File -FilePath $stackFile -Encoding UTF8
            } elseif ($logsBody.StacktraceLog) {
                $result.Stacktrace = "No errors"
            }
        } catch {
            $restStatus = 0
            $restMessage = $_.Exception.Message
            if ($_.Exception.Response) {
                $restStatus = [int]$_.Exception.Response.StatusCode
            }
            $result.ErrorLog = if ($restStatus -gt 0) { "REST $restStatus`: $restMessage" } else { "REST error: $restMessage" }
        }
    }

    $sw.Stop()
    $result.Duration = $sw.Elapsed.TotalSeconds
    return $result
}

function Invoke-ParallelPluginStatusCheck {
    param(
        [Parameter(Mandatory)][array]$TargetSites,
        [Parameter(Mandatory)][array]$PluginFolders,
        [Parameter(Mandatory)][hashtable]$PluginNamespaces,
        [Parameter(Mandatory)][string]$StatusLogsDir
    )

    $statusModulePath = Join-Path $ScriptDir "wp-plugins" "scripts" "modules" "mode-plugin-status.ps1"
    $pluginHelpersPath = Join-Path $ScriptDir "wp-plugins" "scripts" "modules" "plugin-helpers.ps1"
    $helpersPath = Join-Path $ScriptDir "wp-plugins" "scripts" "modules" "helpers.ps1"

    $jobs = @()
    $jobIndex = 0

    foreach ($siteConfig in $TargetSites) {
        $credential = Get-DefaultSiteCredential -SiteConfig $siteConfig

        foreach ($folder in $PluginFolders) {
            $pluginSlug = $folder.Name
            $namespace = $PluginNamespaces[$pluginSlug]
            if (-not $namespace) { continue }

            $currentIndex = $jobIndex
            $siteUrl = $siteConfig.url.TrimEnd('/')
            $siteName = $siteConfig.name
            $username = if ($credential) { $credential.Username } else { "" }
            $password = if ($credential) { $credential.Password } else { "" }
            $isErrorMode = [bool]$script:errorFlag

            $jobs += Start-Job -Name "status-$currentIndex-$pluginSlug-$siteName" -ScriptBlock {
                param($SiteUrl, $SiteName, $PluginSlug, $Namespace, $Username, $Password, $StatusLogsDir, $Index, $IncludeErrors)

                $sw = [System.Diagnostics.Stopwatch]::StartNew()

                $result = @{
                    Site       = $SiteName
                    SiteUrl    = $SiteUrl
                    Plugin     = $PluginSlug
                    Version    = ""
                    Status     = "ERROR"
                    HttpStatus = 0
                    Message    = ""
                    Duration   = 0
                    ErrorLog   = ""
                    Stacktrace = ""
                    Index      = $Index
                }

                if (-not $Username) {
                    $result.Status = "AUTH_FAILED"
                    $result.Message = "No credentials"
                    $sw.Stop()
                    $result.Duration = $sw.Elapsed.TotalSeconds
                    return $result
                }

                $authHeader = "Basic " + [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes("${Username}:${Password}"))
                $statusUrl = "$SiteUrl/wp-json/$Namespace/status"

                try {
                    $response = Invoke-WebRequest -Uri $statusUrl -Method GET -Headers @{ Authorization = $authHeader } -UseBasicParsing -TimeoutSec 30 -ErrorAction Stop
                    $result.HttpStatus = $response.StatusCode

                    if ($response.StatusCode -eq 200) {
                        $result.Status = "OK"
                        try {
                            $body = $response.Content | ConvertFrom-Json
                            if ($body.Version) { $result.Version = $body.Version }
                            elseif ($body.version) { $result.Version = $body.version }
                        } catch { }
                    }
                } catch {
                    $statusCode = 0
                    if ($_.Exception.Response) {
                        $statusCode = [int]$_.Exception.Response.StatusCode
                    }
                    $result.HttpStatus = $statusCode

                    switch ($statusCode) {
                        404 { $result.Status = "NOT_INSTALLED"; $result.Message = "Plugin not found (404)" }
                        401 { $result.Status = "AUTH_FAILED"; $result.Message = "Unauthorized (401)" }
                        403 { $result.Status = "AUTH_FAILED"; $result.Message = "Forbidden (403)" }
                        0   { $result.Status = "UNREACHABLE"; $result.Message = "Site unreachable: $($_.Exception.Message)" }
                        default { $result.Status = "ERROR"; $result.Message = "HTTP $statusCode" }
                    }
                }

                # Error log retrieval (always when status is OK)
                if ($result.Status -eq "OK") {
                    $logsUrl = "$SiteUrl/wp-json/$Namespace/logs/retrieve?include_info_log=false&include_error_log=true&include_stacktrace=true&max_lines=100"
                    try {
                        $logsResponse = Invoke-WebRequest -Uri $logsUrl -Method GET -Headers @{ Authorization = $authHeader } -UseBasicParsing -TimeoutSec 30 -ErrorAction Stop
                        $logsBody = $logsResponse.Content | ConvertFrom-Json

                        $safeSiteName = ($SiteName -replace '[^a-zA-Z0-9_-]', '_')

                        if ($logsBody.ErrorLog -and $logsBody.ErrorLog.Exists -and $logsBody.ErrorLog.Lines -gt 0) {
                            $result.ErrorLog = "$($logsBody.ErrorLog.Lines) lines"
                            $errorFile = Join-Path $StatusLogsDir "$safeSiteName-$PluginSlug-error.txt"
                            $logsBody.ErrorLog.Content | Out-File -FilePath $errorFile -Encoding UTF8
                        } elseif ($logsBody.ErrorLog) {
                            $result.ErrorLog = "No errors"
                        }

                        if ($logsBody.StacktraceLog -and $logsBody.StacktraceLog.Exists -and $logsBody.StacktraceLog.Lines -gt 0) {
                            $result.Stacktrace = "$($logsBody.StacktraceLog.Lines) lines"
                            $stackFile = Join-Path $StatusLogsDir "$safeSiteName-$PluginSlug-stacktrace.txt"
                            $logsBody.StacktraceLog.Content | Out-File -FilePath $stackFile -Encoding UTF8
                        } elseif ($logsBody.StacktraceLog) {
                            $result.Stacktrace = "No errors"
                        }
                    } catch {
                        $restStatus = 0
                        $restMessage = $_.Exception.Message
                        if ($_.Exception.Response) {
                            $restStatus = [int]$_.Exception.Response.StatusCode
                        }
                        $result.ErrorLog = if ($restStatus -gt 0) { "REST $restStatus`: $restMessage" } else { "REST error: $restMessage" }
                    }
                }

                $sw.Stop()
                $result.Duration = $sw.Elapsed.TotalSeconds
                return $result
            } -ArgumentList $siteUrl, $siteName, $pluginSlug, $namespace, $username, $password, $StatusLogsDir, $currentIndex, $isErrorMode

            $jobIndex++
        }
    }

    # Collect results
    $results = @()
    foreach ($job in $jobs) {
        $result = Receive-Job -Job $job -Wait | Select-Object -First 1

        if ($null -eq $result) {
            $idx = [int](($job.Name -split '-')[1])
            $result = @{
                Index      = $idx
                Site       = "unknown"
                SiteUrl    = ""
                Plugin     = "unknown"
                Version    = ""
                Status     = "FAILED"
                HttpStatus = 0
                Message    = "Background job crashed"
                Duration   = 0
                ErrorLog   = ""
                Stacktrace = ""
            }
        }

        $results += $result

        $color = switch ($result.Status) {
            "OK"            { "Green" }
            "NOT_INSTALLED" { "Yellow" }
            "SKIPPED"       { "Yellow" }
            default         { "Red" }
        }
        $symbol = if ($result.Status -eq "OK") { "+" } elseif ($result.Status -match "SKIP|NOT_INSTALLED") { "o" } else { "x" }
        $vLabel = if ($result.Version) { "v$($result.Version)" } else { "-" }
        $duration = "{0:N1}s" -f $result.Duration
        Write-Host "    $symbol [$($result.Site)] $($result.Plugin) $vLabel $($result.Status) $duration" -ForegroundColor $color

        Remove-Job -Job $job -Force
    }

    return @($results | Sort-Object { $_.Index })
}

function Invoke-SequentialPluginStatusCheck {
    param(
        [Parameter(Mandatory)][array]$TargetSites,
        [Parameter(Mandatory)][array]$PluginFolders,
        [Parameter(Mandatory)][hashtable]$PluginNamespaces,
        [Parameter(Mandatory)][string]$StatusLogsDir
    )

    $results = @()
    $jobIndex = 0

    foreach ($siteConfig in $TargetSites) {
        foreach ($folder in $PluginFolders) {
            $pluginSlug = $folder.Name
            $namespace = $PluginNamespaces[$pluginSlug]
            if (-not $namespace) { continue }

            $result = Invoke-SinglePluginStatusCheck -SiteConfig $siteConfig -PluginSlug $pluginSlug -Namespace $namespace -StatusLogsDir $StatusLogsDir -IncludeErrors:([bool]$script:errorFlag)
            $result.Index = $jobIndex

            $color = switch ($result.Status) {
                "OK"            { "Green" }
                "NOT_INSTALLED" { "Yellow" }
                default         { "Red" }
            }
            $symbol = if ($result.Status -eq "OK") { "+" } elseif ($result.Status -match "SKIP|NOT_INSTALLED") { "o" } else { "x" }
            $vLabel = if ($result.Version) { "v$($result.Version)" } else { "-" }
            $duration = "{0:N1}s" -f $result.Duration
            Write-Host "    $symbol [$($result.Site)] $($result.Plugin) $vLabel $($result.Status) $duration" -ForegroundColor $color

            $results += $result
            $jobIndex++
        }
    }

    return $results
}

function Write-PluginStatusSummary {
    param(
        [Parameter(Mandatory)][array]$Results,
        [Parameter(Mandatory)][int]$TotalSites,
        [Parameter(Mandatory)][int]$TotalPlugins,
        [string]$StatusLogsDir
    )

    Write-Host ""
    Write-Host "========================================" -ForegroundColor Magenta
    Write-Host "  Plugin Status Summary" -ForegroundColor Magenta
    Write-Host "========================================" -ForegroundColor Magenta

    $sorted = @($Results | Sort-Object { $_.Index })
    $grouped = $sorted | Group-Object -Property Site

    foreach ($group in $grouped) {
        $siteName = $group.Name
        $siteUrl = if ($group.Group[0].SiteUrl) { $group.Group[0].SiteUrl } else { "" }
        $siteLabel = if ($siteUrl) { "$siteName ($siteUrl)" } else { $siteName }

        Write-Host ""
        Write-Host "  $siteLabel" -ForegroundColor Cyan

        foreach ($r in $group.Group) {
            $isOk = ($r.Status -eq "OK")

            if ($isOk) {
                $symbol = [char]0x2713  # ✓
                $color = "Green"
            } elseif ($r.Status -eq "NOT_INSTALLED") {
                $symbol = "o"
                $color = "Yellow"
            } else {
                $symbol = "x"
                $color = "Red"
            }

            $vLabel = if ($r.Version) { "v$($r.Version)" } else { "-" }
            $duration = "{0:N1}s" -f $r.Duration
            $statusLabel = $r.Status
            if ($r.Message -and $r.Status -ne "OK") { $statusLabel += " ($($r.Message))" }

            Write-Host ("    $symbol {0,-24} {1,-10} {2,-30} {3}" -f $r.Plugin, $vLabel, $statusLabel, $duration) -ForegroundColor $color
        }
    }

    # Error log section
    $hasLogs = $Results | Where-Object { $_.ErrorLog -or $_.Stacktrace }
    if ($hasLogs) {
        Write-Host ""
        Write-Host "  ── Error Logs ─────────────────────────────────────────" -ForegroundColor Cyan

        foreach ($r in ($Results | Where-Object { $_.ErrorLog -or $_.Stacktrace })) {
            Write-Host ""
            Write-Host "  [$($r.Site) / $($r.Plugin)]" -ForegroundColor White
            $errorLabel = if ($r.ErrorLog) { $r.ErrorLog } else { "No errors" }
            $stackLabel = if ($r.Stacktrace) { $r.Stacktrace } else { "No errors" }
            $errorColor = if ($r.ErrorLog -match '^\d+ lines$') { "Yellow" } elseif ($r.ErrorLog -match '^REST ') { "Red" } else { "DarkGray" }
            $stackColor = if ($r.Stacktrace -match '^\d+ lines$') { "Yellow" } elseif ($r.Stacktrace -match '^REST ') { "Red" } else { "DarkGray" }
            Write-Host "    Error log:  $errorLabel" -ForegroundColor $errorColor
            Write-Host "    Stacktrace: $stackLabel" -ForegroundColor $stackColor
        }

        Write-Host ""
        Write-Host "  Logs saved to: $StatusLogsDir" -ForegroundColor Yellow
    }

    # Totals
    $okCount = ($Results | Where-Object { $_.Status -eq "OK" }).Count
    $failCount = ($Results | Where-Object { $_.Status -ne "OK" -and $_.Status -ne "NOT_INSTALLED" }).Count
    $notInstalledCount = ($Results | Where-Object { $_.Status -eq "NOT_INSTALLED" }).Count

    Write-Host ""
    Write-Host "  $('-' * 40)" -ForegroundColor DarkGray
    Write-Host "  Sites: $TotalSites | Plugins: $TotalPlugins | Total: $($Results.Count)" -ForegroundColor White

    $summaryColor = if ($failCount -eq 0) { "Green" } else { "Yellow" }
    $summaryLine = "  OK: $okCount | Failed: $failCount"
    if ($notInstalledCount -gt 0) { $summaryLine += " | Not Installed: $notInstalledCount" }
    Write-Host $summaryLine -ForegroundColor $summaryColor

    Write-Host "========================================" -ForegroundColor Magenta

    # Save summary to file
    $logStamp = Get-Date -Format "yyyyMMdd-HHmmss"
    $summaryFile = Join-Path $StatusLogsDir "status-summary-$logStamp.txt"
    $summaryContent = @()
    $summaryContent += "Plugin Status Summary - $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
    $summaryContent += "=" * 50
    foreach ($r in $sorted) {
        $vLabel = if ($r.Version) { "v$($r.Version)" } else { "-" }
        $summaryContent += "$($r.Site) | $($r.Plugin) | $vLabel | $($r.Status) | $($r.Message)"
    }
    $summaryContent += ""
    $summaryContent += "OK: $okCount | Failed: $failCount | Not Installed: $notInstalledCount"
    ($summaryContent -join "`n") | Out-File -FilePath $summaryFile -Encoding UTF8
}
