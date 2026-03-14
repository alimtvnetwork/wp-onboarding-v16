# Module: mode-list-sites.ps1
# List sites mode: -ls / -lr / -listsites
# Dot-sourced by run.ps1 — expects $Config, $BackendDir, $ConfigFile.

function Invoke-ListSitesMode {
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  Configured Sites" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan

    # Section 1: Deploy Sites (powershell.json)
    Write-Host ""
    Write-Host "  DEPLOY SITES (powershell.json)" -ForegroundColor Yellow
    Write-Host "  Used by: -u, -ua, -uas (upload commands)" -ForegroundColor DarkGray
    Write-Host ""

    $hasDeploySites = $Config.wpPlugins -and $Config.wpPlugins.sites -and $Config.wpPlugins.sites.Count -gt 0

    if (-not $hasDeploySites) {
        Write-Host "    No deploy sites configured (wpPlugins.sites)" -ForegroundColor DarkGray
    } else {
        $siteIndex = 0
        foreach ($s in $Config.wpPlugins.sites) {
            $siteIndex++
            $isEnabled = $s.enabled -ne $false
            $statusIcon = if ($isEnabled) { "[ON]" } else { "[OFF]" }
            $statusColor = if ($isEnabled) { "Green" } else { "DarkGray" }
            $credCount = if ($s.credentials) { $s.credentials.Count } else { 0 }

            Write-Host "    $siteIndex. " -NoNewline -ForegroundColor White
            Write-Host "$statusIcon " -NoNewline -ForegroundColor $statusColor
            Write-Host "$($s.name)" -ForegroundColor $(if ($isEnabled) { "White" } else { "DarkGray" })
            Write-Host "       URL:         $($s.url)" -ForegroundColor Gray
            Write-Host "       Upload:      " -NoNewline -ForegroundColor Gray
            Write-Host ".\run.ps1 -u -site '$($s.name)'" -ForegroundColor DarkYellow
            Write-Host "       Upload all:  " -NoNewline -ForegroundColor Gray
            Write-Host ".\run.ps1 -uas -site '$($s.name)'" -ForegroundColor DarkYellow
            Write-Host "       Credentials: $credCount configured" -ForegroundColor Gray

            if ($s.credentials -and $s.credentials.Count -gt 0) {
                foreach ($cred in $s.credentials) {
                    $isDefault = if ($cred.isDefault) { " (default)" } else { "" }
                    Write-Host "         - $($cred.appName)$isDefault" -ForegroundColor DarkGray
                }
            }
            Write-Host ""
        }
        Write-Host "    Total: $siteIndex deploy site(s)" -ForegroundColor Cyan
    }

    # Section 2: Backend Seeds (config.json)
    Write-Host ""
    Write-Host "  BACKEND SITES (config.json)" -ForegroundColor Yellow
    Write-Host "  Seeded into the dashboard database on startup" -ForegroundColor DarkGray
    Write-Host ""

    $backendConfigPath = Join-Path $BackendDir $ConfigFile

    if (-not (Test-Path $backendConfigPath)) {
        Write-Host "    Backend config not found: $backendConfigPath" -ForegroundColor DarkGray
    } else {
        try {
            $backendConfig = Get-Content $backendConfigPath -Raw | ConvertFrom-Json
            $seedSites = $backendConfig.Seed.Sites
            $hasSeedSites = $seedSites -and $seedSites.Count -gt 0

            if (-not $hasSeedSites) {
                Write-Host "    No seed sites configured (Seed.Sites)" -ForegroundColor DarkGray
            } else {
                $seedIndex = 0
                foreach ($s in $seedSites) {
                    $seedIndex++
                    $category = if ($s.Category) { " [$($s.Category)]" } else { "" }
                    $credCount = if ($s.Credentials) { $s.Credentials.Count } else { 0 }
                    $hasLegacyCred = [bool]$s.Username

                    Write-Host "    $seedIndex. " -NoNewline -ForegroundColor White
                    Write-Host "$($s.Name)$category" -ForegroundColor White
                    Write-Host "       URL:         $($s.Url)" -ForegroundColor Gray

                    if ($hasLegacyCred -and $credCount -eq 0) {
                        Write-Host "       Credentials: 1 (legacy format)" -ForegroundColor Gray
                        Write-Host "         - $($s.Username)" -ForegroundColor DarkGray
                    } elseif ($credCount -gt 0) {
                        Write-Host "       Credentials: $credCount configured" -ForegroundColor Gray
                        foreach ($cred in $s.Credentials) {
                            $isDefault = if ($cred.IsDefault) { " (default)" } else { "" }
                            Write-Host "         - $($cred.AppName)$isDefault" -ForegroundColor DarkGray
                        }
                    } else {
                        Write-Host "       Credentials: none" -ForegroundColor DarkGray
                    }
                    Write-Host ""
                }
                Write-Host "    Total: $seedIndex backend site(s)" -ForegroundColor Cyan
            }
        } catch {
            Write-Host "    Failed to parse backend config: $_" -ForegroundColor Red
        }
    }

    Write-Host ""
    exit 0
}
