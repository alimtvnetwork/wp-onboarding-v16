# Module: mode-clear-logs.ps1
# Remote log clearing for all configured sites via the logs/clear API.
# Two-step flow: DELETE /logs/clear → POST /logs/clear/confirm with token.
# Dot-sourced by run.ps1 — expects $Config, $ScriptDir, helpers, plugin-helpers loaded.
# Expects: $site, $exclude, $sync

function Invoke-ClearLogsMode {
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Magenta
    Write-Host "  Remote Log Clearing Mode (-cl)" -ForegroundColor Magenta
    Write-Host "========================================" -ForegroundColor Magenta
    Write-Host ""

    if (-not $Config.wpPlugins -or -not $Config.wpPlugins.sites -or $Config.wpPlugins.sites.Count -eq 0) {
        Write-Host "ERROR: No sites configured in powershell.json (wpPlugins.sites)" -ForegroundColor Red
        exit 1
    }

    Show-ConfiguredSites

    # Resolve target sites
    $targetSites = Get-TargetSites

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
        Write-Host "  [$($r.Site)] $($r.Plugin): $($r.Status)" -ForegroundColor $color
    }

    Write-Host ""
    Write-Host "  Total: $($results.Count) | Success: $successCount | Failed: $failCount" -ForegroundColor $(if ($failCount -eq 0) { "Green" } else { "Yellow" })
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
        $errorMsg = Get-RestErrorMessage $_

        return @{ Site = $SiteName; Plugin = $PluginLabel; Status = "FAILED"; Error = "Step 1: $errorMsg" }
    }

    $isStep1Success = ($step1Response.success -eq $true)
    $hasToken = ($null -ne $step1Response.token -and $step1Response.token -ne "")

    if (-not $isStep1Success -or -not $hasToken) {
        $errorDetail = if ($step1Response.error) { $step1Response.error } else { "No token returned" }

        return @{ Site = $SiteName; Plugin = $PluginLabel; Status = "FAILED"; Error = "Step 1: $errorDetail" }
    }

    $token = $step1Response.token

    # Step 2: Confirm with token
    $confirmUrl = "$ApiBase/logs/clear/confirm"
    $confirmBody = @{ token = $token } | ConvertTo-Json -Compress

    try {
        $step2Response = Invoke-RestMethod -Uri $confirmUrl -Method Post -Headers $headers -Body $confirmBody -ErrorAction Stop
    } catch {
        $errorMsg = Get-RestErrorMessage $_

        return @{ Site = $SiteName; Plugin = $PluginLabel; Status = "FAILED"; Error = "Step 2: $errorMsg" }
    }

    $isStep2Success = ($step2Response.success -eq $true)

    if (-not $isStep2Success) {
        $errorDetail = if ($step2Response.error) { $step2Response.error } else { "Confirmation failed" }

        return @{ Site = $SiteName; Plugin = $PluginLabel; Status = "FAILED"; Error = "Step 2: $errorDetail" }
    }

    return @{ Site = $SiteName; Plugin = $PluginLabel; Status = "OK"; Error = $null }
}

# ── Helpers ───────────────────────────────────────────────────────────────

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

function Get-RestErrorMessage {
    param($ErrorRecord)

    $response = $ErrorRecord.Exception.Response

    if ($null -ne $response) {
        $statusCode = [int]$response.StatusCode
        $statusDesc = $response.StatusDescription

        try {
            $reader = New-Object System.IO.StreamReader($response.GetResponseStream())
            $body = $reader.ReadToEnd()
            $reader.Close()
            $json = $body | ConvertFrom-Json -ErrorAction SilentlyContinue

            if ($json -and $json.error) {
                return "$statusCode $statusDesc - $($json.error)"
            }

            $bodyPreview = if ($body.Length -gt 100) { $body.Substring(0, 100) + "..." } else { $body }

            return "$statusCode $statusDesc - $bodyPreview"
        } catch {
            return "$statusCode $statusDesc"
        }
    }

    return $ErrorRecord.Exception.Message
}

function Get-TargetSites {
    $allEnabled = @($Config.wpPlugins.sites | Where-Object { $_.enabled -ne $false })

    if ($site -ne "") {
        $matchedSite = $Config.wpPlugins.sites | Where-Object { $_.name -eq $site }

        if (-not $matchedSite) {
            Write-Host "ERROR: Site '$site' not found." -ForegroundColor Red
            foreach ($s in $Config.wpPlugins.sites) { Write-Host "  - $($s.name)" -ForegroundColor Gray }
            exit 1
        }

        return @($matchedSite)
    }

    if ($exclude -ne "") {
        $excludeNames = @($exclude -split ',' | ForEach-Object { $_.Trim() })

        return @($allEnabled | Where-Object { $_.name -notin $excludeNames })
    }

    return $allEnabled
}
