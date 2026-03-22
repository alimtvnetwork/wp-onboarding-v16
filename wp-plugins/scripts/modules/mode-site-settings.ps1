# Module: mode-site-settings.ps1
# Remote site settings management via REST API.
# Dot-sourced by run.ps1 — expects $Config, $ScriptDir, helpers, plugin-helpers loaded.
# Supports: -ss (get), -ss -set 'key=value' (update), convenience flags for common operations.

function Invoke-SiteSettingsMode {
    param(
        [switch]$VerboseMode,
        [string]$SettingValue,
        [string]$SettingAction
    )

    Write-Host ""
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  Site Settings (-ss)" -ForegroundColor Cyan
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

    # Resolve the Riseup Asia Uploader namespace (site-settings is only on the uploader)
    $uploaderSlug = $Config.wpPlugins.defaultUploader
    if (-not $uploaderSlug -or -not $Config.wpPlugins.plugins.$uploaderSlug) {
        Write-Host "ERROR: No default uploader configured in powershell.json" -ForegroundColor Red
        exit 1
    }
    $uploaderNamespace = Get-PluginApiNamespace $uploaderSlug

    # Determine mode: GET (read) or PUT (update)
    $isUpdate = (-not [string]::IsNullOrWhiteSpace($SettingAction))
    $updateBody = $null

    if ($isUpdate) {
        $updateBody = Build-SettingsUpdateBody -Action $SettingAction -Value $SettingValue
        if ($null -eq $updateBody) {
            Write-Host "ERROR: Invalid setting action: $SettingAction" -ForegroundColor Red
            Write-Host ""
            Write-Host "Available actions:" -ForegroundColor Yellow
            Write-Host "  debug-on / debug-off       Toggle WP_DEBUG (+ WP_DEBUG_LOG)" -ForegroundColor Gray
            Write-Host "  debug-display-on / off     Toggle WP_DEBUG_DISPLAY" -ForegroundColor Gray
            Write-Host "  seo-on / seo-off           Toggle search engine visibility" -ForegroundColor Gray
            Write-Host "  upload-size <SIZE>          Set upload_max_filesize (e.g., 256M)" -ForegroundColor Gray
            Write-Host "  post-size <SIZE>            Set post_max_size (e.g., 256M)" -ForegroundColor Gray
            Write-Host "  memory-limit <SIZE>         Set memory_limit (e.g., 512M)" -ForegroundColor Gray
            Write-Host ""
            exit 1
        }
    }

    $results = @()

    foreach ($targetSite in $enabledSites) {
        $siteName = $targetSite.name
        $siteUrl = $targetSite.url.TrimEnd("/")
        $cred = Get-DefaultSiteCredential $targetSite

        Write-Host "  $siteName ($siteUrl)" -ForegroundColor White

        if (-not $cred) {
            Write-Host "    NO CREDENTIALS" -ForegroundColor Red
            $results += @{ Site = $siteName; Status = "NO CREDS"; Settings = $null }
            Write-Host ""
            continue
        }

        $authHeader = Build-BasicAuthHeader $cred.Username $cred.Password
        $settingsUrl = "$siteUrl/wp-json/$uploaderNamespace/site-settings"

        if ($isUpdate) {
            # PUT request
            $jsonBody = $updateBody | ConvertTo-Json -Compress
            $sw = [System.Diagnostics.Stopwatch]::StartNew()

            try {
                $headers = @{
                    "Authorization" = $authHeader
                    "Content-Type"  = "application/json"
                }

                if ($VerboseMode) {
                    Write-Host "      [VERBOSE] PUT $settingsUrl" -ForegroundColor DarkGray
                    Write-Host "      [VERBOSE] Body: $jsonBody" -ForegroundColor DarkGray
                }

                $rawResp = Invoke-WebRequest -Uri $settingsUrl -Method Put -Headers $headers -Body $jsonBody -UseBasicParsing -TimeoutSec 30 -ErrorAction Stop
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

                # Extract updated fields from envelope
                $updatedFields = @()
                $warnings = @()

                $hasResults = ($null -ne $resp.Results -and $resp.Results.Count -gt 0)
                if ($hasResults) {
                    $firstResult = $resp.Results[0]
                    if ($firstResult.updated) {
                        $firstResult.updated.PSObject.Properties | ForEach-Object {
                            $updatedFields += "$($_.Name)=$($_.Value)"
                        }
                    }
                    if ($firstResult.warnings) {
                        $warnings = @($firstResult.warnings)
                    }
                }

                if ($updatedFields.Count -gt 0) {
                    Write-Host "    UPDATED" -NoNewline -ForegroundColor Green
                    Write-Host " | ${elapsed}ms" -NoNewline -ForegroundColor DarkGray
                    Write-Host " | $($updatedFields -join ', ')" -ForegroundColor Cyan
                } else {
                    Write-Host "    NO CHANGES" -NoNewline -ForegroundColor Yellow
                    Write-Host " | ${elapsed}ms" -ForegroundColor DarkGray
                }

                foreach ($w in $warnings) {
                    Write-Host "    WARNING: $w" -ForegroundColor Yellow
                }

                $results += @{ Site = $siteName; Status = "UPDATED"; Settings = $null; Duration = $elapsed }

            } catch {
                $sw.Stop()
                $elapsed = $sw.ElapsedMilliseconds
                $errorDetail = $_.Exception.Message
                $isTooLong = ($errorDetail.Length -gt 120)
                if ($isTooLong) { $errorDetail = $errorDetail.Substring(0, 120) + "..." }

                Write-Host "    FAILED" -NoNewline -ForegroundColor Red
                Write-Host " | ${elapsed}ms" -NoNewline -ForegroundColor DarkGray
                Write-Host " | $errorDetail" -ForegroundColor DarkRed

                $results += @{ Site = $siteName; Status = "FAILED"; Settings = $null; Duration = $elapsed }
            }

        } else {
            # GET request (read settings)
            $sw = [System.Diagnostics.Stopwatch]::StartNew()

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

                # Extract settings from envelope
                $settings = $null
                $hasResults = ($null -ne $resp.Results -and $resp.Results.Count -gt 0)
                if ($hasResults) {
                    $settings = $resp.Results[0]
                }

                if ($null -ne $settings) {
                    Write-Host "    Settings retrieved" -NoNewline -ForegroundColor Green
                    Write-Host " | ${elapsed}ms" -ForegroundColor DarkGray

                    # Display key settings
                    $debugStatus = if ($settings.wpDebug) { "ON" } else { "OFF" }
                    $debugLogStatus = if ($settings.wpDebugLog) { "ON" } else { "OFF" }
                    $debugDisplayStatus = if ($settings.wpDebugDisplay) { "ON" } else { "OFF" }
                    $seoStatus = if ($settings.searchEngineVisible) { "Visible" } else { "Discouraged" }

                    $debugColor = if ($settings.wpDebug) { "Yellow" } else { "Green" }
                    $seoColor = if ($settings.searchEngineVisible) { "Green" } else { "Yellow" }

                    Write-Host "      WP_DEBUG:         $debugStatus" -ForegroundColor $debugColor
                    Write-Host "      WP_DEBUG_LOG:     $debugLogStatus" -ForegroundColor $(if ($settings.wpDebugLog) { "Yellow" } else { "Green" })
                    Write-Host "      WP_DEBUG_DISPLAY: $debugDisplayStatus" -ForegroundColor $(if ($settings.wpDebugDisplay) { "Red" } else { "Green" })
                    Write-Host "      Search Engines:   $seoStatus" -ForegroundColor $seoColor
                    Write-Host "      Upload Max:       $($settings.uploadMaxFilesize)" -ForegroundColor Gray
                    Write-Host "      Post Max Size:    $($settings.postMaxSize)" -ForegroundColor Gray
                    Write-Host "      Memory Limit:     $($settings.memoryLimit)" -ForegroundColor Gray
                    Write-Host "      PHP:              $($settings.phpVersion)" -ForegroundColor Gray
                    Write-Host "      WP:               $($settings.wpVersion)" -ForegroundColor Gray
                    Write-Host "      wp-config.php:    $(if ($settings.wpConfigWritable) { 'Writable' } else { 'Read-only' })" -ForegroundColor $(if ($settings.wpConfigWritable) { "Green" } else { "Yellow" })
                } else {
                    Write-Host "    EMPTY RESPONSE" -NoNewline -ForegroundColor Yellow
                    Write-Host " | ${elapsed}ms" -ForegroundColor DarkGray
                }

                $results += @{ Site = $siteName; Status = "OK"; Settings = $settings; Duration = $elapsed }

            } catch {
                $sw.Stop()
                $elapsed = $sw.ElapsedMilliseconds
                $errorDetail = $_.Exception.Message
                $isTooLong = ($errorDetail.Length -gt 120)
                if ($isTooLong) { $errorDetail = $errorDetail.Substring(0, 120) + "..." }

                Write-Host "    UNREACHABLE" -NoNewline -ForegroundColor Red
                Write-Host " | ${elapsed}ms" -NoNewline -ForegroundColor DarkGray
                Write-Host " | $errorDetail" -ForegroundColor DarkRed

                $results += @{ Site = $siteName; Status = "UNREACHABLE"; Settings = $null; Duration = $elapsed }
            }
        }
        Write-Host ""
    }

    # ── Summary ──────────────────────────────────────────────────────
    Write-Host "========================================" -ForegroundColor Cyan
    $actionLabel = if ($isUpdate) { "Update" } else { "Read" }
    Write-Host "  Site Settings $actionLabel Summary" -ForegroundColor Cyan
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

# ── Build update body from action string ─────────────────────────────────

function Build-SettingsUpdateBody {
    param(
        [string]$Action,
        [string]$Value
    )

    switch ($Action.ToLower()) {
        'debug-on' {
            return @{ wpDebug = $true; wpDebugLog = $true }
        }
        'debug-off' {
            return @{ wpDebug = $false; wpDebugLog = $false; wpDebugDisplay = $false }
        }
        'debug-display-on' {
            return @{ wpDebugDisplay = $true }
        }
        'debug-display-off' {
            return @{ wpDebugDisplay = $false }
        }
        'seo-on' {
            return @{ searchEngineVisible = $true }
        }
        'seo-off' {
            return @{ searchEngineVisible = $false }
        }
        'upload-size' {
            if ([string]::IsNullOrWhiteSpace($Value)) {
                Write-Host "ERROR: upload-size requires a value (e.g., 256M)" -ForegroundColor Red
                return $null
            }
            return @{ uploadMaxFilesize = $Value }
        }
        'post-size' {
            if ([string]::IsNullOrWhiteSpace($Value)) {
                Write-Host "ERROR: post-size requires a value (e.g., 256M)" -ForegroundColor Red
                return $null
            }
            return @{ postMaxSize = $Value }
        }
        'memory-limit' {
            if ([string]::IsNullOrWhiteSpace($Value)) {
                Write-Host "ERROR: memory-limit requires a value (e.g., 512M)" -ForegroundColor Red
                return $null
            }
            return @{ memoryLimit = $Value }
        }
        default {
            return $null
        }
    }
}
