# Module: summary-printer.ps1
# Structured summary output for multi-site upload operations.
# Dot-sourced by run.ps1 - no dependencies.

function Write-UploadSummary {
    param(
        [Parameter(Mandatory)][array]$Results,
        [Parameter(Mandatory)][int]$TotalSites,
        [Parameter(Mandatory)][int]$TotalPlugins,
        [string]$FailureLogsDir
    )

    Write-Host ""
    Write-Host "========================================" -ForegroundColor Magenta
    Write-Host "  Multi-Site Upload Summary" -ForegroundColor Magenta
    Write-Host "========================================" -ForegroundColor Magenta

    # Group by site (preserving index order within each group)
    $sorted = @($Results | Sort-Object { $_.Index })
    $grouped = $sorted | Group-Object -Property Site

    foreach ($group in $grouped) {
        $siteName = $group.Name
        $siteUrl = if ($group.Group[0].SiteUrl) { $group.Group[0].SiteUrl } else { "" }
        $siteLabel = if ($siteUrl) { "$siteName ($siteUrl)" } else { $siteName }

        Write-Host ""
        Write-Host "  $siteLabel" -ForegroundColor Cyan

        foreach ($r in $group.Group) {
            $isOk = ($r.Status -eq "OK")
            $isSkip = ($r.Status -match "SKIP")

            if ($isOk) {
                $symbol = "+"
                $color = "Green"
            } elseif ($isSkip) {
                $symbol = "o"
                $color = "Yellow"
            } else {
                $symbol = "x"
                $color = "Red"
            }

            $duration = if ($r.Duration -and $r.Duration -gt 0) { "{0:N1}s" -f $r.Duration } else { "-" }
            $vLabel = if ($r.Version -and $r.Version -ne "unknown") { "v$($r.Version)" } else { "" }
            $statusLabel = $r.Status

            Write-Host ("    $symbol {0,-24} {1,-10} {2,-20} {3}" -f $r.Plugin, $vLabel, $statusLabel, $duration) -ForegroundColor $color
        }
    }

    # Totals
    $successCount = ($Results | Where-Object { $_.Status -eq "OK" }).Count
    $failCount = ($Results | Where-Object { $_.Status -match "FAIL" }).Count
    $skipCount = ($Results | Where-Object { $_.Status -match "SKIP" }).Count
    $totalOps = $Results.Count

    Write-Host ""
    Write-Host "  $('-' * 40)" -ForegroundColor DarkGray
    Write-Host "  Sites: $TotalSites | Plugins: $TotalPlugins | Total: $totalOps" -ForegroundColor White

    $summaryColor = if ($failCount -eq 0) { "Green" } else { "Yellow" }
    $summaryLine = "  Success: $successCount | Failed: $failCount"
    if ($skipCount -gt 0) { $summaryLine += " | Skipped: $skipCount" }
    Write-Host $summaryLine -ForegroundColor $summaryColor

    if ($failCount -gt 0 -and $FailureLogsDir) {
        Write-Host "  Failure logs: $FailureLogsDir" -ForegroundColor Yellow
    }

    Write-Host "========================================" -ForegroundColor Magenta
}

function Write-ZipSummary {
    param([Parameter(Mandatory)][array]$ZipResults)

    Write-Host ""
    Write-Host "  ZIP Summary:" -ForegroundColor Cyan
    foreach ($z in $ZipResults) {
        $color = if ($z.Status -eq "OK") { "Green" } else { "Red" }
        $sizeLabel = if ($z.SizeKB -ge 1024) { "{0:N1} MB" -f ($z.SizeKB / 1024) } else { "{0:N1} KB" -f $z.SizeKB }
        $duration = if ($z.Duration -and $z.Duration -gt 0) { " [{0:N1}s]" -f $z.Duration } else { "" }
        Write-Host "    $($z.Slug)-v$($z.Version): $($z.Status) ($sizeLabel)$duration" -ForegroundColor $color
    }
    Write-Host ""
}
