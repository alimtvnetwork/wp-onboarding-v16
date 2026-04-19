# Module: zip-single.ps1
# Atomic ZIP operation: creates a ZIP archive for exactly one WordPress plugin.
# Dot-sourced by run.ps1 - expects plugin-helpers.ps1 loaded (Get-PluginVersion).

Add-Type -AssemblyName System.IO.Compression
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
        # Build ZIP entries manually with forward-slash separators.
        # CreateFromDirectory on some Windows/.NET versions emits backslashes
        # in entry names, which breaks PHP ZipArchive::extractTo on Linux WP
        # servers (it then creates literal "riseup-asia-uploader\file.php"
        # rather than nested folders, leading to "Unable to find plugin folder"
        # / "Plugin file does not exist" errors after extraction).
        $resolvedPluginPath = (Resolve-Path $PluginPath).Path.TrimEnd('\','/')
        $basePrefix = $resolvedPluginPath + [System.IO.Path]::DirectorySeparatorChar

        $zipStream = [System.IO.File]::Open($zipOutputPath, [System.IO.FileMode]::Create)
        $archive = [System.IO.Compression.ZipArchive]::new($zipStream, [System.IO.Compression.ZipArchiveMode]::Create)

        try {
            $allFiles = Get-ChildItem -Path $resolvedPluginPath -Recurse -File -Force
            foreach ($file in $allFiles) {
                $relative = $file.FullName.Substring($basePrefix.Length)
                $entryName = $pluginName + '/' + ($relative -replace '\\','/')
                $entry = $archive.CreateEntry($entryName, [System.IO.Compression.CompressionLevel]::Optimal)
                $entryStream = $entry.Open()
                try {
                    $fileStream = [System.IO.File]::OpenRead($file.FullName)
                    try {
                        $fileStream.CopyTo($entryStream)
                    } finally {
                        $fileStream.Dispose()
                    }
                } finally {
                    $entryStream.Dispose()
                }
            }
        } finally {
            $archive.Dispose()
            $zipStream.Dispose()
        }

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
