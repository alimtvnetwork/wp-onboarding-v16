# Module: zip-parallel.ps1
# Parallel ZIP orchestrator: ZIPs multiple plugins using background jobs.
# Dot-sourced by run.ps1 - expects zip-single.ps1, plugin-helpers.ps1 loaded.

function Invoke-ParallelPluginZip {
    param(
        [Parameter(Mandatory)][array]$PluginFolders,
        [switch]$Sequential
    )

    if ($Sequential) {
        return Invoke-SequentialPluginZip -PluginFolders $PluginFolders
    }

    Write-Host "  Zipping $($PluginFolders.Count) plugin(s) in parallel..." -ForegroundColor Yellow

    $zipSinglePath = Join-Path $ScriptDir "wp-plugins" "scripts" "modules" "zip-single.ps1"
    $pluginHelpersPath = Join-Path $ScriptDir "wp-plugins" "scripts" "modules" "plugin-helpers.ps1"

    $zipJobs = @()
    $jobIndex = 0

    foreach ($folder in $PluginFolders) {
        $folderPath = $folder.FullName
        $folderName = $folder.Name
        $currentIndex = $jobIndex
        Write-Host "    [ZIP] Starting: $folderName" -ForegroundColor DarkGray

        $zipJobs += Start-Job -Name "zip-$currentIndex-$folderName" -ScriptBlock {
            param($ZipSinglePath, $PluginHelpersPath, $PluginPath, $Index)

            Add-Type -AssemblyName System.IO.Compression.FileSystem
            . $PluginHelpersPath
            . $ZipSinglePath

            $result = Invoke-SinglePluginZip -PluginPath $PluginPath -Quiet
            $result.Index = $Index
            return $result
        } -ArgumentList $zipSinglePath, $pluginHelpersPath, $folderPath, $currentIndex

        $jobIndex++
    }

    $zipResults = @()
    foreach ($job in $zipJobs) {
        $result = Receive-Job -Job $job -Wait | Select-Object -First 1

        if ($null -eq $result) {
            $idx = [int](($job.Name -split '-')[1])
            $folderName = ($job.Name -replace "^zip-\d+-", "")
            $result = @{
                Index    = $idx
                Slug     = $folderName
                Version  = "unknown"
                Path     = ""
                SizeKB   = 0
                Status   = "FAILED (job crashed)"
                Error    = "Background job returned no result"
                Duration = 0
            }
        }

        $zipResults += $result

        $color = if ($result.Status -eq "OK") { "Green" } else { "Red" }
        $sizeLabel = if ($result.SizeKB -ge 1024) { "{0:N1} MB" -f ($result.SizeKB / 1024) } else { "{0:N1} KB" -f $result.SizeKB }
        $duration = "{0:N1}s" -f $result.Duration
        if ($result.Status -eq "OK") {
            Write-Host "    [ZIP] Done: $($result.Slug)-v$($result.Version) ($sizeLabel) [$duration]" -ForegroundColor $color
        } else {
            Write-Host "    [ZIP] FAILED: $($result.Slug) - $($result.Error)" -ForegroundColor $color
        }

        Remove-Job -Job $job -Force
    }

    $orderedResults = @($zipResults | Sort-Object { $_.Index })
    return $orderedResults
}

function Invoke-SequentialPluginZip {
    param(
        [Parameter(Mandatory)][array]$PluginFolders
    )

    Write-Host "  Zipping $($PluginFolders.Count) plugin(s) sequentially..." -ForegroundColor Yellow

    $zipResults = @()
    $jobIndex = 0

    foreach ($folder in $PluginFolders) {
        Write-Host "    [ZIP] $($folder.Name)..." -ForegroundColor DarkGray
        $result = Invoke-SinglePluginZip -PluginPath $folder.FullName -Quiet
        $result.Index = $jobIndex

        $color = if ($result.Status -eq "OK") { "Green" } else { "Red" }
        $sizeLabel = if ($result.SizeKB -ge 1024) { "{0:N1} MB" -f ($result.SizeKB / 1024) } else { "{0:N1} KB" -f $result.SizeKB }
        $duration = "{0:N1}s" -f $result.Duration
        if ($result.Status -eq "OK") {
            Write-Host "    [ZIP] Done: $($result.Slug)-v$($result.Version) ($sizeLabel) [$duration]" -ForegroundColor $color
        } else {
            Write-Host "    [ZIP] FAILED: $($result.Slug) - $($result.Error)" -ForegroundColor $color
        }

        $zipResults += $result
        $jobIndex++
    }

    return $zipResults
}
