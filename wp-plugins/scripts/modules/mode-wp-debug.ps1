# Module: mode-wp-debug.ps1
# Toggle WP_DEBUG + WP_DEBUG_LOG on WordPress sites via REST API.
# Shortcut for: -ss -set 'debug-on' / -ss -set 'debug-off'
# Dot-sourced by run.ps1 — expects $Config, $ScriptDir, helpers, plugin-helpers loaded.

function Invoke-WpDebugMode {
    param(
        [string]$Action = "",
        [switch]$VerboseMode = $false
    )

    # ── Help ──
    $isShowHelp = ($Action -eq "help" -or $Action -eq "?")
    if ($isShowHelp) {
        Write-Host ""
        Write-Host "WP Debug Toggle — Enable/Disable WP_DEBUG on WordPress sites" -ForegroundColor Cyan
        Write-Host "==============================================================" -ForegroundColor Cyan
        Write-Host ""
        Write-Host "USAGE:" -ForegroundColor Yellow
        Write-Host "  .\run.ps1 -wpd on                     Enable WP_DEBUG on default site" -ForegroundColor White
        Write-Host "  .\run.ps1 -wpd off                    Disable WP_DEBUG on default site" -ForegroundColor White
        Write-Host "  .\run.ps1 -wpd on -a                  Enable WP_DEBUG on ALL sites" -ForegroundColor White
        Write-Host "  .\run.ps1 -wpd off -a                 Disable WP_DEBUG on ALL sites" -ForegroundColor White
        Write-Host "  .\run.ps1 -wpd on -site 'Test V1'     Enable on specific site" -ForegroundColor White
        Write-Host "  .\run.ps1 -wpd off -site 'T1,T2'      Disable on multiple sites" -ForegroundColor White
        Write-Host "  .\run.ps1 -wpd                        Show WP_DEBUG status on default site" -ForegroundColor White
        Write-Host "  .\run.ps1 -wpd -a                     Show WP_DEBUG status on ALL sites" -ForegroundColor White
        Write-Host "  .\run.ps1 -wpd -i 2                   Show/toggle on site #2" -ForegroundColor White
        Write-Host "  .\run.ps1 -wpd help                   Show this help" -ForegroundColor White
        Write-Host ""
        Write-Host "ACTIONS:" -ForegroundColor Yellow
        Write-Host "  on      Enable WP_DEBUG and WP_DEBUG_LOG" -ForegroundColor Gray
        Write-Host "  off     Disable WP_DEBUG, WP_DEBUG_LOG, and WP_DEBUG_DISPLAY" -ForegroundColor Gray
        Write-Host "  (empty) Show current WP_DEBUG status" -ForegroundColor Gray
        Write-Host ""
        exit 0
    }

    # ── Determine mode: status, on, or off ──
    $isStatusMode = [string]::IsNullOrWhiteSpace($Action)
    $isEnableMode = ($Action -eq "on" -or $Action -eq "enable" -or $Action -eq "1" -or $Action -eq "true")
    $isDisableMode = ($Action -eq "off" -or $Action -eq "disable" -or $Action -eq "0" -or $Action -eq "false")

    if (-not $isStatusMode -and -not $isEnableMode -and -not $isDisableMode) {
        Write-Host "ERROR: Invalid action '$Action'. Use 'on', 'off', or leave empty for status." -ForegroundColor Red
        Write-Host "  Run: .\run.ps1 -wpd help" -ForegroundColor Yellow
        exit 1
    }

    $actionLabel = if ($isEnableMode) { "Enable" } elseif ($isDisableMode) { "Disable" } else { "Status" }

    Write-Host ""
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  WP Debug $actionLabel (-wpd)" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""

    # ── Validate config ──
    if (-not $Config.wpPlugins -or -not $Config.wpPlugins.sites -or $Config.wpPlugins.sites.Count -eq 0) {
        Write-Host "ERROR: No sites configured in powershell.json (wpPlugins.sites)" -ForegroundColor Red
        exit 1
    }

    # ── Resolve target sites ──
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

    # ── Resolve uploader namespace ──
    $uploaderSlug = $Config.wpPlugins.defaultUploader
    if (-not $uploaderSlug -or -not $Config.wpPlugins.plugins.$uploaderSlug) {
        Write-Host "ERROR: No default uploader configured in powershell.json" -ForegroundColor Red
        exit 1
    }
    $uploaderNamespace = Get-PluginApiNamespace $uploaderSlug

    # ── Build update body ──
    $updateBody = $null
    if ($isEnableMode) {
        $updateBody = @{ wpDebug = $true; wpDebugLog = $true }
    } elseif ($isDisableMode) {
        $updateBody = @{ wpDebug = $false; wpDebugLog = $false; wpDebugDisplay = $false }
    }

    $results = @()

    foreach ($targetSite in $enabledSites) {
        $siteName = $targetSite.name
        $siteUrl = $targetSite.url.TrimEnd("/")
        $cred = Get-DefaultSiteCredential $targetSite

        Write-Host "  $siteName ($siteUrl)" -ForegroundColor White

        if (-not $cred) {
            Write-Host "    NO CREDENTIALS" -ForegroundColor Red
            $results += @{ Site = $siteName; Status = "NO CREDS" }
            Write-Host ""
            continue
        }

        $authHeader = Build-BasicAuthHeader $cred.Username $cred.Password
        $settingsUrl = "$siteUrl/wp-json/$uploaderNamespace/site-settings"
        $sw = [System.Diagnostics.Stopwatch]::StartNew()

        if ($isStatusMode) {
            # ── GET: Read current debug status ──
            try {
                $headers = @{ "Authorization" = $authHeader }

                if ($VerboseMode) {
                    Write-Host "      [VERBOSE] GET $settingsUrl" -ForegroundColor DarkGray
                }

                $rawResp = Invoke-WebRequest -Uri $settingsUrl -Method Get -Headers $headers -UseBasicParsing -TimeoutSec 15 -ErrorAction Stop
                $rawBody = $rawResp.Content
                $sw.Stop()
                $elapsed = $sw.ElapsedMilliseconds

                if ($VerboseMode) {
                    Write-Host "      [VERBOSE] Response: $rawBody" -ForegroundColor DarkGray
                }

                # Strip PHP noise
                $jsonStr = $rawBody
                $jsonStart = $rawBody.IndexOf('{')
                if ($jsonStart -gt 0) { $jsonStr = $rawBody.Substring($jsonStart) }

                $resp = $jsonStr | ConvertFrom-Json -ErrorAction Stop

                $settings = $null
                $hasResults = ($null -ne $resp.Results -and $resp.Results.Count -gt 0)
                if ($hasResults) { $settings = $resp.Results[0] }

                if ($null -ne $settings) {
                    $debugOn = [bool]$settings.wpDebug
                    $debugLogOn = [bool]$settings.wpDebugLog
                    $debugDisplayOn = [bool]$settings.wpDebugDisplay

                    $statusIcon = if ($debugOn) { "ON" } else { "OFF" }
                    $statusColor = if ($debugOn) { "Yellow" } else { "Green" }

                    Write-Host "    WP_DEBUG: " -NoNewline -ForegroundColor Gray
                    Write-Host "$statusIcon" -NoNewline -ForegroundColor $statusColor
                    Write-Host " | LOG: $(if ($debugLogOn) { 'ON' } else { 'OFF' }) | DISPLAY: $(if ($debugDisplayOn) { 'ON' } else { 'OFF' })" -NoNewline -ForegroundColor Gray
                    Write-Host " | ${elapsed}ms" -ForegroundColor DarkGray

                    $results += @{ Site = $siteName; Status = "OK"; Debug = $debugOn }
                } else {
                    Write-Host "    EMPTY RESPONSE" -NoNewline -ForegroundColor Yellow
                    Write-Host " | ${elapsed}ms" -ForegroundColor DarkGray
                    $results += @{ Site = $siteName; Status = "EMPTY" }
                }
            } catch {
                $sw.Stop()
                $elapsed = $sw.ElapsedMilliseconds
                $errorDetail = $_.Exception.Message
                if ($errorDetail.Length -gt 120) { $errorDetail = $errorDetail.Substring(0, 120) + "..." }

                Write-Host "    UNREACHABLE" -NoNewline -ForegroundColor Red
                Write-Host " | ${elapsed}ms" -NoNewline -ForegroundColor DarkGray
                Write-Host " | $errorDetail" -ForegroundColor DarkRed

                $results += @{ Site = $siteName; Status = "UNREACHABLE" }
            }
        } else {
            # ── PUT: Toggle debug ──
            try {
                $jsonBody = $updateBody | ConvertTo-Json -Compress
                $headers = @{
                    "Authorization" = $authHeader
                    "Content-Type"  = "application/json"
                }

                if ($VerboseMode) {
                    Write-Host "      [VERBOSE] PUT $settingsUrl" -ForegroundColor DarkGray
                    Write-Host "      [VERBOSE] Body: $jsonBody" -ForegroundColor DarkGray
                }

                $rawResp = Invoke-WebRequest -Uri $settingsUrl -Method Put -Headers $headers -Body $jsonBody -UseBasicParsing -TimeoutSec 15 -ErrorAction Stop
                $rawBody = $rawResp.Content
                $sw.Stop()
                $elapsed = $sw.ElapsedMilliseconds

                if ($VerboseMode) {
                    Write-Host "      [VERBOSE] Response: $rawBody" -ForegroundColor DarkGray
                }

                # Strip PHP noise
                $jsonStr = $rawBody
                $jsonStart = $rawBody.IndexOf('{')
                if ($jsonStart -gt 0) { $jsonStr = $rawBody.Substring($jsonStart) }

                $resp = $jsonStr | ConvertFrom-Json -ErrorAction Stop

                # Check for updated fields
                $updatedFields = @()
                if ($resp.Results -and $resp.Results.Count -gt 0) {
                    $result = $resp.Results[0]
                    if ($result.updatedFields) {
                        $updatedFields = @($result.updatedFields)
                    }
                }

                $newState = if ($isEnableMode) { "ON" } else { "OFF" }
                $stateColor = if ($isEnableMode) { "Yellow" } else { "Green" }

                Write-Host "    WP_DEBUG: " -NoNewline -ForegroundColor Gray
                Write-Host "$newState" -NoNewline -ForegroundColor $stateColor
                Write-Host " | ${elapsed}ms" -ForegroundColor DarkGray

                if ($updatedFields.Count -gt 0) {
                    Write-Host "      Changed: $($updatedFields -join ', ')" -ForegroundColor Cyan
                }

                $results += @{ Site = $siteName; Status = "UPDATED" }
            } catch {
                $sw.Stop()
                $elapsed = $sw.ElapsedMilliseconds
                $errorDetail = $_.Exception.Message
                if ($errorDetail.Length -gt 120) { $errorDetail = $errorDetail.Substring(0, 120) + "..." }

                Write-Host "    FAILED" -NoNewline -ForegroundColor Red
                Write-Host " | ${elapsed}ms" -NoNewline -ForegroundColor DarkGray
                Write-Host " | $errorDetail" -ForegroundColor DarkRed

                $results += @{ Site = $siteName; Status = "FAILED" }
            }
        }
        Write-Host ""
    }

    # ── Summary ──
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  WP Debug $actionLabel Summary" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""

    $okCount = @($results | Where-Object { $_.Status -eq "OK" -or $_.Status -eq "UPDATED" }).Count
    $failCount = @($results | Where-Object { $_.Status -eq "FAILED" -or $_.Status -eq "UNREACHABLE" }).Count
    $noCredsCount = @($results | Where-Object { $_.Status -eq "NO CREDS" }).Count

    Write-Host "  Total:       $($results.Count)" -ForegroundColor White
    Write-Host "  Success:     $okCount" -ForegroundColor Green
    if ($failCount -gt 0) {
        Write-Host "  Failed:      $failCount" -ForegroundColor Red
    }
    if ($noCredsCount -gt 0) {
        Write-Host "  No Creds:    $noCredsCount" -ForegroundColor Red
    }
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Cyan

    exit $(if ($failCount -eq 0) { 0 } else { 1 })
}
