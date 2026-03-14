# 26-04 — Summary Output

## summary-printer.ps1

### Purpose

Formats and displays the final upload summary table. Separated into its own module so it can be reused by `-ua`, `-uas`, and future modes.

### Function Signature

```powershell
function Write-UploadSummary {
    param(
        [Parameter(Mandatory)][array]$Results,      # Array of UploadResult hashtables
        [Parameter(Mandatory)][int]$TotalSites,     # Number of target sites
        [Parameter(Mandatory)][int]$TotalPlugins,   # Number of plugins attempted
        [string]$FailureLogsDir                      # Path to failure logs (shown if failures exist)
    )
}
```

### Output Format — Grouped by Site

The summary groups results by site name, showing each plugin's status under the site header:

```
========================================
  Multi-Site Upload Summary
========================================

  Atto Property Demo (https://demoat.attoproperty.com.au)
    ✓ riseup-asia-uploader  v2.12.0   OK      3.2s
    ✓ qupload               v2.12.0   OK      2.1s

  Test V1 (https://testv1.developers-organism.com)
    ✓ riseup-asia-uploader  v2.12.0   OK      4.5s
    ✗ qupload               v2.12.0   FAILED  1.8s

  Test V2 (https://testv2.developers-organism.com)
    ✓ riseup-asia-uploader  v2.12.0   OK      3.8s
    ✓ qupload               v2.12.0   OK      2.4s

  ────────────────────────────────────────
  Sites: 3 | Plugins: 2 | Total: 6
  Success: 5 | Failed: 1
  Failure logs: logs/uas-upload/
========================================
```

### Color Coding

| Status | Color | Symbol |
|--------|-------|--------|
| OK | Green | ✓ |
| FAILED | Red | ✗ |
| SKIPPED | Yellow | ⊘ |

### Implementation Logic

```powershell
function Write-UploadSummary {
    param($Results, [int]$TotalSites, [int]$TotalPlugins, [string]$FailureLogsDir)

    Write-Host ""
    Write-Host "========================================" -ForegroundColor Magenta
    Write-Host "  Multi-Site Upload Summary" -ForegroundColor Magenta
    Write-Host "========================================" -ForegroundColor Magenta

    # Group by site (preserving index order within each group)
    $grouped = $Results | Sort-Object { $_.Index } | Group-Object -Property Site

    foreach ($group in $grouped) {
        $siteName = $group.Name
        $siteUrl = $group.Group[0].SiteUrl
        Write-Host ""
        Write-Host "  $siteName ($siteUrl)" -ForegroundColor Cyan

        foreach ($r in $group.Group) {
            $symbol = if ($r.Status -eq "OK") { [char]0x2713 } elseif ($r.Status -match "SKIP") { [char]0x2298 } else { [char]0x2717 }
            $color = if ($r.Status -eq "OK") { "Green" } elseif ($r.Status -match "SKIP") { "Yellow" } else { "Red" }
            $duration = if ($r.Duration) { "{0:N1}s" -f $r.Duration } else { "-" }
            $vLabel = if ($r.Version -and $r.Version -ne "unknown") { "v$($r.Version)" } else { "" }

            Write-Host ("    $symbol {0,-24} {1,-10} {2,-8} {3}" -f $r.Plugin, $vLabel, $r.Status, $duration) -ForegroundColor $color
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
```

### Alternative: Flat Table Format

If the grouped format proves too verbose, a flat table is available as a fallback:

```
  Site                   Plugin                   Version    Status   Time
  ──────────────────────────────────────────────────────────────────────────
  Atto Property Demo     riseup-asia-uploader     v2.12.0    OK       3.2s
  Atto Property Demo     qupload                  v2.12.0    OK       2.1s
  Test V1                riseup-asia-uploader     v2.12.0    OK       4.5s
  Test V1                qupload                  v2.12.0    FAILED   1.8s
```

The grouped format is the default. The flat format can be triggered by a future `-flat` flag if needed.

### ZIP Phase Summary (Optional)

A lighter summary can also be used after the ZIP phase:

```powershell
function Write-ZipSummary {
    param([array]$ZipResults)

    Write-Host ""
    Write-Host "  ZIP Summary:" -ForegroundColor Cyan
    foreach ($z in $ZipResults) {
        $color = if ($z.Status -eq "OK") { "Green" } else { "Red" }
        $sizeLabel = if ($z.SizeKB -ge 1024) { "{0:N1} MB" -f ($z.SizeKB / 1024) } else { "{0:N1} KB" -f $z.SizeKB }
        Write-Host "    $($z.Slug)-v$($z.Version): $($z.Status) ($sizeLabel)" -ForegroundColor $color
    }
    Write-Host ""
}
```
