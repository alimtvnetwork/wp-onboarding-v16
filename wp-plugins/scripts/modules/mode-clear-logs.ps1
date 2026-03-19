# Module: mode-clear-logs.ps1
# Remote log clearing for configured sites via the logs/clear API.
# Two-step flow: DELETE /logs/clear -> POST /logs/clear/confirm with token.
# Dot-sourced by run.ps1 — expects $Config, $ScriptDir, helpers, plugin-helpers loaded.
# Expects: $site, $exclude, $sync, $index

function Invoke-ClearLogsMode {
    param(
        [switch]$ForceAll,
        [string]$PluginFilter = "",
        [string]$TypeFilter = "",
        [switch]$AuditMode,
        [switch]$VerboseMode
    )

    Write-Host ""

    if ($AuditMode) {
        Invoke-ClearAuditLogsMode -ForceAll:$ForceAll -VerboseMode:$VerboseMode
        return
    }

    $modeLabel = if ($ForceAll) { "Remote Log Clearing Mode (-cla: ALL sites)" } else { "Remote Log Clearing Mode (-cl)" }
    Write-Host "========================================" -ForegroundColor Magenta
    Write-Host "  $modeLabel" -ForegroundColor Magenta
    Write-Host "========================================" -ForegroundColor Magenta
    Write-Host ""

    if (-not $Config.wpPlugins -or -not $Config.wpPlugins.sites -or $Config.wpPlugins.sites.Count -eq 0) {
        Write-Host "ERROR: No sites configured in powershell.json (wpPlugins.sites)" -ForegroundColor Red
        exit 1
    }

    Show-ConfiguredSites

    # Resolve target sites using shared helper (supports -site, -i, -xs)
    $allSites = @($Config.wpPlugins.sites)

    if ($ForceAll) {
        # -cla: target ALL enabled sites, ignore -site/-i/-xs
        $targetSites = @($allSites | Where-Object { $_.enabled -ne $false })
        Write-Host "  Target: All enabled sites ($($targetSites.Count))" -ForegroundColor Cyan
    } else {
        $excludeNames = @()

        $hasExclude = ($exclude -ne "")

        if ($hasExclude) {
            $excludeNames = @($exclude -split ',' | ForEach-Object { $_.Trim() })
        }

        $targetSites = Resolve-TargetSites -Index $index -SiteName $site -ExcludedSiteNames $excludeNames -AllSites $allSites
    }

    if ($targetSites.Count -eq 0) {
        Write-Host "No enabled sites found." -ForegroundColor Yellow
        exit 0
    }

    $machineName = $env:COMPUTERNAME
    Write-Host "  Machine: $machineName" -ForegroundColor Cyan
    Write-Host "  Target:  $($targetSites.Count) site(s)" -ForegroundColor Cyan

    # Resolve log type filter
    $resolvedType = Resolve-LogTypeFilter $TypeFilter
    $hasTypeFilter = ($resolvedType -ne "all")

    if ($hasTypeFilter) {
        Write-Host "  Type:    $resolvedType" -ForegroundColor Cyan
    }

    Write-Host ""

    # Determine which plugins to clear logs for
    $pluginNamespaces = @()

    $hasDefaultUploader = ($Config.wpPlugins -and $Config.wpPlugins.defaultUploader -and $Config.wpPlugins.plugins.$($Config.wpPlugins.defaultUploader))
    if ($hasDefaultUploader) {
        $uploaderSlug = $Config.wpPlugins.defaultUploader
        $uploaderCfg = $Config.wpPlugins.plugins.$uploaderSlug
        $uploaderNamespace = Get-PluginApiNamespace $uploaderSlug
        $pluginNamespaces += @{ Slug = $uploaderSlug; Name = $uploaderCfg.name; Namespace = $uploaderNamespace }
    }

    $hasQUploader = ($Config.wpPlugins -and $Config.wpPlugins.defaultQUploader -and $Config.wpPlugins.plugins.$($Config.wpPlugins.defaultQUploader))
    if ($hasQUploader) {
        $qSlug = $Config.wpPlugins.defaultQUploader
        $qCfg = $Config.wpPlugins.plugins.$qSlug
        $qNamespace = Get-PluginApiNamespace $qSlug
        $pluginNamespaces += @{ Slug = $qSlug; Name = $qCfg.name; Namespace = $qNamespace }
    }

    # Apply plugin filter
    $hasPluginFilter = ($PluginFilter -ne "")

    if ($hasPluginFilter) {
        $pluginNamespaces = Resolve-PluginFilter $pluginNamespaces $PluginFilter

        if ($pluginNamespaces.Count -eq 0) {
            Write-Host "ERROR: No matching plugin for filter '$PluginFilter'. Use: q|qupload|r|riseup" -ForegroundColor Red
            exit 1
        }
    }

    if ($pluginNamespaces.Count -eq 0) {
        Write-Host "ERROR: No plugins with API namespaces configured." -ForegroundColor Red
        exit 1
    }

    Write-Host "  Plugins:" -ForegroundColor Cyan
    foreach ($ns in $pluginNamespaces) {
        Write-Host "    - $($ns.Name) ($($ns.Namespace))" -ForegroundColor Gray
    }
    Write-Host ""

    # Execute clearing
    $results = @()

    foreach ($targetSite in $targetSites) {
        $siteName = $targetSite.name
        $siteUrl = $targetSite.url.TrimEnd("/")
        $cred = Get-DefaultSiteCredential $targetSite

        if (-not $cred) {
            Write-Host "  [$siteName] SKIPPED: No valid credentials" -ForegroundColor Red
            foreach ($ns in $pluginNamespaces) {
                $results += @{ Site = $siteName; Plugin = $ns.Name; Status = "SKIPPED"; Error = "No credentials" }
            }
            continue
        }

        $authHeader = Build-BasicAuthHeader $cred.Username $cred.Password

        foreach ($ns in $pluginNamespaces) {
            $pluginLabel = $ns.Name
            $apiBase = "$siteUrl/wp-json/$($ns.Namespace)"

            Write-Host "  [$siteName] $pluginLabel..." -ForegroundColor Yellow -NoNewline

            $clearResult = Invoke-TwoStepLogClear -ApiBase $apiBase -AuthHeader $authHeader -MachineName $machineName -SiteName $siteName -PluginLabel $pluginLabel -LogType $resolvedType -VerboseMode:$VerboseMode

            $results += $clearResult

            $isSuccess = ($clearResult.Status -eq "OK")
            $icon = if ($isSuccess) { " OK" } else { " FAILED" }
            $color = if ($isSuccess) { "Green" } else { "Red" }
            Write-Host $icon -ForegroundColor $color

            if (-not $isSuccess -and $clearResult.Error) {
                Write-Host "    Error: $($clearResult.Error)" -ForegroundColor DarkYellow
            }

            # Show response body on failure for diagnostics
            $hasResponseBody = (-not $isSuccess -and $clearResult.ResponseBody)

            if ($hasResponseBody) {
                Write-Host "    Response: $($clearResult.ResponseBody)" -ForegroundColor DarkGray
            }
        }
    }

    # Summary
    Show-ClearLogsSummary $results $machineName

    exit $(if (($results | Where-Object { $_.Status -ne "OK" }).Count -eq 0) { 0 } else { 1 })
}

# ── Purge Mode (All Logs + Audit in one command) ────────────────────────

function Invoke-PurgeMode {
    param(
        [switch]$SkipConfirm,
        [switch]$VerboseMode
    )

    Write-Host ""
    Write-Host "========================================" -ForegroundColor Red
    Write-Host "  PURGE MODE - Clear ALL Logs + Audit" -ForegroundColor Red
    Write-Host "========================================" -ForegroundColor Red
    Write-Host ""

    $allSites = $Config.wpPlugins.sites
    $excludeNames = if ($exclude) { $exclude -split ',' | ForEach-Object { $_.Trim() } } else { @() }

    if ($site -or $index -or $exclude) {
        $targetSites = Resolve-TargetSites -Index $index -SiteName $site -ExcludedSiteNames $excludeNames -AllSites $allSites
    } else {
        $targetSites = @($allSites | Where-Object { $_.enabled -ne $false })
        Write-Host "  Target: All enabled sites ($($targetSites.Count))" -ForegroundColor Cyan
    }

    if ($targetSites.Count -eq 0) {
        Write-Host "No enabled sites found." -ForegroundColor Yellow
        exit 0
    }

    $machineName = $env:COMPUTERNAME
    Write-Host "  Machine: $machineName" -ForegroundColor Cyan
    Write-Host "  Target:  $($targetSites.Count) site(s)" -ForegroundColor Cyan
    Write-Host "  Scope:   File logs (all plugins) + Audit logs (plugins-onboard)" -ForegroundColor Cyan
    Write-Host ""

    # Confirmation prompt — destructive operation (skip with -yes)
    if (-not $SkipConfirm) {
        $siteList = ($targetSites | ForEach-Object { $_.name }) -join ', '
        Write-Host "  WARNING: This will permanently delete ALL logs, stacktraces, and audit data" -ForegroundColor Yellow
        Write-Host "  on: $siteList" -ForegroundColor Yellow
        Write-Host ""
        $confirm = Read-Host "  Type 'yes' to confirm, or anything else to cancel"
        if ($confirm -ne 'yes') {
            Write-Host ""
            Write-Host "  Cancelled." -ForegroundColor Gray
            return
        }
        Write-Host ""
    } else {
        Write-Host "  Skipping confirmation (-yes flag)" -ForegroundColor DarkGray
        Write-Host ""
    }

    # Build plugin namespace list for file log clearing
    $pluginNamespaces = @()

    $hasDefaultUploader = ($Config.wpPlugins -and $Config.wpPlugins.defaultUploader -and $Config.wpPlugins.plugins.$($Config.wpPlugins.defaultUploader))
    if ($hasDefaultUploader) {
        $uploaderSlug = $Config.wpPlugins.defaultUploader
        $uploaderCfg = $Config.wpPlugins.plugins.$uploaderSlug
        $uploaderNamespace = Get-PluginApiNamespace $uploaderSlug
        $pluginNamespaces += @{ Slug = $uploaderSlug; Name = $uploaderCfg.name; Namespace = $uploaderNamespace }
    }

    $hasQUploader = ($Config.wpPlugins -and $Config.wpPlugins.defaultQUploader -and $Config.wpPlugins.plugins.$($Config.wpPlugins.defaultQUploader))
    if ($hasQUploader) {
        $qSlug = $Config.wpPlugins.defaultQUploader
        $qCfg = $Config.wpPlugins.plugins.$qSlug
        $qNamespace = Get-PluginApiNamespace $qSlug
        $pluginNamespaces += @{ Slug = $qSlug; Name = $qCfg.name; Namespace = $qNamespace }
    }

    Write-Host "  Plugins:" -ForegroundColor Cyan
    foreach ($ns in $pluginNamespaces) {
        Write-Host "    - $($ns.Name) ($($ns.Namespace))" -ForegroundColor Gray
    }
    Write-Host "    - plugins-onboard (audit logs)" -ForegroundColor Gray
    Write-Host ""

    $results = @()

    foreach ($targetSite in $targetSites) {
        $siteName = $targetSite.name
        $siteUrl = $targetSite.url.TrimEnd("/")
        $cred = Get-DefaultSiteCredential $targetSite

        if (-not $cred) {
            Write-Host "  [$siteName] SKIPPED: No valid credentials" -ForegroundColor Red
            foreach ($ns in $pluginNamespaces) {
                $results += @{ Site = $siteName; Plugin = $ns.Name; Status = "SKIPPED"; Error = "No credentials" }
            }
            $results += @{ Site = $siteName; Plugin = "plugins-onboard (audit)"; Status = "SKIPPED"; Error = "No credentials" }
            continue
        }

        $authHeader = Build-BasicAuthHeader $cred.Username $cred.Password

        # Phase 1: Clear file logs for all plugins
        foreach ($ns in $pluginNamespaces) {
            $pluginLabel = $ns.Name
            $apiBase = "$siteUrl/wp-json/$($ns.Namespace)"

            Write-Host "  [$siteName] $pluginLabel..." -ForegroundColor Yellow -NoNewline

            $clearResult = Invoke-TwoStepLogClear -ApiBase $apiBase -AuthHeader $authHeader -MachineName $machineName -SiteName $siteName -PluginLabel $pluginLabel -LogType "all" -VerboseMode:$VerboseMode

            $results += $clearResult

            $isSuccess = ($clearResult.Status -eq "OK")
            $icon = if ($isSuccess) { " OK" } else { " FAILED" }
            $color = if ($isSuccess) { "Green" } else { "Red" }
            Write-Host $icon -ForegroundColor $color

            if (-not $isSuccess -and $clearResult.Error) {
                Write-Host "    Error: $($clearResult.Error)" -ForegroundColor DarkYellow
            }
        }

        # Phase 2: Clear audit logs via plugins-onboard
        $auditClearUrl = "$siteUrl/wp-json/onboard-plugin/v1/audit-logs/clear"
        Write-Host "  [$siteName] audit logs..." -ForegroundColor Yellow -NoNewline

        try {
            $headers = @{
                "Authorization" = $authHeader
                "Content-Type"  = "application/json"
            }

            if ($VerboseMode) {
                Write-Host ""
                Write-Host "    [VERBOSE] DELETE $auditClearUrl" -ForegroundColor DarkGray
            }

            $response = Invoke-RestMethod -Uri $auditClearUrl -Method Delete -Headers $headers -ErrorAction Stop

            if ($VerboseMode) {
                $respJson = $response | ConvertTo-Json -Depth 5 -Compress
                Write-Host "    [VERBOSE] Response: $respJson" -ForegroundColor DarkGray
            }

            $isSuccess = ($response.success -eq $true)

            if ($isSuccess) {
                $recordsCleared = if ($response.records_cleared) { $response.records_cleared } else { 0 }
                Write-Host " OK ($recordsCleared records)" -ForegroundColor Green
                $results += @{ Site = $siteName; Plugin = "plugins-onboard (audit)"; Status = "OK"; Error = $null }
            } else {
                $errorMsg = if ($response.error) { $response.error } else { "Unknown error" }
                Write-Host " FAILED" -ForegroundColor Red
                Write-Host "    Error: $errorMsg" -ForegroundColor DarkYellow
                $results += @{ Site = $siteName; Plugin = "plugins-onboard (audit)"; Status = "FAILED"; Error = $errorMsg }
            }
        } catch {
            $errorMsg = Get-ClearLogsErrorMessage $_
            Write-Host " FAILED" -ForegroundColor Red
            Write-Host "    Error: $errorMsg" -ForegroundColor DarkYellow
            $results += @{ Site = $siteName; Plugin = "plugins-onboard (audit)"; Status = "FAILED"; Error = $errorMsg }
        }
    }

    # Summary
    Show-ClearLogsSummary $results $machineName

    exit $(if (($results | Where-Object { $_.Status -ne "OK" }).Count -eq 0) { 0 } else { 1 })
}

# ── Audit Log Clearing Mode ─────────────────────────────────────────────

function Invoke-ClearAuditLogsMode {
    param(
        [switch]$ForceAll,
        [switch]$VerboseMode
    )

    $modeLabel = if ($ForceAll) { "Audit Log Clearing Mode (-cla -audit: ALL sites)" } else { "Audit Log Clearing Mode (-cl -audit)" }
    Write-Host "========================================" -ForegroundColor Magenta
    Write-Host "  $modeLabel" -ForegroundColor Magenta
    Write-Host "========================================" -ForegroundColor Magenta
    Write-Host ""

    if (-not $Config.wpPlugins -or -not $Config.wpPlugins.sites -or $Config.wpPlugins.sites.Count -eq 0) {
        Write-Host "ERROR: No sites configured in powershell.json (wpPlugins.sites)" -ForegroundColor Red
        exit 1
    }

    Show-ConfiguredSites

    $allSites = @($Config.wpPlugins.sites)

    if ($ForceAll) {
        $targetSites = @($allSites | Where-Object { $_.enabled -ne $false })
        Write-Host "  Target: All enabled sites ($($targetSites.Count))" -ForegroundColor Cyan
    } else {
        $excludeNames = @()
        $hasExclude = ($exclude -ne "")

        if ($hasExclude) {
            $excludeNames = @($exclude -split ',' | ForEach-Object { $_.Trim() })
        }

        $targetSites = Resolve-TargetSites -Index $index -SiteName $site -ExcludedSiteNames $excludeNames -AllSites $allSites
    }

    if ($targetSites.Count -eq 0) {
        Write-Host "No enabled sites found." -ForegroundColor Yellow
        exit 0
    }

    Write-Host "  Target:  $($targetSites.Count) site(s)" -ForegroundColor Cyan
    Write-Host "  API:     onboard-plugin/v1/audit-logs/clear" -ForegroundColor Cyan
    Write-Host ""

    $results = @()

    foreach ($targetSite in $targetSites) {
        $siteName = $targetSite.name
        $siteUrl = $targetSite.url.TrimEnd("/")
        $cred = Get-DefaultSiteCredential $targetSite

        if (-not $cred) {
            Write-Host "  [$siteName] SKIPPED: No valid credentials" -ForegroundColor Red
            $results += @{ Site = $siteName; Plugin = "plugins-onboard"; Status = "SKIPPED"; Error = "No credentials" }
            continue
        }

        $authHeader = Build-BasicAuthHeader $cred.Username $cred.Password
        $auditClearUrl = "$siteUrl/wp-json/onboard-plugin/v1/audit-logs/clear"

        Write-Host "  [$siteName] audit logs..." -ForegroundColor Yellow -NoNewline

        try {
            $headers = @{
                "Authorization" = $authHeader
                "Content-Type"  = "application/json"
            }
            $response = Invoke-RestMethod -Uri $auditClearUrl -Method Delete -Headers $headers -ErrorAction Stop

            $isSuccess = ($response.success -eq $true)

            if ($isSuccess) {
                $recordsCleared = if ($response.records_cleared) { $response.records_cleared } else { 0 }
                Write-Host " OK ($recordsCleared records)" -ForegroundColor Green
                $results += @{ Site = $siteName; Plugin = "plugins-onboard"; Status = "OK"; Error = $null }
            } else {
                $errorMsg = if ($response.error) { $response.error } else { "Unknown error" }
                Write-Host " FAILED" -ForegroundColor Red
                Write-Host "    Error: $errorMsg" -ForegroundColor DarkYellow
                $results += @{ Site = $siteName; Plugin = "plugins-onboard"; Status = "FAILED"; Error = $errorMsg }
            }
        } catch {
            $errorMsg = Get-ClearLogsErrorMessage $_
            $responseBody = Get-ClearLogsResponseBody $_
            Write-Host " FAILED" -ForegroundColor Red
            Write-Host "    Error: $errorMsg" -ForegroundColor DarkYellow

            $hasResponseBody = ($null -ne $responseBody)

            if ($hasResponseBody) {
                Write-Host "    Response: $responseBody" -ForegroundColor DarkGray
            }
            $results += @{ Site = $siteName; Plugin = "plugins-onboard"; Status = "FAILED"; Error = $errorMsg; ResponseBody = $responseBody }
        }
    }

    # Summary
    Show-ClearLogsSummary $results ""

    exit $(if (($results | Where-Object { $_.Status -ne "OK" }).Count -eq 0) { 0 } else { 1 })
}

# ── Two-step log clear flow ──────────────────────────────────────────────

function Invoke-TwoStepLogClear {
    param(
        [string]$ApiBase,
        [string]$AuthHeader,
        [string]$MachineName,
        [string]$SiteName,
        [string]$PluginLabel,
        [string]$LogType = "all",
        [switch]$VerboseMode
    )

    $headers = @{
        "Authorization"           = $AuthHeader
        "X-Riseup-Source-Machine" = $MachineName
        "Content-Type"            = "application/json"
    }

    # Step 1: Request token
    $clearUrl = "$ApiBase/logs/clear"

    if ($VerboseMode) {
        Write-Host ""
        Write-Host "    [VERBOSE] DELETE $clearUrl" -ForegroundColor DarkGray
    }

    try {
        $step1Response = Invoke-RestMethod -Uri $clearUrl -Method Delete -Headers $headers -ErrorAction Stop

        if ($VerboseMode) {
            $respJson = $step1Response | ConvertTo-Json -Depth 5 -Compress
            Write-Host "    [VERBOSE] Step 1 Response: $respJson" -ForegroundColor DarkGray
        }
    } catch {
        $errorMsg = Get-ClearLogsErrorMessage $_
        $responseBody = Get-ClearLogsResponseBody $_

        return @{ Site = $SiteName; Plugin = $PluginLabel; Status = "FAILED"; Error = "Step 1: $errorMsg"; ResponseBody = $responseBody }
    }

    $isStep1Success = ($step1Response.success -eq $true)
    $hasToken = ($null -ne $step1Response.token -and $step1Response.token -ne "")

    if (-not $isStep1Success -or -not $hasToken) {
        $errorDetail = if ($step1Response.error) { $step1Response.error } elseif ($step1Response.message) { $step1Response.message } else { "No token returned" }
        $errorCode = if ($step1Response.code) { " [$($step1Response.code)]" } else { "" }

        return @{ Site = $SiteName; Plugin = $PluginLabel; Status = "FAILED"; Error = "Step 1: $errorDetail$errorCode"; ResponseBody = $null }
    }

    $token = $step1Response.token

    # Step 2: Confirm with token (include type filter)
    $confirmUrl = "$ApiBase/logs/clear/confirm"
    $confirmPayload = @{ token = $token }

    $hasTypeFilter = ($LogType -ne "all")

    if ($hasTypeFilter) {
        $confirmPayload["type"] = $LogType
    }

    $confirmBody = $confirmPayload | ConvertTo-Json -Compress

    try {
        $step2Response = Invoke-RestMethod -Uri $confirmUrl -Method Post -Headers $headers -Body $confirmBody -ErrorAction Stop
    } catch {
        $errorMsg = Get-ClearLogsErrorMessage $_
        $responseBody = Get-ClearLogsResponseBody $_

        return @{ Site = $SiteName; Plugin = $PluginLabel; Status = "FAILED"; Error = "Step 2: $errorMsg"; ResponseBody = $responseBody }
    }

    $isStep2Success = ($step2Response.success -eq $true)

    if (-not $isStep2Success) {
        $errorDetail = if ($step2Response.error) { $step2Response.error } elseif ($step2Response.message) { $step2Response.message } else { "Confirmation failed" }

        return @{ Site = $SiteName; Plugin = $PluginLabel; Status = "FAILED"; Error = "Step 2: $errorDetail"; ResponseBody = $null }
    }

    return @{ Site = $SiteName; Plugin = $PluginLabel; Status = "OK"; Error = $null; ResponseBody = $null }
}

# ── Plugin Filter Resolution ────────────────────────────────────────────

function Resolve-PluginFilter {
    param(
        [array]$AllPlugins,
        [string]$Filter
    )

    $normalizedFilter = $Filter.Trim().ToLower()

    $isQUpload = ($normalizedFilter -eq "q" -or $normalizedFilter -eq "qupload" -or $normalizedFilter -eq "quick-upload")
    $isRiseup = ($normalizedFilter -eq "r" -or $normalizedFilter -eq "riseup" -or $normalizedFilter -eq "riseup-asia" -or $normalizedFilter -eq "riseup-asia-uploader")

    if ($isQUpload) {
        return @($AllPlugins | Where-Object { $_.Slug -eq "qupload" })
    }

    if ($isRiseup) {
        return @($AllPlugins | Where-Object { $_.Slug -ne "qupload" })
    }

    # Fuzzy match on slug or name
    return @($AllPlugins | Where-Object {
        $_.Slug.ToLower().Contains($normalizedFilter) -or $_.Name.ToLower().Contains($normalizedFilter)
    })
}

# ── Log Type Filter Resolution ──────────────────────────────────────────

function Resolve-LogTypeFilter {
    param([string]$TypeFilter)

    $hasNoFilter = ([string]::IsNullOrWhiteSpace($TypeFilter))

    if ($hasNoFilter) {
        return "all"
    }

    $normalized = $TypeFilter.Trim().ToLower()

    switch ($normalized) {
        { $_ -eq "log" -or $_ -eq "info" -or $_ -eq "activity" } { return "log" }
        { $_ -eq "err" -or $_ -eq "error" -or $_ -eq "errors" }  { return "error" }
        { $_ -eq "stack" -or $_ -eq "stacktrace" -or $_ -eq "trace" } { return "stacktrace" }
        { $_ -eq "all" }    { return "all" }
        { $_ -eq "files" }  { return "files" }
        { $_ -eq "db" -or $_ -eq "database" } { return "database" }
        default { return "all" }
    }
}

# ── Summary ──────────────────────────────────────────────────────────────

function Show-ClearLogsSummary {
    param(
        [array]$Results,
        [string]$MachineName
    )

    Write-Host ""
    Write-Host "========================================" -ForegroundColor Magenta
    Write-Host "  Log Clearing Summary" -ForegroundColor Magenta
    Write-Host "========================================" -ForegroundColor Magenta

    $successCount = ($Results | Where-Object { $_.Status -eq "OK" }).Count
    $failCount = $Results.Count - $successCount

    foreach ($r in $Results) {
        $color = if ($r.Status -eq "OK") { "Green" } else { "Red" }
        $errorSuffix = if ($r.Error -and $r.Status -ne "OK") { " - $($r.Error)" } else { "" }
        Write-Host "  [$($r.Site)] $($r.Plugin): $($r.Status)$errorSuffix" -ForegroundColor $color
    }

    Write-Host ""
    Write-Host "  Total: $($Results.Count) | Success: $successCount | Failed: $failCount" -ForegroundColor $(if ($failCount -eq 0) { "Green" } else { "Yellow" })

    if ($failCount -gt 0) {
        Write-Host ""
        Write-Host "  TROUBLESHOOTING:" -ForegroundColor Yellow
        Write-Host "    403 rest_disabled: Enable logs_clear/logs_confirm in Riseup Settings > API Endpoints Configuration" -ForegroundColor Gray
        Write-Host "    403 Forbidden: Check that the WordPress user has 'activate_plugins' capability" -ForegroundColor Gray

        $hasMachineName = ($MachineName -ne "")

        if ($hasMachineName) {
            Write-Host "    403 machine_not_approved: Add '$MachineName' to approved_machines in plugin settings/settings.json" -ForegroundColor Gray
        }
        Write-Host "    404 Not Found: The plugin may not be installed/activated on that site" -ForegroundColor Gray
        Write-Host "    401 Unauthorized: Verify Base64 credentials in powershell.json" -ForegroundColor Gray
    }

    Write-Host "========================================" -ForegroundColor Magenta
}

# ── Error Extraction (PS 5.1 + PS 7+ compatible) ────────────────────────

function Get-ClearLogsResponseBody {
    param($ErrorRecord)

    # PS 7+: ErrorDetails.Message contains the response body
    $hasErrorDetails = ($ErrorRecord.ErrorDetails -and $ErrorRecord.ErrorDetails.Message)

    if ($hasErrorDetails) {
        $body = $ErrorRecord.ErrorDetails.Message
        $maxLen = 300
        $isTruncated = ($body.Length -gt $maxLen)

        if ($isTruncated) {
            return $body.Substring(0, $maxLen) + "..."
        }

        return $body
    }

    # PS 5.1: Try GetResponseStream()
    try {
        $response = $ErrorRecord.Exception.Response

        if ($null -ne $response) {
            $stream = $response.GetResponseStream()

            if ($null -ne $stream) {
                $reader = New-Object System.IO.StreamReader($stream)
                $body = $reader.ReadToEnd()
                $reader.Close()
                $maxLen = 300
                $isTruncated = ($body.Length -gt $maxLen)

                if ($isTruncated) {
                    return $body.Substring(0, $maxLen) + "..."
                }

                return $body
            }
        }
    } catch {
        # Swallowed intentionally — best-effort extraction
    }

    return $null
}

function Get-ClearLogsErrorMessage {
    param($ErrorRecord)

    # PS 7+: ErrorDetails.Message often contains structured JSON
    $hasErrorDetails = ($ErrorRecord.ErrorDetails -and $ErrorRecord.ErrorDetails.Message)

    if ($hasErrorDetails) {
        $body = $ErrorRecord.ErrorDetails.Message

        try {
            $json = $body | ConvertFrom-Json -ErrorAction Stop

            # WordPress REST error format: { "code": "...", "message": "...", "data": { "status": 403 } }
            $hasWpError = ($json.code -and $json.message)

            if ($hasWpError) {
                $statusCode = if ($json.data -and $json.data.status) { "$($json.data.status) " } else { "" }

                return "${statusCode}$($json.code) - $($json.message)"
            }

            # Plugin error format: { "success": false, "error": "..." }
            $hasPluginError = ($json.error)

            if ($hasPluginError) {
                return $json.error
            }
        } catch {
            # Not JSON — return as-is (truncated)
            $maxLen = 200
            $isTruncated = ($body.Length -gt $maxLen)

            if ($isTruncated) {
                return $body.Substring(0, $maxLen) + "..."
            }

            return $body
        }
    }

    # PS 5.1: Try Response object
    $response = $ErrorRecord.Exception.Response

    if ($null -ne $response) {
        $statusCode = [int]$response.StatusCode

        try {
            $stream = $response.GetResponseStream()

            if ($null -ne $stream) {
                $reader = New-Object System.IO.StreamReader($stream)
                $body = $reader.ReadToEnd()
                $reader.Close()
                $json = $body | ConvertFrom-Json -ErrorAction SilentlyContinue

                if ($json -and $json.code -and $json.message) {
                    return "$statusCode $($json.code) - $($json.message)"
                }

                if ($json -and $json.error) {
                    return "$statusCode - $($json.error)"
                }

                $bodyPreview = if ($body.Length -gt 200) { $body.Substring(0, 200) + "..." } else { $body }

                return "$statusCode - $bodyPreview"
            }
        } catch {
            return "$statusCode"
        }

        return "$statusCode"
    }

    return $ErrorRecord.Exception.Message
}

# ── Shared Helpers ───────────────────────────────────────────────────────

function Get-PluginApiNamespace {
    param([string]$PluginSlug)

    $namespaceMap = @{
        "riseup-asia-uploader" = "riseup-asia-api/v1"
        "qupload"             = "qupload-api/v1"
    }

    $hasMapping = $namespaceMap.ContainsKey($PluginSlug)

    if ($hasMapping) {
        return $namespaceMap[$PluginSlug]
    }

    return "$PluginSlug-api/v1"
}

function Build-BasicAuthHeader {
    param([string]$Username, [string]$Password)

    $pair = "${Username}:${Password}"
    $bytes = [System.Text.Encoding]::UTF8.GetBytes($pair)
    $encoded = [Convert]::ToBase64String($bytes)

    return "Basic $encoded"
}
