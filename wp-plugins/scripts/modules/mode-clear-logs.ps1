# Module: mode-clear-logs.ps1
# Remote log clearing for configured sites via the logs/clear API.
# Two-step flow: DELETE /logs/clear -> POST /logs/clear/confirm with token.
# Dot-sourced by run.ps1 — expects $Config, $ScriptDir, helpers, plugin-helpers loaded.
# Expects: $site, $exclude, $sync, $index

function Invoke-ClearLogsMode {
    param([switch]$ForceAll)
    Write-Host ""
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

            $clearResult = Invoke-TwoStepLogClear -ApiBase $apiBase -AuthHeader $authHeader -MachineName $machineName -SiteName $siteName -PluginLabel $pluginLabel

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
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Magenta
    Write-Host "  Log Clearing Summary" -ForegroundColor Magenta
    Write-Host "========================================" -ForegroundColor Magenta

    $successCount = ($results | Where-Object { $_.Status -eq "OK" }).Count
    $failCount = $results.Count - $successCount

    foreach ($r in $results) {
        $color = if ($r.Status -eq "OK") { "Green" } else { "Red" }
        $errorSuffix = if ($r.Error -and $r.Status -ne "OK") { " - $($r.Error)" } else { "" }
        Write-Host "  [$($r.Site)] $($r.Plugin): $($r.Status)$errorSuffix" -ForegroundColor $color
    }

    Write-Host ""
    Write-Host "  Total: $($results.Count) | Success: $successCount | Failed: $failCount" -ForegroundColor $(if ($failCount -eq 0) { "Green" } else { "Yellow" })

    if ($failCount -gt 0) {
        Write-Host ""
        Write-Host "  TROUBLESHOOTING:" -ForegroundColor Yellow
        Write-Host "    403 Forbidden: Check that the WordPress user has 'activate_plugins' capability" -ForegroundColor Gray
        Write-Host "    403 + machine_not_approved: Add '$machineName' to approved_machines in plugin settings" -ForegroundColor Gray
        Write-Host "    404 Not Found: The plugin may not be installed/activated on that site" -ForegroundColor Gray
        Write-Host "    401 Unauthorized: Verify Base64 credentials in powershell.json" -ForegroundColor Gray
    }

    Write-Host "========================================" -ForegroundColor Magenta

    exit $(if ($failCount -eq 0) { 0 } else { 1 })
}

# ── Two-step log clear flow ──────────────────────────────────────────────

function Invoke-TwoStepLogClear {
    param(
        [string]$ApiBase,
        [string]$AuthHeader,
        [string]$MachineName,
        [string]$SiteName,
        [string]$PluginLabel
    )

    $headers = @{
        "Authorization"           = $AuthHeader
        "X-Riseup-Source-Machine" = $MachineName
        "Content-Type"            = "application/json"
    }

    # Step 1: Request token
    $clearUrl = "$ApiBase/logs/clear"

    try {
        $step1Response = Invoke-RestMethod -Uri $clearUrl -Method Delete -Headers $headers -ErrorAction Stop
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

    # Step 2: Confirm with token
    $confirmUrl = "$ApiBase/logs/clear/confirm"
    $confirmBody = @{ token = $token } | ConvertTo-Json -Compress

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
