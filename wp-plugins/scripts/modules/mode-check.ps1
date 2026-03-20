# Module: mode-check.ps1
# Preflight readiness check across all sites — read-only diagnostics.
# Queries /status on each plugin for every enabled site.
# Dot-sourced by run.ps1 — expects $Config, $ScriptDir, helpers, plugin-helpers loaded.

function Invoke-CheckMode {
    param(
        [switch]$VerboseMode
    )
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  Preflight Readiness Check (-check)" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""

    if (-not $Config.wpPlugins -or -not $Config.wpPlugins.sites -or $Config.wpPlugins.sites.Count -eq 0) {
        Write-Host "ERROR: No sites configured in powershell.json (wpPlugins.sites)" -ForegroundColor Red
        exit 1
    }

    # Resolve target sites (supports -site, -i, -xs)
    $allSites = @($Config.wpPlugins.sites)
    $excludedSiteNames = @()
    $hasExclusions = (-not [string]::IsNullOrWhiteSpace($exclude))

    if ($hasExclusions) {
        $excludedSiteNames = @($exclude -split ',' | ForEach-Object { $_.Trim() })
    }

    $targetSites = Resolve-TargetSites -Index $index -SiteName $site -ExcludedSiteNames $excludedSiteNames -AllSites $allSites
    $enabledSites = @($targetSites | Where-Object { $_.enabled -ne $false })

    if ($enabledSites.Count -eq 0) {
        Write-Host "No enabled sites found." -ForegroundColor Yellow
        exit 0
    }

    # Build plugin namespace list
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

    Write-Host "  Checking $($enabledSites.Count) site(s) x $($pluginNamespaces.Count) plugin(s)..." -ForegroundColor Gray
    Write-Host ""

    # Check each site/plugin combination
    $results = @()

    foreach ($targetSite in $enabledSites) {
        $siteName = $targetSite.name
        $siteUrl = $targetSite.url.TrimEnd("/")
        $cred = Get-DefaultSiteCredential $targetSite

        Write-Host "  $siteName ($siteUrl)" -ForegroundColor White

        if (-not $cred) {
            Write-Host "    NO CREDENTIALS" -ForegroundColor Red
            foreach ($ns in $pluginNamespaces) {
                $results += @{
                    Site = $siteName; Plugin = $ns.Name; Status = "NO CREDS"
                    Version = "-"; WpVersion = "-"; Endpoints = @()
                    ResponseMs = $null
                }
            }
            Write-Host ""
            continue
        }

        $authHeader = Build-BasicAuthHeader $cred.Username $cred.Password

        foreach ($ns in $pluginNamespaces) {
            $statusUrl = "$siteUrl/wp-json/$($ns.Namespace)/status"
            $sw = [System.Diagnostics.Stopwatch]::StartNew()

            try {
                $headers = @{ "Authorization" = $authHeader }

                if ($VerboseMode) {
                    Write-Host "      [VERBOSE] GET $statusUrl" -ForegroundColor DarkGray
                }

                # Use Invoke-WebRequest to get raw response (Invoke-RestMethod chokes on PHP noise)
                $rawResp = Invoke-WebRequest -Uri $statusUrl -Method Get -Headers $headers -UseBasicParsing -TimeoutSec 15 -ErrorAction Stop
                $rawBody = $rawResp.Content

                if ($VerboseMode) {
                    Write-Host "      [VERBOSE] Response: $rawBody" -ForegroundColor DarkGray
                }

                $sw.Stop()
                $elapsed = $sw.ElapsedMilliseconds

                # Strip PHP warnings/notices before JSON
                $jsonBody = $rawBody
                $jsonStart = $rawBody.IndexOf('{')

                if ($jsonStart -gt 0) { $jsonBody = $rawBody.Substring($jsonStart) }

                $statusResp = $jsonBody | ConvertFrom-Json -ErrorAction Stop

                # Extract version from envelope: Results[0].Version
                $ver = "?"
                $wpVer = "?"
                $hasResults = ($null -ne $statusResp.Results -and $statusResp.Results.Count -gt 0)

                if ($hasResults) {
                    $firstResult = $statusResp.Results[0]
                    if ($firstResult.Version) { $ver = $firstResult.Version }
                    if ($firstResult.Wp) { $wpVer = $firstResult.Wp }
                    elseif ($firstResult.WpVersion) { $wpVer = $firstResult.WpVersion }
                }

                # Fallback: top-level properties (legacy format)
                if ($ver -eq "?" -and $statusResp.version) { $ver = $statusResp.version }
                if ($wpVer -eq "?" -and $statusResp.wordpress_version) { $wpVer = $statusResp.wordpress_version }

                # Check version readiness (v2.17.0+ for full API)
                $isReady = $false
                if ($ver -ne "?") {
                    $parts = $ver -split '\.'
                    $major = [int]$parts[0]
                    $minor = if ($parts.Count -gt 1) { [int]$parts[1] } else { 0 }
                    $isReady = ($major -gt 2 -or ($major -eq 2 -and $minor -ge 17))
                }

                # Parse registered routes from status response
                $registeredRoutes = @()
                if ($hasResults -and $firstResult.RegisteredRoutes) {
                    $registeredRoutes = @($firstResult.RegisteredRoutes)
                }

                # Detect available endpoints from registered routes
                $availableEndpoints = @()
                $endpointChecks = @("upload", "plugins", "logs/status", "logs/clear", "logs/retrieve", "machines/approve")

                foreach ($ep in $endpointChecks) {
                    $pattern = "/$($ns.Namespace)/$ep"
                    $found = $registeredRoutes | Where-Object { $_.Route -eq $pattern }
                    if ($found -or $isReady) {
                        $availableEndpoints += $ep
                    }
                }

                # Version color
                $vColor = if ($isReady) { "Green" } else { "Yellow" }
                $readyLabel = if ($isReady) { "READY" } else { "OUTDATED (needs v2.17.0+)" }
                $readyColor = if ($isReady) { "Green" } else { "Yellow" }

                # Extract extra info for verbose
                $phpVer = "-"
                $dbAvail = $false
                $serverTime = "-"
                $uploadMax = "-"
                $memLimit = "-"
                if ($hasResults) {
                    if ($firstResult.Php) { $phpVer = $firstResult.Php }
                    elseif ($firstResult.PhpVersion) { $phpVer = $firstResult.PhpVersion }
                    if ($null -ne $firstResult.DbAvailable) { $dbAvail = $firstResult.DbAvailable }
                    if ($firstResult.ServerTime) { $serverTime = $firstResult.ServerTime }
                    if ($firstResult.UploadMaxFilesize) { $uploadMax = $firstResult.UploadMaxFilesize }
                    if ($firstResult.MemoryLimit) { $memLimit = $firstResult.MemoryLimit }
                }

                Write-Host "    $($ns.Name)" -NoNewline -ForegroundColor Gray
                Write-Host " v$ver" -NoNewline -ForegroundColor $vColor
                Write-Host " | WP $wpVer" -NoNewline -ForegroundColor Gray
                Write-Host " | ${elapsed}ms" -NoNewline -ForegroundColor DarkGray
                Write-Host " | $readyLabel" -ForegroundColor $readyColor

                if ($VerboseMode) {
                    # Server details
                    Write-Host "      PHP: $phpVer | DB: $dbAvail | Memory: $memLimit | Upload Max: $uploadMax" -ForegroundColor DarkGray
                    Write-Host "      Server Time: $serverTime" -ForegroundColor DarkGray

                    # Registered routes grouped by category
                    if ($registeredRoutes.Count -gt 0) {
                        Write-Host "      Registered Routes ($($registeredRoutes.Count)):" -ForegroundColor DarkGray

                        # Group by method for a compact view
                        $coreRoutes = @($registeredRoutes | Where-Object { $_.Route -match '(status|openapi|opcache)' })
                        $pluginRoutes = @($registeredRoutes | Where-Object { $_.Route -match '/plugins' })
                        $logRoutes = @($registeredRoutes | Where-Object { $_.Route -match '/(logs|error)' })
                        $machineRoutes = @($registeredRoutes | Where-Object { $_.Route -match '/machines' })
                        $otherRoutes = @($registeredRoutes | Where-Object {
                            $_.Route -notmatch '(status|openapi|opcache|/plugins|/(logs|error)|/machines|/agents|/snapshots|/users|/cloud-storage|/posts|/categories|/media|/upload)'
                        })

                        $categories = @(
                            @{ Label = "Core";     Routes = $coreRoutes },
                            @{ Label = "Plugins";  Routes = $pluginRoutes },
                            @{ Label = "Logs";     Routes = $logRoutes },
                            @{ Label = "Machines"; Routes = $machineRoutes }
                        )

                        foreach ($cat in $categories) {
                            if ($cat.Routes.Count -gt 0) {
                                $routeSummary = ($cat.Routes | ForEach-Object {
                                    $shortRoute = $_.Route -replace ".*/$($ns.Namespace)/", ""
                                    $methods = $_.Methods -join ","
                                    "$shortRoute [$methods]"
                                }) -join ", "
                                Write-Host "        $($cat.Label): $routeSummary" -ForegroundColor DarkGray
                            }
                        }

                        # Features (if present in Riseup response)
                        if ($firstResult.Features) {
                            $enabledFeatures = @()
                            $firstResult.Features.PSObject.Properties | ForEach-Object {
                                if ($_.Value -eq $true) { $enabledFeatures += $_.Name }
                            }
                            if ($enabledFeatures.Count -gt 0) {
                                Write-Host "      Features: $($enabledFeatures -join ', ')" -ForegroundColor DarkGray
                            }
                        }
                    } else {
                        Write-Host "      No registered routes in response" -ForegroundColor DarkYellow
                    }
                } elseif ($isReady) {
                    Write-Host "      Endpoints: $($availableEndpoints -join ', ')" -ForegroundColor DarkGray
                }

                $results += @{
                    Site = $siteName; Plugin = $ns.Name; Status = $readyLabel
                    Version = $ver; WpVersion = $wpVer; Endpoints = $availableEndpoints
                    ResponseMs = $elapsed
                }

            } catch {
                $sw.Stop()
                $elapsed = $sw.ElapsedMilliseconds

                # Try to extract HTTP status code
                $errorDetail = "Connection failed"
                $response = $_.Exception.Response

                if ($null -ne $response) {
                    $statusCode = [int]$response.StatusCode
                    $errorDetail = "$statusCode - $($_.Exception.Message)"
                } else {
                    $errorDetail = $_.Exception.Message
                }

                # Truncate long errors
                $isTooLong = ($errorDetail.Length -gt 120)
                if ($isTooLong) {
                    $errorDetail = $errorDetail.Substring(0, 120) + "..."
                }

                Write-Host "    $($ns.Name)" -NoNewline -ForegroundColor Gray
                Write-Host " UNREACHABLE" -NoNewline -ForegroundColor Red
                Write-Host " | ${elapsed}ms" -NoNewline -ForegroundColor DarkGray
                Write-Host " | $errorDetail" -ForegroundColor DarkRed

                $results += @{
                    Site = $siteName; Plugin = $ns.Name; Status = "UNREACHABLE"
                    Version = "-"; WpVersion = "-"; Endpoints = @()
                    ResponseMs = $elapsed
                }
            }
        }
        Write-Host ""
    }

    # ── Summary ──────────────────────────────────────────────────────
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  Readiness Summary" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""

    $readyCount = @($results | Where-Object { $_.Status -eq "READY" }).Count
    $outdatedCount = @($results | Where-Object { $_.Status -like "OUTDATED*" }).Count
    $unreachableCount = @($results | Where-Object { $_.Status -eq "UNREACHABLE" }).Count
    $noCredsCount = @($results | Where-Object { $_.Status -eq "NO CREDS" }).Count
    $totalCount = $results.Count

    Write-Host "  Total checks:  $totalCount" -ForegroundColor White
    Write-Host "  Ready:         $readyCount" -ForegroundColor Green
    if ($outdatedCount -gt 0) {
        Write-Host "  Outdated:      $outdatedCount" -ForegroundColor Yellow
    }
    if ($unreachableCount -gt 0) {
        Write-Host "  Unreachable:   $unreachableCount" -ForegroundColor Red
    }
    if ($noCredsCount -gt 0) {
        Write-Host "  No Creds:      $noCredsCount" -ForegroundColor Red
    }

    Write-Host ""

    # Actionable recommendations
    $hasOutdated = ($outdatedCount -gt 0)
    $hasUnreachable = ($unreachableCount -gt 0)

    if ($hasOutdated) {
        Write-Host "  To update outdated sites:" -ForegroundColor Yellow
        Write-Host "    .\run.ps1 -uas" -ForegroundColor DarkGray
        Write-Host ""
    }

    if ($hasUnreachable) {
        Write-Host "  Unreachable sites may have:" -ForegroundColor Yellow
        Write-Host "    - Plugin not installed or activated" -ForegroundColor DarkGray
        Write-Host "    - Site down or blocked by firewall" -ForegroundColor DarkGray
        Write-Host "    - Invalid credentials in powershell.json" -ForegroundColor DarkGray
        Write-Host ""
    }

    $isAllReady = ($readyCount -eq $totalCount)

    if ($isAllReady) {
        Write-Host "  All endpoints ready!" -ForegroundColor Green
    }

    Write-Host "========================================" -ForegroundColor Cyan

    exit $(if ($readyCount -eq $totalCount) { 0 } else { 1 })
}
