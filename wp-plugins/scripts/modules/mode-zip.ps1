# Module: mode-zip.ps1
# ZIP modes: -z, -za, -zq
# Dot-sourced by run.ps1 — expects all helpers and plugin-helpers loaded.

function Invoke-ZipMode {
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  ZIP Mode (-z)" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""

    Clear-PluginZips

    if ($pluginpath -ne "") {
        $zipPluginPath = $pluginpath
        if (-not [System.IO.Path]::IsPathRooted($zipPluginPath)) {
            $zipPluginPath = Join-Path $ScriptDir $zipPluginPath
        }
        if (-not (Test-Path $zipPluginPath)) {
            Write-Host "ERROR: Plugin folder not found: $zipPluginPath" -ForegroundColor Red
            exit 1
        }
        Write-Host "  Using custom plugin path: $zipPluginPath" -ForegroundColor Cyan
        New-PluginZip $zipPluginPath
    } else {
        $defaultPath = Get-DefaultUploaderPath
        New-PluginZip $defaultPath
    }

    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  ZIP complete!" -ForegroundColor Green
    Write-Host "========================================" -ForegroundColor Cyan

    exit 0
}

function Invoke-ZipAllMode {
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  ZIP All Mode (-za)" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""

    Clear-PluginZips

    $wpPluginsDir = Join-Path $ScriptDir "wp-plugins"
    if (-not (Test-Path $wpPluginsDir)) {
        Write-Host "ERROR: wp-plugins/ directory not found" -ForegroundColor Red
        exit 1
    }

    $skipList = @()
    if ($Config.wpPlugins -and $Config.wpPlugins.skipPlugins) {
        $skipList = @($Config.wpPlugins.skipPlugins)
    }

    $pluginFolders = Get-ChildItem $wpPluginsDir -Directory | Where-Object {
        if ($_.Name -in $skipList) { return $false }
        $phpFiles = Get-ChildItem $_.FullName -Filter "*.php" -File -ErrorAction SilentlyContinue
        $hasPluginHeader = $false
        foreach ($f in $phpFiles) {
            $head = Get-Content $f.FullName -Head 5 -ErrorAction SilentlyContinue
            if ($head -match "Plugin Name:") { $hasPluginHeader = $true; break }
        }
        $hasPluginHeader
    }

    if ($pluginFolders.Count -eq 0) {
        Write-Host "No WordPress plugins found in wp-plugins/" -ForegroundColor Yellow
        exit 0
    }

    Write-Host "  Found $($pluginFolders.Count) plugin(s) to ZIP:" -ForegroundColor Cyan
    foreach ($folder in $pluginFolders) {
        Write-Host "    - $($folder.Name)" -ForegroundColor Gray
    }
    Write-Host ""

    foreach ($folder in $pluginFolders) {
        New-PluginZip $folder.FullName
    }

    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  ZIP All complete!" -ForegroundColor Green
    Write-Host "========================================" -ForegroundColor Cyan

    exit 0
}

function Invoke-ZipQUploadMode {
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  ZIP QUpload Mode (-zq)" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""

    Clear-PluginZips

    $qPath = Get-DefaultQUploaderPath
    New-PluginZip $qPath

    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  ZIP complete!" -ForegroundColor Green
    Write-Host "========================================" -ForegroundColor Cyan

    exit 0
}
