# Module: mode-approve-machine.ps1
# Remotely approve a machine name on all configured sites via REST API.
# Calls PUT /machines/approve on both plugins for each target site.
# Dot-sourced by run.ps1 — expects $Config, $ScriptDir, helpers, plugin-helpers loaded.

function Invoke-ApproveMachineMode {
    param(
        [string]$MachineNameToApprove
    )

    Write-Host ""
    Write-Host "========================================" -ForegroundColor Magenta
    Write-Host "  Machine Approval Mode (-am)" -ForegroundColor Magenta
    Write-Host "========================================" -ForegroundColor Magenta
    Write-Host ""

    $isMachineEmpty = ([string]::IsNullOrWhiteSpace($MachineNameToApprove))

    if ($isMachineEmpty) {
        $MachineNameToApprove = $env:COMPUTERNAME
        Write-Host "  No machine name specified, using current: $MachineNameToApprove" -ForegroundColor Yellow
    }

    Write-Host "  Machine to approve: $MachineNameToApprove" -ForegroundColor Cyan
    Write-Host ""

    if (-not $Config.wpPlugins -or -not $Config.wpPlugins.sites -or $Config.wpPlugins.sites.Count -eq 0) {
        Write-Host "ERROR: No sites configured in powershell.json (wpPlugins.sites)" -ForegroundColor Red
        exit 1
    }

    Show-ConfiguredSites

    # Target all enabled sites
    $allSites = @($Config.wpPlugins.sites)
    $targetSites = @($allSites | Where-Object { $_.enabled -ne $false })

    if ($targetSites.Count -eq 0) {
        Write-Host "No enabled sites found." -ForegroundColor Yellow
        exit 0
    }

    Write-Host "  Target: $($targetSites.Count) enabled site(s)" -ForegroundColor Cyan
    Write-Host ""

    # Determine which plugins to call
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

    # Execute approval calls
    $results = @()

    foreach ($targetSite in $targetSites) {
        $siteName = $targetSite.name
        $siteUrl = $targetSite.url.TrimEnd("/")
        $cred = Get-DefaultSiteCredential $targetSite

        if (-not $cred) {
            Write-Host "  [$siteName] SKIPPED: No valid credentials" -ForegroundColor Red
            foreach ($ns in $pluginNamespaces) {
                $results += @{ Site = $siteName; Plugin = $ns.Name; Status = "SKIPPED"; Error = "No credentials"; Detail = $null }
            }
            continue
        }

        $authHeader = Build-BasicAuthHeader $cred.Username $cred.Password

        foreach ($ns in $pluginNamespaces) {
            $pluginLabel = $ns.Name
            $apiBase = "$siteUrl/wp-json/$($ns.Namespace)"
            $approveUrl = "$apiBase/machines/approve"

            Write-Host "  [$siteName] $pluginLabel..." -ForegroundColor Yellow -NoNewline

            $headers = @{
                "Authorization" = $authHeader
                "Content-Type"  = "application/json"
            }

            $body = @{ machine = $MachineNameToApprove } | ConvertTo-Json -Compress

            try {
                $response = Invoke-RestMethod -Uri $approveUrl -Method Put -Headers $headers -Body $body -ErrorAction Stop

                $isSuccess = ($response.Success -eq $true)

                if ($isSuccess) {
                    $isAlready = ($response.already_approved -eq $true)
                    $statusLabel = if ($isAlready) { " ALREADY APPROVED" } else { " OK" }
                    $color = if ($isAlready) { "Cyan" } else { "Green" }
                    Write-Host $statusLabel -ForegroundColor $color

                    $results += @{ Site = $siteName; Plugin = $pluginLabel; Status = "OK"; Error = $null; Detail = $response.Message }
                } else {
                    $errorMsg = if ($response.Error) { $response.Error } else { "Unknown error" }
                    Write-Host " FAILED" -ForegroundColor Red
                    Write-Host "    Error: $errorMsg" -ForegroundColor DarkYellow

                    $results += @{ Site = $siteName; Plugin = $pluginLabel; Status = "FAILED"; Error = $errorMsg; Detail = $null }
                }
            } catch {
                $errorMsg = Get-ApproveMachineErrorMessage $_
                $isNoRoute = ($errorMsg -match 'rest_no_route' -or $errorMsg -match '(^|\s)404(\s|$)')

                if ($isNoRoute) {
                    $errorMsg = "$errorMsg (machines/approve endpoint is not deployed on the remote site yet)"
                }

                Write-Host " FAILED" -ForegroundColor Red
                Write-Host "    Error: $errorMsg" -ForegroundColor DarkYellow

                $results += @{ Site = $siteName; Plugin = $pluginLabel; Status = "FAILED"; Error = $errorMsg; Detail = $null }
            }
        }
    }

    # Summary
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Magenta
    Write-Host "  Machine Approval Summary" -ForegroundColor Magenta
    Write-Host "========================================" -ForegroundColor Magenta

    $successCount = ($results | Where-Object { $_.Status -eq "OK" }).Count
    $failCount = $results.Count - $successCount

    foreach ($r in $results) {
        $color = if ($r.Status -eq "OK") { "Green" } else { "Red" }
        $suffix = ""

        if ($r.Status -eq "OK" -and $r.Detail) {
            $suffix = " - $($r.Detail)"
        } elseif ($r.Status -ne "OK" -and $r.Error) {
            $suffix = " - $($r.Error)"
        }

        Write-Host "  [$($r.Site)] $($r.Plugin): $($r.Status)$suffix" -ForegroundColor $color
    }

    Write-Host ""
    Write-Host "  Machine: $MachineNameToApprove" -ForegroundColor Cyan
    Write-Host "  Total: $($results.Count) | Success: $successCount | Failed: $failCount" -ForegroundColor $(if ($failCount -eq 0) { "Green" } else { "Yellow" })

    if ($failCount -gt 0) {
        Write-Host ""
        Write-Host "  TROUBLESHOOTING:" -ForegroundColor Yellow
        Write-Host "    404 Not Found / rest_no_route: Deploy latest plugins first to add PUT /machines/approve" -ForegroundColor Gray
        Write-Host "      Command: .\run.ps1 -uas" -ForegroundColor DarkGray
        Write-Host "    403 Forbidden: Check that the WordPress user has 'activate_plugins' capability" -ForegroundColor Gray
        Write-Host "    401 Unauthorized: Verify Base64 credentials in powershell.json" -ForegroundColor Gray
    }

    Write-Host "========================================" -ForegroundColor Magenta

    exit $(if ($failCount -eq 0) { 0 } else { 1 })
}

# ── Error Extraction ─────────────────────────────────────────────────────

function Get-ApproveMachineErrorMessage {
    param($ErrorRecord)

    # PS 7+: ErrorDetails.Message contains the response body
    $hasErrorDetails = ($ErrorRecord.ErrorDetails -and $ErrorRecord.ErrorDetails.Message)

    if ($hasErrorDetails) {
        $body = $ErrorRecord.ErrorDetails.Message

        try {
            $json = $body | ConvertFrom-Json -ErrorAction Stop

            $hasWpError = ($json.code -and $json.message)

            if ($hasWpError) {
                $statusCode = if ($json.data -and $json.data.status) { "$($json.data.status) " } else { "" }

                return "${statusCode}$($json.code) - $($json.message)"
            }

            $hasPluginError = ($json.Error)

            if ($hasPluginError) {
                return $json.Error
            }
        } catch {
            $maxLen = 200
            $isTruncated = ($body.Length -gt $maxLen)

            if ($isTruncated) {
                return $body.Substring(0, $maxLen) + "..."
            }

            return $body
        }
    }

    # PS 5.1 fallback
    $response = $ErrorRecord.Exception.Response

    if ($null -ne $response) {
        $statusCode = [int]$response.StatusCode

        return "$statusCode - $($ErrorRecord.Exception.Message)"
    }

    return $ErrorRecord.Exception.Message
}
