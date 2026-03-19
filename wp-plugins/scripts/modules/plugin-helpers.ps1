# Module: plugin-helpers.ps1
# WordPress plugin discovery, ZIP, credential, and site helper functions.
# Dot-sourced by run.ps1 — expects $ScriptDir, $Config, Resolve-RelativePath, Decode-Base64.

Add-Type -AssemblyName System.IO.Compression.FileSystem

function Get-PluginVersion($PluginDir) {
    $phpFiles = Get-ChildItem $PluginDir -Filter "*.php" -File | Where-Object {
        (Get-Content $_.FullName -Head 5 -ErrorAction SilentlyContinue) -match "Plugin Name:"
    } | Select-Object -First 1

    if ($phpFiles) {
        $content = Get-Content $phpFiles.FullName -Raw -ErrorAction SilentlyContinue
        $match = [regex]::Match($content, "\*?\s*Version:\s*(\d+\.\d+\.\d+)")
        if ($match.Success) { return $match.Groups[1].Value }
    }

    return "unknown"
}

function New-PluginZip($PluginDir) {
    $pluginName = Split-Path $PluginDir -Leaf
    $version = Get-PluginVersion $PluginDir
    $zipFileName = "$pluginName-v$version.zip"
    $zipOutputPath = Join-Path (Split-Path $PluginDir -Parent) $zipFileName

    Write-Host "  Plugin:  $pluginName" -ForegroundColor Yellow
    Write-Host "  Version: v$version" -ForegroundColor Yellow
    Write-Host "  Source:  $PluginDir" -ForegroundColor Gray
    Write-Host "  Output:  $zipOutputPath" -ForegroundColor Gray

    if (Test-Path $zipOutputPath) {
        Remove-Item $zipOutputPath -Force
        Write-Host "  Replaced existing ZIP" -ForegroundColor DarkGray
    }

    try {
        $tempDir = Join-Path $env:TEMP "wp-zip-$(Get-Random)"
        $pluginTempDir = Join-Path $tempDir $pluginName
        New-Item -ItemType Directory -Path $pluginTempDir -Force | Out-Null
        Copy-Item -Path "$PluginDir\*" -Destination $pluginTempDir -Recurse

        [System.IO.Compression.ZipFile]::CreateFromDirectory(
            $pluginTempDir,
            $zipOutputPath,
            [System.IO.Compression.CompressionLevel]::SmallestSize,
            $true
        )

        Remove-Item $tempDir -Recurse -Force

        if (Test-Path $zipOutputPath) {
            $zipSize = (Get-Item $zipOutputPath).Length
            $zipSizeKB = [math]::Round($zipSize / 1024, 1)
            $zipSizeMB = [math]::Round($zipSize / 1048576, 2)
            $sizeLabel = if ($zipSizeMB -ge 1) { "$zipSizeMB MB" } else { "$zipSizeKB KB" }
            Write-Host "  Created: $zipFileName ($sizeLabel)" -ForegroundColor Green
        } else {
            Write-Host "  ERROR: ZIP file was not created for $pluginName" -ForegroundColor Red
        }
    } catch {
        Write-Host "  ERROR: Failed to create ZIP for $pluginName`: $_" -ForegroundColor Red
    }

    Write-Host ""
}

function Get-DefaultUploaderPath {
    $defaultUploader = $null
    if ($Config.wpPlugins -and $Config.wpPlugins.defaultUploader) {
        $defaultUploader = $Config.wpPlugins.defaultUploader
    }
    if (-not $defaultUploader -or -not $Config.wpPlugins.plugins.$defaultUploader) {
        Write-Host "ERROR: No default uploader configured in powershell.json (wpPlugins.defaultUploader)" -ForegroundColor Red
        exit 1
    }
    $pluginCfg = $Config.wpPlugins.plugins.$defaultUploader
    $resolved = Resolve-RelativePath $pluginCfg.path
    if (-not (Test-Path $resolved)) {
        Write-Host "ERROR: Plugin folder not found: $resolved" -ForegroundColor Red
        exit 1
    }
    Write-Host "  Plugin: $defaultUploader" -ForegroundColor Yellow
    return $resolved
}

function Get-DefaultQUploaderPath {
    $defaultQUploader = $null
    if ($Config.wpPlugins -and $Config.wpPlugins.defaultQUploader) {
        $defaultQUploader = $Config.wpPlugins.defaultQUploader
    }
    if (-not $defaultQUploader -and $Config.wpPlugins -and $Config.wpPlugins.defaultUploader) {
        $defaultQUploader = $Config.wpPlugins.defaultUploader
    }
    if (-not $defaultQUploader -or -not $Config.wpPlugins.plugins.$defaultQUploader) {
        Write-Host "ERROR: No default QUploader configured in powershell.json (wpPlugins.defaultQUploader)" -ForegroundColor Red
        exit 1
    }
    $pluginCfg = $Config.wpPlugins.plugins.$defaultQUploader
    $resolved = Resolve-RelativePath $pluginCfg.path
    if (-not (Test-Path $resolved)) {
        Write-Host "ERROR: Plugin folder not found: $resolved" -ForegroundColor Red
        exit 1
    }
    Write-Host "  Plugin: $defaultQUploader" -ForegroundColor Yellow
    return $resolved
}

function Get-DefaultSiteCredential {
    param($SiteConfig)

    $defaultCred = $null
    foreach ($cred in $SiteConfig.credentials) {
        if ($cred.isDefault -eq $true) {
            $defaultCred = $cred
            break
        }
    }

    if (-not $defaultCred -and $SiteConfig.credentials.Count -gt 0) {
        $defaultCred = $SiteConfig.credentials[0]
        Write-Host "    No default credential found, using first: $($defaultCred.appName)" -ForegroundColor DarkYellow
    }

    if (-not $defaultCred) {
        Write-Host "    ERROR: No credentials configured for site $($SiteConfig.name)" -ForegroundColor Red
        return $null
    }

    $username = Decode-Base64 $defaultCred.usernameBase64
    $password = Decode-Base64 $defaultCred.passwordBase64

    Write-Host "    Credential: $($defaultCred.appName)" -ForegroundColor Gray
    Write-Host "    Username:   $username" -ForegroundColor Gray

    return @{
        Username = $username
        Password = $password
        AppName  = $defaultCred.appName
    }
}

function Show-ConfiguredSites {
    if (-not $Config.wpPlugins -or -not $Config.wpPlugins.sites -or $Config.wpPlugins.sites.Count -eq 0) {
        Write-Host "  No sites configured in powershell.json (wpPlugins.sites)" -ForegroundColor Yellow
        return
    }

    Write-Host "  Configured sites:" -ForegroundColor Cyan
    $siteIndex = 0
    foreach ($s in $Config.wpPlugins.sites) {
        $siteIndex++
        $enabledLabel = if ($s.enabled -eq $false) { " [DISABLED]" } else { "" }
        $credCount = if ($s.credentials) { $s.credentials.Count } else { 0 }
        Write-Host "    $siteIndex. $($s.name)$enabledLabel - $($s.url) ($credCount credential(s))" -ForegroundColor $(if ($s.enabled -eq $false) { "DarkGray" } else { "White" })
    }
    Write-Host ""
}

function Clear-PluginZips {
    $wpPluginsDir = Join-Path $ScriptDir "wp-plugins"
    if (-not (Test-Path $wpPluginsDir)) { return }

    $zips = Get-ChildItem $wpPluginsDir -Filter "*.zip" -File -ErrorAction SilentlyContinue
    if ($zips.Count -eq 0) {
        Write-Host "  No existing ZIP files found" -ForegroundColor DarkGray
        return
    }

    Write-Host "  Clearing $($zips.Count) existing ZIP file(s):" -ForegroundColor Yellow
    foreach ($z in $zips) {
        Remove-Item $z.FullName -Force
        Write-Host "    Removed: $($z.Name)" -ForegroundColor DarkGray
    }
    Write-Host ""
}

function Get-UploadablePlugins {
    param(
        [string[]]$ExtraSkipList = @()
    )

    $wpPluginsDir = Join-Path $ScriptDir "wp-plugins"

    # skipPlugins from config is the SOLE exclusion mechanism (no hardcoded QUpload exclusion)
    $skipList = @()
    if ($Config.wpPlugins -and $Config.wpPlugins.skipPlugins) {
        $skipList = @($Config.wpPlugins.skipPlugins)
    }
    $skipList += $ExtraSkipList

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

    return @{
        Plugins  = $pluginFolders
        SkipList = $skipList
    }
}
