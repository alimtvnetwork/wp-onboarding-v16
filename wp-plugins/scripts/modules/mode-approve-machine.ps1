# Module: mode-approve-machine.ps1
# Remotely approve a machine name on all configured sites via REST API.
# Calls PUT /machines/approve on both plugins for each target site.
# Dot-sourced by run.ps1 — expects $Config, $ScriptDir, helpers, plugin-helpers loaded.

function Invoke-ApproveMachineMode {
    param(
        [string]$MachineNameToApprove,
        [switch]$VerboseMode
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

    # ── Preflight: check endpoint availability ───────────────────────
    Write-Host "  Preflight: checking /machines/approve availability..." -ForegroundColor DarkCyan
    Write-Host ""

    $readySites = @{}

    foreach ($targetSite in $targetSites) {
        $siteName = $targetSite.name
        $siteUrl = $targetSite.url.TrimEnd("/")
        $cred = Get-DefaultSiteCredential $targetSite

        if (-not $cred) {
            foreach ($ns in $pluginNamespaces) {
                $key = "$siteName|$($ns.Name)"
                $readySites[$key] = $false
            }
            Write-Host "    [$siteName] SKIP - no credentials" -ForegroundColor Red
            continue
        }

        $authHeader = Build-BasicAuthHeader $cred.Username $cred.Password

        foreach ($ns in $pluginNamespaces) {
            $key = "$siteName|$($ns.Name)"
            $statusUrl = "$siteUrl/wp-json/$($ns.Namespace)/status"

            try {
                $headers = @{ "Authorization" = $authHeader }

                if ($VerboseMode) {
                    Write-Host "    [VERBOSE] GET $statusUrl" -ForegroundColor DarkGray
                }

                # Use Invoke-WebRequest to get raw response (Invoke-RestMethod chokes on PHP noise)
                $rawResp = Invoke-WebRequest -Uri $statusUrl -Method Get -Headers $headers -UseBasicParsing -TimeoutSec 15 -ErrorAction Stop
                $rawBody = $rawResp.Content

                if ($VerboseMode) {
                    Write-Host "    [VERBOSE] Response: $rawBody" -ForegroundColor DarkGray
                }

                # Strip PHP warnings/notices before JSON
                $jsonBody = $rawBody
                $jsonStart = $rawBody.IndexOf('{')
                $hasJsonStart = ($jsonStart -ge 0)

                if (-not $hasJsonStart) {
                    $readySites[$key] = $false
                    Write-Host "    [$siteName] $($ns.Name)" -ForegroundColor Yellow -NoNewline
                    Write-Host " NOT READY (no JSON in response)" -ForegroundColor Yellow
                    continue
                }

                if ($jsonStart -gt 0) { $jsonBody = $rawBody.Substring($jsonStart) }

                $statusResp = $jsonBody | ConvertFrom-Json -ErrorAction Stop

                # Extract version from envelope: Results[0].Version
                $ver = $null
                $hasResults = ($null -ne $statusResp.Results -and $statusResp.Results.Count -gt 0)

                if ($hasResults) {
                    $ver = $statusResp.Results[0].Version
                }

                # Fallback: check top-level .version (legacy format)
                if (-not $ver -and $statusResp.version) {
                    $ver = $statusResp.version
                }

                $hasVersion = (-not [string]::IsNullOrWhiteSpace($ver))

                if ($hasVersion) {
                    $parts = $ver -split '\.'
                    $major = [int]$parts[0]
                    $minor = if ($parts.Count -gt 1) { [int]$parts[1] } else { 0 }
                    $isReady = ($major -gt 2 -or ($major -eq 2 -and $minor -ge 17))

                    $readySites[$key] = $isReady

                    if ($isReady) {
                        Write-Host "    [$siteName] $($ns.Name) v$ver" -ForegroundColor Green -NoNewline
                        Write-Host " READY" -ForegroundColor Green
                    } else {
                        Write-Host "    [$siteName] $($ns.Name) v$ver" -ForegroundColor Yellow -NoNewline
                        Write-Host " NOT READY (needs v2.17.0+)" -ForegroundColor Yellow
                    }
                } else {
                    $readySites[$key] = $false
                    Write-Host "    [$siteName] $($ns.Name)" -ForegroundColor Yellow -NoNewline
                    Write-Host " NOT READY (no version in response)" -ForegroundColor Yellow
                }
            } catch {
                $readySites[$key] = $false
                Write-Host "    [$siteName] $($ns.Name)" -ForegroundColor Red -NoNewline
                Write-Host " UNREACHABLE" -ForegroundColor Red
            }
        }
    }

    $readyCount = ($readySites.Values | Where-Object { $_ -eq $true }).Count
    $totalCount = $readySites.Count

    Write-Host ""
    Write-Host "  Preflight: $readyCount/$totalCount endpoints ready" -ForegroundColor $(if ($readyCount -eq $totalCount) { "Green" } elseif ($readyCount -gt 0) { "Yellow" } else { "Red" })
    Write-Host ""

    if ($readyCount -eq 0) {
        Write-Host "  All endpoints unavailable. Deploy v2.17.0+ first:" -ForegroundColor Red
        Write-Host "    .\run.ps1 -uas" -ForegroundColor DarkGray
        Write-Host "========================================" -ForegroundColor Magenta
        exit 1
    }

    # Execute approval calls (only for ready endpoints)
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
            $key = "$siteName|$pluginLabel"
            $isEndpointReady = ($readySites.ContainsKey($key) -and $readySites[$key] -eq $true)

            if (-not $isEndpointReady) {
                Write-Host "  [$siteName] $pluginLabel..." -ForegroundColor DarkGray -NoNewline
                Write-Host " SKIPPED (not ready)" -ForegroundColor DarkGray
                $results += @{ Site = $siteName; Plugin = $pluginLabel; Status = "SKIPPED"; Error = "Endpoint not deployed (needs v2.17.0+)"; Detail = $null }
                continue
            }

            $apiBase = "$siteUrl/wp-json/$($ns.Namespace)"
            $approveUrl = "$apiBase/machines/approve"

            Write-Host "  [$siteName] $pluginLabel..." -ForegroundColor Yellow -NoNewline

            $headers = @{
                "Authorization" = $authHeader
                "Content-Type"  = "application/json"
            }

            $body = @{ machine = $MachineNameToApprove } | ConvertTo-Json -Compress

            if ($VerboseMode) {
                Write-Host ""
                Write-Host "    [VERBOSE] PUT $approveUrl" -ForegroundColor DarkGray
                Write-Host "    [VERBOSE] Body: $body" -ForegroundColor DarkGray
            }

            try {
                $response = Invoke-RestMethod -Uri $approveUrl -Method Put -Headers $headers -Body $body -ErrorAction Stop

                if ($VerboseMode) {
                    $respJson = $response | ConvertTo-Json -Depth 5 -Compress
                    Write-Host "    [VERBOSE] Response: $respJson" -ForegroundColor DarkGray
                }

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
