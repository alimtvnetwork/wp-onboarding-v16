# Module: zip-single.ps1
# Atomic ZIP operation: creates a ZIP archive for exactly one WordPress plugin.
# Dot-sourced by run.ps1 - expects plugin-helpers.ps1 loaded (Get-PluginVersion).

Add-Type -AssemblyName System.IO.Compression.FileSystem

function Invoke-SinglePluginZip {
    param(
        [Parameter(Mandatory)][string]$PluginPath,
        [switch]$Quiet
    )

    $pluginName = Split-Path $PluginPath -Leaf
    $result = @{
        Slug     = $pluginName
        Version  = "unknown"
        Path     = ""
        SizeKB   = 0
        Status   = "FAILED"
        Error    = $null
        Duration = 0
    }

    $sw = [System.Diagnostics.Stopwatch]::StartNew()

    if (-not (Test-Path $PluginPath)) {
        $result.Error = "Plugin folder not found: $PluginPath"
        $sw.Stop()
        $result.Duration = $sw.Elapsed.TotalSeconds
        return $result
    }

    $version = Get-PluginVersion $PluginPath
    $result.Version = $version
    $zipFileName = "$pluginName-v$version.zip"
    $zipOutputPath = Join-Path (Split-Path $PluginPath -Parent) $zipFileName
    $result.Path = $zipOutputPath

    if (-not $Quiet) {
        Write-Host "  Plugin:  $pluginName" -ForegroundColor Yellow
        Write-Host "  Version: v$version" -ForegroundColor Yellow
        Write-Host "  Source:  $PluginPath" -ForegroundColor Gray
        Write-Host "  Output:  $zipOutputPath" -ForegroundColor Gray
    }

    if (Test-Path $zipOutputPath) {
        Remove-Item $zipOutputPath -Force
        if (-not $Quiet) { Write-Host "  Replaced existing ZIP" -ForegroundColor DarkGray }
    }

    try {
        $tempDir = Join-Path $env:TEMP "wp-zip-$(Get-Random)"
        $pluginTempDir = Join-Path $tempDir $pluginName
        New-Item -ItemType Directory -Path $pluginTempDir -Force | Out-Null
        Copy-Item -Path "$PluginPath\*" -Destination $pluginTempDir -Recurse

        [System.IO.Compression.ZipFile]::CreateFromDirectory(
            $pluginTempDir,
            $zipOutputPath,
            [System.IO.Compression.CompressionLevel]::SmallestSize,
            $true
        )

        Remove-Item $tempDir -Recurse -Force

        if (Test-Path $zipOutputPath) {
            $zipSize = (Get-Item $zipOutputPath).Length
            $result.SizeKB = [math]::Round($zipSize / 1024, 1)
            $result.Status = "OK"

            if (-not $Quiet) {
                $zipSizeMB = [math]::Round($zipSize / 1048576, 2)
                $sizeLabel = if ($zipSizeMB -ge 1) { "$zipSizeMB MB" } else { "$($result.SizeKB) KB" }
                Write-Host "  Created: $zipFileName ($sizeLabel)" -ForegroundColor Green
            }
        } else {
            $result.Error = "ZIP file was not created"
            if (-not $Quiet) { Write-Host "  ERROR: ZIP file was not created for $pluginName" -ForegroundColor Red }
        }
    } catch {
        $result.Error = $_.Exception.Message
        if (-not $Quiet) { Write-Host "  ERROR: Failed to create ZIP for $pluginName`: $_" -ForegroundColor Red }
    }

    $sw.Stop()
    $result.Duration = $sw.Elapsed.TotalSeconds

    if (-not $Quiet) { Write-Host "" }

    return $result
}
