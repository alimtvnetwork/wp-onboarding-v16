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
        if ($script:deployMode) { return }
        exit 0
    }

    # ── Discover plugins ───────────────────────────────────────────────
    $discovery = Get-UploadablePlugins
    $pluginFolders = $discovery.Plugins

    if ($pluginFolders.Count -eq 0) {
        Write-Host "No plugins found." -ForegroundColor Yellow
        if ($script:deployMode) { return }
        exit 0
    }

    # ── Detect local plugin versions ───────────────────────────────────
    $script:localPluginVersions = @{}
    foreach ($folder in $pluginFolders) {
        $localVer = Get-PluginVersion $folder.FullName
        $script:localPluginVersions[$folder.Name] = $localVer
    }

    Write-Host "  Local plugin versions:" -ForegroundColor Cyan
    foreach ($folder in $pluginFolders) {
        $lv = $script:localPluginVersions[$folder.Name]
        Write-Host "    $($folder.Name): v$lv" -ForegroundColor White
    }
    Write-Host ""

    # ── Prepare log folder ─────────────────────────────────────────────
    $statusLogsDir = Join-Path (Join-Path $ScriptDir "logs") "plugin-status"
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
    $script:pluginStatusExitCode = if ($failCount -eq 0) { 0 } else { 1 }

    # In deploy mode (-d), return to caller instead of exiting so the build & run phase can proceed
    if ($script:deployMode) { return }
    exit $script:pluginStatusExitCode
}

function Test-HasProperty {
    param([object]$Obj, [string]$Name)

    if ($null -eq $Obj) { return $false }
    if ($Obj -is [System.Collections.IDictionary]) { return $Obj.Contains($Name) }

    try {
        return ($Obj.PSObject.Properties.Name -contains $Name)
    } catch {
        return $false
    }
}

function Get-PropertyValue {
    param([object]$Obj, [string]$Name)

    if ($null -eq $Obj) { return $null }
    if ($Obj -is [System.Collections.IDictionary]) {
        if ($Obj.Contains($Name)) { return $Obj[$Name] }
        return $null
    }

    try {
        if ($Obj.PSObject.Properties.Name -contains $Name) {
            return $Obj.$Name
        }
    } catch { }

    return $null
}

function Get-PluginStatusPayload {
    param(
        [Parameter(Mandatory=$false)]
        [object]$Body
    )

    if ($null -eq $Body) {
        return $null
    }

    $results = Get-PropertyValue -Obj $Body -Name 'Results'
    if ($null -ne $results) {
        if ($results -is [System.Array]) {
            if ($results.Count -gt 0) { return $results[0] }
        } elseif ($results -is [System.Collections.IEnumerable] -and $results -isnot [string]) {
            $items = @($results)
            if ($items.Count -gt 0) { return $items[0] }
        } else {
            return $results
        }
    }

    $result = Get-PropertyValue -Obj $Body -Name 'Result'
    if ($null -ne $result) {
        return $result
    }

    return $Body
}

function Get-SafeProperty {
    param([object]$Obj, [string[]]$Names)

    foreach ($name in $Names) {
        $val = Get-PropertyValue -Obj $Obj -Name $name
        if ($null -ne $val -and "$val" -ne "") {
            return "$val"
        }
    }

    return ""
}

function Get-PluginStatusMetadata {
    param(
        [Parameter(Mandatory=$false)]
        [object]$Body
    )

    $payload = Get-PluginStatusPayload -Body $Body
    $metadata = @{
        Payload       = $payload
        Version       = ""
        WpVersion     = ""
        PhpVersion    = ""
        PluginName    = ""
        ApiNamespace  = ""
        ServerTime    = ""
        DbAvailable   = ""
        RemoteSiteUrl = ""
    }

    if ($null -eq $payload) {
        return $metadata
    }

    $metadata.Version       = Get-SafeProperty $payload @('Version', 'version')
    if (-not $metadata.Version) { $metadata.Version = Get-SafeProperty $Body @('Version', 'version') }

    $metadata.WpVersion     = Get-SafeProperty $payload @('Wp', 'WpVersion', 'wp', 'wpVersion')
    $metadata.PhpVersion    = Get-SafeProperty $payload @('Php', 'PhpVersion', 'php', 'phpVersion')
    $metadata.PluginName    = Get-SafeProperty $payload @('Plugin', 'plugin')
    $metadata.ApiNamespace  = Get-SafeProperty $payload @('Api', 'ApiNamespace', 'api')
    $metadata.ServerTime    = Get-SafeProperty $payload @('ServerTime', 'serverTime', 'Timestamp', 'timestamp')
    $metadata.DbAvailable   = Get-SafeProperty $payload @('DbAvailable', 'dbAvailable')
    $metadata.RemoteSiteUrl = Get-SafeProperty $payload @('SiteUrl', 'siteUrl')

    return $metadata
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
        Site          = $SiteConfig.Name
        SiteUrl       = $SiteConfig.Url
        Plugin        = $PluginSlug
        Version       = ""
        LocalVersion  = if ($script:localPluginVersions -and $script:localPluginVersions[$PluginSlug]) { $script:localPluginVersions[$PluginSlug] } else { "" }
        WpVersion     = ""
        PhpVersion    = ""
        PluginName    = ""
        ApiNamespace  = ""
        ServerTime    = ""
        DbAvailable   = ""
        RemoteSiteUrl = ""
        Status        = "ERROR"
        HttpStatus    = 0
        Message       = ""
        Duration      = 0
        ErrorLog      = ""
        Stacktrace    = ""
    }

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

    try {
        $response = Invoke-WebRequest -Uri $statusUrl -Method GET -Headers @{ Authorization = $authHeader } -UseBasicParsing -TimeoutSec 30 -ErrorAction Stop
        $result.HttpStatus = $response.StatusCode

        if ($response.StatusCode -eq 200) {
            $result.Status = "OK"
            try {
                $rawStatusBody = $response.Content

                if ($script:pluginStatusVerbose) {
                    Write-Host "" 
                    Write-Host "    ── RAW STATUS [$($SiteConfig.Name) / $PluginSlug] ──" -ForegroundColor DarkCyan
                    Write-Host $rawStatusBody -ForegroundColor Gray
                    Write-Host "    ───────────────────────────────────────────────" -ForegroundColor DarkCyan
                }

                # Strip PHP warnings/notices before the JSON body
                $jsonBody = $rawStatusBody
                $jsonStart = $rawStatusBody.IndexOf('{')
                if ($jsonStart -gt 0) { $jsonBody = $rawStatusBody.Substring($jsonStart) }

                $body = $jsonBody | ConvertFrom-Json
                $metadata = Get-PluginStatusMetadata -Body $body
                $result.Version = $metadata.Version
                $result.WpVersion = $metadata.WpVersion
                $result.PhpVersion = $metadata.PhpVersion
                $result.PluginName = $metadata.PluginName
                $result.ApiNamespace = $metadata.ApiNamespace
                $result.ServerTime = $metadata.ServerTime
                $result.DbAvailable = $metadata.DbAvailable
                $result.RemoteSiteUrl = $metadata.RemoteSiteUrl
            } catch {
                $result.Message = "Status endpoint returned invalid JSON"
            }
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

    if ($result.Status -eq "OK") {
        $minLogsVersion = [version]"2.18.0"
        $remoteVersion = $null
        try { if ($result.Version) { $remoteVersion = [version]$result.Version } } catch { }

        if (-not $remoteVersion -or $remoteVersion -lt $minLogsVersion) {
            $versionLabel = if ($result.Version) { "v$($result.Version)" } else { "unknown" }
            $result.ErrorLog = "Skipped (remote $versionLabel < v$minLogsVersion; update plugin to enable)"
        } else {
            $logsUrl = "$siteUrl/wp-json/$Namespace/logs/retrieve?include_info_log=false&include_error_log=true&include_stacktrace=true&max_lines=100"

            try {
                $logsResponse = Invoke-WebRequest -Uri $logsUrl -Method GET -Headers @{ Authorization = $authHeader } -UseBasicParsing -TimeoutSec 30 -ErrorAction Stop
                $rawContent = $logsResponse.Content
                $logsJsonStart = if ($rawContent) { $rawContent.IndexOf('{') } else { -1 }
                $isJsonResponse = $logsJsonStart -ge 0

                if (-not $isJsonResponse) {
                    $result.ErrorLog = "Not available (endpoint returned non-JSON)"
                } else {
                    if ($logsJsonStart -gt 0) { $rawContent = $rawContent.Substring($logsJsonStart) }
                    $logsBody = $rawContent | ConvertFrom-Json
                    $logsPayload = Get-PluginStatusPayload -Body $logsBody
                    $safeSiteName = ($SiteConfig.Name -replace '[^a-zA-Z0-9_-]', '_')

                    if ($logsPayload.ErrorLog -and $logsPayload.ErrorLog.Exists -and $logsPayload.ErrorLog.Lines -gt 0) {
                        $errorContent = $logsPayload.ErrorLog.Content
                        $result.ErrorLog = "$($logsPayload.ErrorLog.Lines) lines"
                        $errorFile = Join-Path $StatusLogsDir "$safeSiteName-$PluginSlug-error.txt"
                        $errorContent | Out-File -FilePath $errorFile -Encoding UTF8
                    } elseif ($logsPayload.ErrorLog) {
                        $result.ErrorLog = "No errors"
                    }

                    if ($logsPayload.StacktraceLog -and $logsPayload.StacktraceLog.Exists -and $logsPayload.StacktraceLog.Lines -gt 0) {
                        $stackContent = $logsPayload.StacktraceLog.Content
                        $result.Stacktrace = "$($logsPayload.StacktraceLog.Lines) lines"
                        $stackFile = Join-Path $StatusLogsDir "$safeSiteName-$PluginSlug-stacktrace.txt"
                        $stackContent | Out-File -FilePath $stackFile -Encoding UTF8
                    } elseif ($logsPayload.StacktraceLog) {
                        $result.Stacktrace = "No errors"
                    }
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

                function Test-JobHasProperty {
                    param([object]$Obj, [string]$Name)

                    if ($null -eq $Obj) { return $false }
                    if ($Obj -is [System.Collections.IDictionary]) { return $Obj.Contains($Name) }

                    try {
                        return ($Obj.PSObject.Properties.Name -contains $Name)
                    } catch {
                        return $false
                    }
                }

                function Get-JobPropertyValue {
                    param([object]$Obj, [string]$Name)

                    if ($null -eq $Obj) { return $null }
                    if ($Obj -is [System.Collections.IDictionary]) {
                        if ($Obj.Contains($Name)) { return $Obj[$Name] }
                        return $null
                    }

                    try {
                        if ($Obj.PSObject.Properties.Name -contains $Name) {
                            return $Obj.$Name
                        }
                    } catch { }

                    return $null
                }

                function Get-JobSafeProperty {
                    param([object]$Obj, [string[]]$Names)
                    foreach ($name in $Names) {
                        $val = Get-JobPropertyValue -Obj $Obj -Name $name
                        if ($null -ne $val -and "$val" -ne "") {
                            return "$val"
                        }
                    }
                    return ""
                }

                function Get-JobPluginStatusPayload {
                    param([object]$Body)

                    if ($null -eq $Body) { return $null }

                    $results = Get-JobPropertyValue -Obj $Body -Name 'Results'
                    if ($null -ne $results) {
                        if ($results -is [System.Array]) {
                            if ($results.Count -gt 0) { return $results[0] }
                        } elseif ($results -is [System.Collections.IEnumerable] -and $results -isnot [string]) {
                            $items = @($results)
                            if ($items.Count -gt 0) { return $items[0] }
                        } else {
                            return $results
                        }
                    }

                    $result = Get-JobPropertyValue -Obj $Body -Name 'Result'
                    if ($null -ne $result) {
                        return $result
                    }

                    return $Body
                }

                function Get-JobPluginStatusMetadata {
                    param([object]$Body)

                    $payload = Get-JobPluginStatusPayload -Body $Body
                    $metadata = @{
                        Payload       = $payload
                        Version       = ""
                        WpVersion     = ""
                        PhpVersion    = ""
                        PluginName    = ""
                        ApiNamespace  = ""
                        ServerTime    = ""
                        DbAvailable   = ""
                        RemoteSiteUrl = ""
                    }

                    if ($null -eq $payload) { return $metadata }

                    $metadata.Version       = Get-JobSafeProperty $payload @('Version', 'version')
                    if (-not $metadata.Version) { $metadata.Version = Get-JobSafeProperty $Body @('Version', 'version') }

                    $metadata.WpVersion     = Get-JobSafeProperty $payload @('Wp', 'WpVersion', 'wp', 'wpVersion')
                    $metadata.PhpVersion    = Get-JobSafeProperty $payload @('Php', 'PhpVersion', 'php', 'phpVersion')
                    $metadata.PluginName    = Get-JobSafeProperty $payload @('Plugin', 'plugin')
                    $metadata.ApiNamespace  = Get-JobSafeProperty $payload @('Api', 'ApiNamespace', 'api')
                    $metadata.ServerTime    = Get-JobSafeProperty $payload @('ServerTime', 'serverTime', 'Timestamp', 'timestamp')
                    $metadata.DbAvailable   = Get-JobSafeProperty $payload @('DbAvailable', 'dbAvailable')
                    $metadata.RemoteSiteUrl = Get-JobSafeProperty $payload @('SiteUrl', 'siteUrl')

                    return $metadata
                }

                $sw = [System.Diagnostics.Stopwatch]::StartNew()

                $result = @{
                    Site          = $SiteName
                    SiteUrl       = $SiteUrl
                    Plugin        = $PluginSlug
                    Version       = ""
                    WpVersion     = ""
                    PhpVersion    = ""
                    PluginName    = ""
                    ApiNamespace  = ""
                    ServerTime    = ""
                    DbAvailable   = ""
                    RemoteSiteUrl = ""
                    RawStatusBody = ""
                    Status        = "ERROR"
                    HttpStatus    = 0
                    Message       = ""
                    Duration      = 0
                    ErrorLog      = ""
                    Stacktrace    = ""
                    Index         = $Index
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
                            $rawStatusBody = $response.Content
                            $result.RawStatusBody = $rawStatusBody

                            # Strip PHP warnings/notices before the JSON body
                            $jsonBody = $rawStatusBody
                            $jsonStart = $rawStatusBody.IndexOf('{')
                            if ($jsonStart -gt 0) { $jsonBody = $rawStatusBody.Substring($jsonStart) }

                            $body = $jsonBody | ConvertFrom-Json
                            $metadata = Get-JobPluginStatusMetadata -Body $body
                            $result.Version = $metadata.Version
                            $result.WpVersion = $metadata.WpVersion
                            $result.PhpVersion = $metadata.PhpVersion
                            $result.PluginName = $metadata.PluginName
                            $result.ApiNamespace = $metadata.ApiNamespace
                            $result.ServerTime = $metadata.ServerTime
                            $result.DbAvailable = $metadata.DbAvailable
                            $result.RemoteSiteUrl = $metadata.RemoteSiteUrl
                        } catch {
                            $result.Message = "Status endpoint returned invalid JSON"
                        }
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

                if ($result.Status -eq "OK") {
                    $minLogsVersion = [version]"2.18.0"
                    $remoteVer = $null
                    try { if ($result.Version) { $remoteVer = [version]$result.Version } } catch { }

                    if (-not $remoteVer -or $remoteVer -lt $minLogsVersion) {
                        $versionLabel = if ($result.Version) { "v$($result.Version)" } else { "unknown" }
                        $result.ErrorLog = "Skipped (remote $versionLabel < v$minLogsVersion; update plugin to enable)"
                    } else {
                        $logsUrl = "$SiteUrl/wp-json/$Namespace/logs/retrieve?include_info_log=false&include_error_log=true&include_stacktrace=true&max_lines=100"
                        try {
                            $logsResponse = Invoke-WebRequest -Uri $logsUrl -Method GET -Headers @{ Authorization = $authHeader } -UseBasicParsing -TimeoutSec 30 -ErrorAction Stop
                            $rawContent = $logsResponse.Content
                            $logsJsonStart = if ($rawContent) { $rawContent.IndexOf('{') } else { -1 }
                            $isJsonResponse = $logsJsonStart -ge 0

                            if (-not $isJsonResponse) {
                                $result.ErrorLog = "Not available (endpoint returned non-JSON)"
                            } else {
                                if ($logsJsonStart -gt 0) { $rawContent = $rawContent.Substring($logsJsonStart) }
                                $logsBody = $rawContent | ConvertFrom-Json
                                $logsPayload = Get-JobPluginStatusPayload -Body $logsBody
                                $safeSiteName = ($SiteName -replace '[^a-zA-Z0-9_-]', '_')

                                if ($logsPayload.ErrorLog -and $logsPayload.ErrorLog.Exists -and $logsPayload.ErrorLog.Lines -gt 0) {
                                    $result.ErrorLog = "$($logsPayload.ErrorLog.Lines) lines"
                                    $errorFile = Join-Path $StatusLogsDir "$safeSiteName-$PluginSlug-error.txt"
                                    $logsPayload.ErrorLog.Content | Out-File -FilePath $errorFile -Encoding UTF8
                                } elseif ($logsPayload.ErrorLog) {
                                    $result.ErrorLog = "No errors"
                                }

                                if ($logsPayload.StacktraceLog -and $logsPayload.StacktraceLog.Exists -and $logsPayload.StacktraceLog.Lines -gt 0) {
                                    $result.Stacktrace = "$($logsPayload.StacktraceLog.Lines) lines"
                                    $stackFile = Join-Path $StatusLogsDir "$safeSiteName-$PluginSlug-stacktrace.txt"
                                    $logsPayload.StacktraceLog.Content | Out-File -FilePath $stackFile -Encoding UTF8
                                } elseif ($logsPayload.StacktraceLog) {
                                    $result.Stacktrace = "No errors"
                                }
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
                }

                $sw.Stop()
                $result.Duration = $sw.Elapsed.TotalSeconds
                return $result
            } -ArgumentList $siteUrl, $siteName, $pluginSlug, $namespace, $username, $password, $StatusLogsDir, $currentIndex, $isErrorMode

            $jobIndex++
        }
    }

    $results = @()
    foreach ($job in $jobs) {
        $result = Receive-Job -Job $job -Wait | Select-Object -First 1

        if ($null -eq $result) {
            $idx = [int](($job.Name -split '-')[1])
            $result = @{
                Index         = $idx
                Site          = "unknown"
                SiteUrl       = ""
                Plugin        = "unknown"
                Version       = ""
                LocalVersion  = ""
                WpVersion     = ""
                PhpVersion    = ""
                PluginName    = ""
                ApiNamespace  = ""
                ServerTime    = ""
                DbAvailable   = ""
                RemoteSiteUrl = ""
                RawStatusBody = ""
                Status        = "FAILED"
                HttpStatus    = 0
                Message       = "Background job crashed"
                Duration      = 0
                ErrorLog      = ""
                Stacktrace    = ""
            }
        }

        # Attach local version (not available inside job)
        $plugSlug = $result.Plugin
        if ($script:localPluginVersions -and $script:localPluginVersions[$plugSlug]) {
            $result.LocalVersion = $script:localPluginVersions[$plugSlug]
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
        $localLabel = if ($result.LocalVersion -and $result.LocalVersion -ne "unknown") { " (local v$($result.LocalVersion))" } else { "" }
        $duration = "{0:N1}s" -f $result.Duration
        Write-Host "    $symbol [$($result.Site)] $($result.Plugin) $vLabel$localLabel $($result.Status) $duration" -ForegroundColor $color

        if ($script:pluginStatusVerbose -and $result.RawStatusBody) {
            Write-Host "" 
            Write-Host "    ── RAW STATUS [$($result.Site) / $($result.Plugin)] ──" -ForegroundColor DarkCyan
            Write-Host $result.RawStatusBody -ForegroundColor Gray
            Write-Host "    ───────────────────────────────────────────────" -ForegroundColor DarkCyan
        }

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
            $localLabel = if ($result.LocalVersion -and $result.LocalVersion -ne "unknown") { " (local v$($result.LocalVersion))" } else { "" }
            $duration = "{0:N1}s" -f $result.Duration
            Write-Host "    $symbol [$($result.Site)] $($result.Plugin) $vLabel$localLabel $($result.Status) $duration" -ForegroundColor $color

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

    $checkTimestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"

    Write-Host ""
    Write-Host "========================================" -ForegroundColor Magenta
    Write-Host "  Plugin Status Summary" -ForegroundColor Magenta
    Write-Host "  Checked: $checkTimestamp" -ForegroundColor DarkGray
    Write-Host "========================================" -ForegroundColor Magenta

    $sorted = @($Results | Sort-Object { $_.Index })
    $grouped = $sorted | Group-Object -Property Site

    foreach ($group in $grouped) {
        $siteName = $group.Name
        $siteUrl = if ($group.Group[0].SiteUrl) { $group.Group[0].SiteUrl } else { "" }

        Write-Host ""
        Write-Host "  ┌─────────────────────────────────────────────────────────" -ForegroundColor DarkCyan
        Write-Host "  │ $siteName" -ForegroundColor Cyan -NoNewline
        if ($siteUrl) { Write-Host "  $siteUrl" -ForegroundColor DarkGray } else { Write-Host "" }
        Write-Host "  ├─────────────────────────────────────────────────────────" -ForegroundColor DarkCyan

        foreach ($r in $group.Group) {
            $isOk = ($r.Status -eq "OK")
            $isNotInstalled = ($r.Status -eq "NOT_INSTALLED")

            # Plugin status line
            if ($isOk) {
                $symbol = [char]0x2713  # ✓
                $statusColor = "Green"
            } elseif ($isNotInstalled) {
                $symbol = "○"
                $statusColor = "Yellow"
            } else {
                $symbol = "✗"
                $statusColor = "Red"
            }

            $vLabel = if ($r.Version) { "v$($r.Version)" } else { "version unknown" }
            $vColor = if ($r.Version) { "White" } else { "DarkYellow" }
            $duration = "{0:N1}s" -f $r.Duration

            # Version comparison: local vs remote
            $localVer = $r.LocalVersion
            $versionCompare = ""
            $versionCompareColor = "DarkGray"
            if ($r.Version -and $localVer -and $localVer -ne "unknown") {
                if ($r.Version -eq $localVer) {
                    $versionCompare = "(up to date)"
                    $versionCompareColor = "DarkGreen"
                } else {
                    try {
                        $remoteV = [version]$r.Version
                        $localV  = [version]$localVer
                        if ($localV -gt $remoteV) {
                            $versionCompare = "(local v$localVer → needs deploy)"
                            $versionCompareColor = "Yellow"
                        } else {
                            $versionCompare = "(local v$localVer ← remote is newer)"
                            $versionCompareColor = "Cyan"
                        }
                    } catch {
                        $versionCompare = "(local v$localVer)"
                        $versionCompareColor = "DarkGray"
                    }
                }
            } elseif ($localVer -and $localVer -ne "unknown") {
                $versionCompare = "(local v$localVer)"
            }

            Write-Host "  │" -ForegroundColor DarkCyan -NoNewline
            Write-Host "  $symbol " -ForegroundColor $statusColor -NoNewline
            Write-Host "$($r.Plugin)" -ForegroundColor White -NoNewline
            Write-Host "  " -NoNewline
            Write-Host "$vLabel" -ForegroundColor $vColor -NoNewline
            if ($versionCompare) {
                Write-Host " $versionCompare" -ForegroundColor $versionCompareColor -NoNewline
            }
            Write-Host "  " -NoNewline
            Write-Host "$($r.Status)" -ForegroundColor $statusColor -NoNewline
            Write-Host "  [$duration]" -ForegroundColor DarkGray
            if ($r.Message -and -not $isOk) {
                Write-Host "  │      $($r.Message)" -ForegroundColor $statusColor
            }

            # Show extra details if available
            if ($isOk) {
                # Line 1: environment info
                $detailParts = @()
                if ($r.WpVersion) { $detailParts += "WP $($r.WpVersion)" }
                if ($r.PhpVersion) { $detailParts += "PHP $($r.PhpVersion)" }
                if ($r.ApiNamespace) { $detailParts += "API $($r.ApiNamespace)" }
                if ($r.DbAvailable) {
                    $dbLabel = if ($r.DbAvailable -eq "True") { "DB ✓" } else { "DB ✗" }
                    $detailParts += $dbLabel
                }
                if ($detailParts.Count -gt 0) {
                    Write-Host ("  │      " + ($detailParts -join " | ")) -ForegroundColor DarkGray
                }
                # Line 2: server time and remote site URL
                $extraParts = @()
                if ($r.ServerTime) { $extraParts += "Server: $($r.ServerTime)" }
                if ($r.RemoteSiteUrl) { $extraParts += "URL: $($r.RemoteSiteUrl)" }
                if ($extraParts.Count -gt 0) {
                    Write-Host ("  │      " + ($extraParts -join " | ")) -ForegroundColor DarkGray
                }
            }

            # Nested logs under each plugin
            $hasError = ($r.ErrorLog -and $r.ErrorLog -ne "No errors")
            $hasStack = ($r.Stacktrace -and $r.Stacktrace -ne "No errors")

            if ($hasError -or $hasStack) {
                if ($hasError) {
                    $errorColor = if ($r.ErrorLog -match '^\d+ lines$') { "Yellow" } elseif ($r.ErrorLog -match '^REST |^Not available') { "DarkYellow" } else { "DarkGray" }
                    Write-Host "  │      ├ Error log:  $($r.ErrorLog)" -ForegroundColor $errorColor
                }
                if ($hasStack) {
                    $stackColor = if ($r.Stacktrace -match '^\d+ lines$') { "Yellow" } elseif ($r.Stacktrace -match '^REST |^Not available') { "DarkYellow" } else { "DarkGray" }
                    Write-Host "  │      └ Stacktrace: $($r.Stacktrace)" -ForegroundColor $stackColor
                } elseif ($hasError) {
                    Write-Host "  │      └ Stacktrace: Clean" -ForegroundColor DarkGreen
                }
            } else {
                Write-Host "  │      └ Logs: Clean" -ForegroundColor DarkGreen
            }
        }

        Write-Host "  └─────────────────────────────────────────────────────────" -ForegroundColor DarkCyan
    }

    # Totals
    $okCount = ($Results | Where-Object { $_.Status -eq "OK" }).Count
    $failCount = ($Results | Where-Object { $_.Status -ne "OK" -and $_.Status -ne "NOT_INSTALLED" }).Count
    $notInstalledCount = ($Results | Where-Object { $_.Status -eq "NOT_INSTALLED" }).Count

    Write-Host ""
    Write-Host "  ── Totals ─────────────────────────────────────────────" -ForegroundColor Magenta
    Write-Host "  Sites: $TotalSites | Plugins: $TotalPlugins | Checks: $($Results.Count)" -ForegroundColor White

    $summaryColor = if ($failCount -eq 0) { "Green" } else { "Yellow" }
    $summaryLine = "  OK: $okCount | Failed: $failCount"
    if ($notInstalledCount -gt 0) { $summaryLine += " | Not Installed: $notInstalledCount" }
    Write-Host $summaryLine -ForegroundColor $summaryColor

    if ($StatusLogsDir -and (Test-Path $StatusLogsDir)) {
        $savedLogs = @(Get-ChildItem -Path $StatusLogsDir -File -ErrorAction SilentlyContinue)
        if ($savedLogs.Count -gt 0) {
            Write-Host "  Log files: $StatusLogsDir ($($savedLogs.Count) file(s))" -ForegroundColor DarkGray
        }
    }

    Write-Host "========================================" -ForegroundColor Magenta

    # Save summary to file
    $logStamp = Get-Date -Format "yyyyMMdd-HHmmss"
    $summaryFile = Join-Path $StatusLogsDir "status-summary-$logStamp.txt"
    $summaryContent = @()
    $summaryContent += "Plugin Status Summary - $checkTimestamp"
    $summaryContent += "=" * 50
    foreach ($r in $sorted) {
        $vLabel = if ($r.Version) { "v$($r.Version)" } else { "-" }
        $localLabel = if ($r.LocalVersion -and $r.LocalVersion -ne "unknown") { "local=v$($r.LocalVersion)" } else { "" }
        $errorLabel = if ($r.ErrorLog -and $r.ErrorLog -ne "No errors") { $r.ErrorLog } else { "clean" }
        $stackLabel = if ($r.Stacktrace -and $r.Stacktrace -ne "No errors") { $r.Stacktrace } else { "clean" }
        $wpLabel = if ($r.WpVersion) { "WP $($r.WpVersion)" } else { "" }
        $phpLabel = if ($r.PhpVersion) { "PHP $($r.PhpVersion)" } else { "" }
        $dbLabel = if ($r.DbAvailable) { "DB=$($r.DbAvailable)" } else { "" }
        $serverLabel = if ($r.ServerTime) { "Server=$($r.ServerTime)" } else { "" }
        $summaryContent += "$($r.Site) | $($r.Plugin) | remote=$vLabel | $localLabel | $($r.Status) | $wpLabel | $phpLabel | $dbLabel | $serverLabel | errors=$errorLabel | stack=$stackLabel | $($r.Message)"
    }
    $summaryContent += ""
    $summaryContent += "OK: $okCount | Failed: $failCount | Not Installed: $notInstalledCount"
    ($summaryContent -join "`n") | Out-File -FilePath $summaryFile -Encoding UTF8
}
