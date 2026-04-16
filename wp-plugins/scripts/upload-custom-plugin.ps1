# Upload Custom Plugin — External Plugin Zipper & Uploader
# Version: 1.0.0
# Reads plugin paths from custom-plugins.json, zips with best compression,
# and uploads via upload-plugin-v2.ps1 to configured WordPress sites.
#
# Usage (standalone):
#   .\upload-custom-plugin.ps1 -Slug "alim"
#   .\upload-custom-plugin.ps1 -Slug "alim" -All
#   .\upload-custom-plugin.ps1 -Slug "alim" -Site "Test V1"
#   .\upload-custom-plugin.ps1 -List
#
# Usage (via run.ps1):
#   .\run.ps1 -ucp alim
#   .\run.ps1 -ucp alim -a
#   .\run.ps1 -ucp alim -site "Test V1"

param(
    [Parameter(Mandatory=$false)]
    [string]$Slug = "",

    [Parameter(Mandatory=$false)]
    [switch]$All = $false,

    [Parameter(Mandatory=$false)]
    [string]$Site = "",

    [Parameter(Mandatory=$false)]
    [switch]$List = $false,

    [Parameter(Mandatory=$false)]
    [switch]$Activate = $true,

    [Parameter(Mandatory=$false)]
    [switch]$VerboseOutput = $false,

    [Parameter(Mandatory=$false)]
    [switch]$SkipGitPull = $false,

    [Parameter(Mandatory=$false)]
    [switch]$Help = $false
)

$ErrorActionPreference = "Stop"

# --- Self-lint: detect parse errors before execution ---
$_lintScriptFile = $MyInvocation.MyCommand.Path
if ($_lintScriptFile -and (Test-Path $_lintScriptFile)) {
    $_lintErrors = $null
    [void][System.Management.Automation.Language.Parser]::ParseFile(
        $_lintScriptFile, [ref]$null, [ref]$_lintErrors
    )
    if ($_lintErrors -and $_lintErrors.Count -gt 0) {
        $scriptName = Split-Path $_lintScriptFile -Leaf
        Write-Host "LINT FAILED: $scriptName has parse errors" -ForegroundColor Red
        foreach ($e in $_lintErrors) {
            Write-Host "  Line $($e.Extent.StartLineNumber): $($e.Message)" -ForegroundColor Yellow
        }
        Write-Host "Fix: Ensure UTF-8 (no BOM) encoding with straight ASCII quotes." -ForegroundColor Cyan
        exit 1
    }
}

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path

# ============================================================================
# HELP
# ============================================================================
if ($Help) {
    Write-Host ""
    Write-Host "Upload Custom Plugin - External Plugin Zipper & Uploader" -ForegroundColor Cyan
    Write-Host "========================================================" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "USAGE:" -ForegroundColor Yellow
    Write-Host "  .\upload-custom-plugin.ps1 -Slug 'alim'"
    Write-Host "  .\upload-custom-plugin.ps1 -Slug 'alim' -All"
    Write-Host "  .\upload-custom-plugin.ps1 -Slug 'alim' -Site 'Test V1'"
    Write-Host "  .\upload-custom-plugin.ps1 -List"
    Write-Host ""
    Write-Host "  Via run.ps1:" -ForegroundColor DarkGray
    Write-Host "  .\run.ps1 -ucp alim                  Upload to default site"
    Write-Host "  .\run.ps1 -ucp alim -a               Upload to ALL sites"
    Write-Host "  .\run.ps1 -ucp alim -site 'Test V1'  Upload to specific site"
    Write-Host "  .\run.ps1 -ucp -list                 List registered plugins"
    Write-Host ""
    Write-Host "CONFIG:" -ForegroundColor Yellow
    Write-Host "  File: wp-plugins/scripts/custom-plugins.json"
    Write-Host "  Each plugin has Windows + Unix paths; OS is auto-detected."
    Write-Host ""
    exit 0
}

# ============================================================================
# CONFIG LOADING
# ============================================================================
$configPath = Join-Path $ScriptDir "custom-plugins.json"

if (-not (Test-Path $configPath)) {
    Write-Host "ERROR: custom-plugins.json not found at: $configPath" -ForegroundColor Red
    Write-Host "Create this file with your plugin paths and site credentials." -ForegroundColor Yellow
    Write-Host ""
    Write-Host "Example:" -ForegroundColor DarkGray
    Write-Host '{' -ForegroundColor Gray
    Write-Host '  "defaultSite": "Test V2",' -ForegroundColor Gray
    Write-Host '  "sites": [{ "name": "Test V2", "url": "...", "username": "...", "appPassword": "..." }],' -ForegroundColor Gray
    Write-Host '  "plugins": [{ "slug": "alim", "paths": { "windows": "D:\\...", "unix": "/Users/..." } }]' -ForegroundColor Gray
    Write-Host '}' -ForegroundColor Gray
    exit 1
}

$config = Get-Content $configPath -Raw | ConvertFrom-Json

# Validate config structure
$hasDefaultSite = $null -ne $config.defaultSite -and $config.defaultSite -ne ""
$hasSites = $null -ne $config.sites -and $config.sites.Count -gt 0
$hasPlugins = $null -ne $config.plugins -and $config.plugins.Count -gt 0

if (-not $hasDefaultSite) {
    Write-Host "ERROR: custom-plugins.json missing 'defaultSite' field" -ForegroundColor Red
    exit 1
}
if (-not $hasSites) {
    Write-Host "ERROR: custom-plugins.json missing or empty 'sites' array" -ForegroundColor Red
    exit 1
}
if (-not $hasPlugins) {
    Write-Host "ERROR: custom-plugins.json missing or empty 'plugins' array" -ForegroundColor Red
    exit 1
}

# ============================================================================
# LIST MODE
# ============================================================================
if ($List) {
    Write-Host ""
    Write-Host "Registered Custom Plugins:" -ForegroundColor Cyan
    Write-Host ("-" * 60) -ForegroundColor DarkGray
    
    $isWin = ($IsWindows -or $env:OS -eq "Windows_NT")
    $osLabel = if ($isWin) { "Windows" } else { "Unix" }

    foreach ($plugin in $config.plugins) {
        $pathForOs = if ($isWin) { $plugin.paths.windows } else { $plugin.paths.unix }
        $pathExists = $pathForOs -and (Test-Path $pathForOs)
        $statusIcon = if ($pathExists) { "[OK]" } else { "[!!]" }
        $statusColor = if ($pathExists) { "Green" } else { "Red" }
        
        Write-Host "  $statusIcon " -ForegroundColor $statusColor -NoNewline
        Write-Host "$($plugin.slug)" -ForegroundColor White -NoNewline
        if ($plugin.name) {
            Write-Host " ($($plugin.name))" -ForegroundColor DarkGray -NoNewline
        }
        Write-Host ""
        Write-Host "       $osLabel path: $pathForOs" -ForegroundColor Gray
    }

    Write-Host ""
    Write-Host "Default site: $($config.defaultSite)" -ForegroundColor Cyan
    Write-Host "Sites ($($config.sites.Count)):" -ForegroundColor Cyan
    foreach ($s in $config.sites) {
        $isDefault = $s.name -eq $config.defaultSite
        $marker = if ($isDefault) { " (default)" } else { "" }
        Write-Host "  - $($s.name)$marker  $($s.url)" -ForegroundColor Gray
    }
    Write-Host ""
    exit 0
}

# ============================================================================
# SLUG VALIDATION + DIRECT FOLDER PATH FALLBACK
# ============================================================================
if ([string]::IsNullOrWhiteSpace($Slug)) {
    Write-Host "ERROR: Plugin slug is required. Use -Slug 'name' or .\run.ps1 -ucp name" -ForegroundColor Red
    Write-Host "Use -List to see registered plugins." -ForegroundColor Yellow
    exit 2
}

$isWin = ($IsWindows -or $env:OS -eq "Windows_NT")
$isDirectPath = $false
$pluginFolderPath = ""
$pingEndpoint = ""

# Try to find plugin in config by slug
$plugin = $config.plugins | Where-Object { $_.slug -eq $Slug }

if ($plugin) {
    # --- Found in config: resolve OS-aware path ---
    $pluginFolderPath = if ($isWin) { $plugin.paths.windows } else { $plugin.paths.unix }
    $pingEndpoint = if ($plugin.pingEndpoint) { $plugin.pingEndpoint } else { "" }

    if ([string]::IsNullOrWhiteSpace($pluginFolderPath)) {
        $osLabel = if ($isWin) { "windows" } else { "unix" }
        Write-Host "ERROR: No '$osLabel' path configured for plugin '$Slug'" -ForegroundColor Red
        exit 3
    }

    if (-not (Test-Path $pluginFolderPath)) {
        Write-Host "ERROR: Plugin folder not found: $pluginFolderPath" -ForegroundColor Red
        exit 3
    }
} else {
    # --- Not in config: treat $Slug as a direct folder path ---
    if (Test-Path $Slug) {
        $isDirectPath = $true
        $pluginFolderPath = (Resolve-Path $Slug).Path
        $Slug = Split-Path $pluginFolderPath -Leaf
        Write-Host "  [DirectPath] Folder detected, using slug: $Slug" -ForegroundColor DarkCyan
    } else {
        Write-Host "ERROR: '$Slug' is not a registered plugin slug and not a valid folder path" -ForegroundColor Red
        Write-Host ""
        Write-Host "Registered plugins:" -ForegroundColor Yellow
        foreach ($p in $config.plugins) {
            Write-Host "  - $($p.slug)" -ForegroundColor Gray
        }
        Write-Host ""
        Write-Host "Or provide a direct folder path:" -ForegroundColor Yellow
        Write-Host "  .\run.ps1 -ucp 'D:\path\to\my-plugin'" -ForegroundColor Gray
        exit 2
    }
}

# ============================================================================
# RESOLVE TARGET SITES
# ============================================================================
$targetSites = @()

if ($All) {
    $targetSites = $config.sites
} elseif ($Site -ne "") {
    $matched = $config.sites | Where-Object { $_.name -eq $Site }
    if (-not $matched) {
        Write-Host "ERROR: Site '$Site' not found in custom-plugins.json" -ForegroundColor Red
        Write-Host "Available sites:" -ForegroundColor Yellow
        foreach ($s in $config.sites) {
            Write-Host "  - $($s.name)" -ForegroundColor Gray
        }
        exit 1
    }
    $targetSites = @($matched)
} else {
    $defaultMatch = $config.sites | Where-Object { $_.name -eq $config.defaultSite }
    if (-not $defaultMatch) {
        Write-Host "ERROR: Default site '$($config.defaultSite)' not found in sites array" -ForegroundColor Red
        exit 1
    }
    $targetSites = @($defaultMatch)
}

# ============================================================================
# ORCHESTRATION (versioned ZIP path + git pull + syntax check)
# ============================================================================
function Get-LocalPluginVersion {
    param([string]$PluginPath)

    $enumFile = Join-Path $PluginPath "includes/Enums/PluginConfigType.php"
    if (Test-Path $enumFile) {
        $enumContent = Get-Content $enumFile -Raw
        if ($enumContent -match "case\s+Version\s*=\s*'([0-9]+\.[0-9]+\.[0-9]+)'") {
            return $Matches[1]
        }
    }

    $mainFile = Get-ChildItem $PluginPath -Filter "*.php" | Where-Object {
        (Get-Content $_.FullName -Head 8) -match 'Plugin Name:'
    } | Select-Object -First 1

    if ($mainFile) {
        $content = Get-Content $mainFile.FullName -Raw
        if ($content -match "Version:\s*([0-9]+\.[0-9]+\.[0-9]+)") {
            return $Matches[1]
        }
    }

    return "unknown"
}

$pluginDisplayName = if ($plugin -and $plugin.name) { $plugin.name } else { $Slug }
$siteCountLabel = if ($targetSites.Count -eq 1) { $targetSites[0].name } else { "$($targetSites.Count) sites" }
$sourceLabel = if ($isDirectPath) { " [DirectPath]" } else { "" }
$localVersion = Get-LocalPluginVersion -PluginPath $pluginFolderPath
$zipFolderPath = Split-Path $pluginFolderPath -Parent
$zipFileName = if ($localVersion -ne "unknown") { "$Slug-$localVersion.zip" } else { "$Slug.zip" }
$zipPath = Join-Path $zipFolderPath $zipFileName

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  Custom Plugin Upload$sourceLabel" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  Plugin:   $pluginDisplayName ($Slug)" -ForegroundColor White
Write-Host "  Path:     $pluginFolderPath" -ForegroundColor Gray
Write-Host "  Target:   $siteCountLabel" -ForegroundColor Gray
Write-Host "  Version:  $localVersion" -ForegroundColor Gray
Write-Host "  ZIP:      $zipPath" -ForegroundColor Gray
if ($pingEndpoint -ne "") {
    Write-Host "  Ping:     $pingEndpoint" -ForegroundColor Gray
}
Write-Host ""

$totalWatch = [System.Diagnostics.Stopwatch]::StartNew()

# ============================================================================
# GIT PULL (pull first, rebase only if required)
# ============================================================================
if ($SkipGitPull) {
    Write-Host "  Git pull skipped (-skipgitpull)" -ForegroundColor DarkGray
} else {
    $gitCommand = Get-Command git -ErrorAction SilentlyContinue
    if ($null -ne $gitCommand) {
        $gitCheckOutput = & git -C $pluginFolderPath rev-parse --is-inside-work-tree 2>&1
        if ($LASTEXITCODE -eq 0 -and $gitCheckOutput -eq "true") {
            $gitWatch = [System.Diagnostics.Stopwatch]::StartNew()
            $branchName = (& git -C $pluginFolderPath rev-parse --abbrev-ref HEAD 2>&1 | Out-String).Trim()
            Write-Host "  Git repo detected (branch: $branchName)" -ForegroundColor DarkCyan
            Write-Host "  Running git pull..." -ForegroundColor Yellow

            $gitOutput = (& git -C $pluginFolderPath pull 2>&1 | Out-String).Trim()
            $gitExitCode = $LASTEXITCODE

            if ($gitExitCode -ne 0 -and ($gitOutput -match 'rebase' -or $gitOutput -match 'divergent' -or $gitOutput -match 'reconcile divergent branches')) {
                Write-Host "  Git pull requires rebase; retrying with --rebase..." -ForegroundColor Yellow
                $gitOutput = (& git -C $pluginFolderPath pull --rebase 2>&1 | Out-String).Trim()
                $gitExitCode = $LASTEXITCODE
            }

            $gitWatch.Stop()
            $gitElapsed = [math]::Round($gitWatch.Elapsed.TotalSeconds, 1)

            if ($gitExitCode -eq 0) {
                $shortHash = (& git -C $pluginFolderPath rev-parse --short HEAD 2>&1 | Out-String).Trim()
                Write-Host "  Git pull OK ($branchName @ $shortHash) - ${gitElapsed}s" -ForegroundColor Green
            } else {
                Write-Host "  Git pull WARNING: $gitOutput" -ForegroundColor DarkYellow
                Write-Host "  Continuing with upload... - ${gitElapsed}s" -ForegroundColor DarkYellow
            }
        }
    }
}

# ============================================================================
# PHP SYNTAX CHECK (skip vendor folder)
# ============================================================================
$phpCommand = Get-Command php -ErrorAction SilentlyContinue

if ($null -ne $phpCommand) {
    $phpWatch = [System.Diagnostics.Stopwatch]::StartNew()
    Write-Host "  PHP syntax check..." -ForegroundColor Yellow

    $skipFolders = @("vendor")
    $pluginSettingsPath = Join-Path $pluginFolderPath "settings.json"
    if (Test-Path $pluginSettingsPath) {
        try {
            $pluginSettings = Get-Content $pluginSettingsPath -Raw | ConvertFrom-Json
            if ($pluginSettings.phpCheck -and $pluginSettings.phpCheck.skipFolders) {
                $skipFolders += @($pluginSettings.phpCheck.skipFolders)
            }
        } catch { }
    }

    $phpFiles = @(Get-ChildItem -Path $pluginFolderPath -Recurse -File -Filter "*.php" | Sort-Object FullName)
    $filteredFiles = @()
    $skippedCount = 0
    foreach ($file in $phpFiles) {
        $relativePath = $file.FullName.Substring($pluginFolderPath.Length).TrimStart('\\', '/')
        $isSkipped = $false
        foreach ($skip in $skipFolders) {
            if ($relativePath -like "$skip\\*" -or $relativePath -like "$skip/*") {
                $isSkipped = $true
                $skippedCount++
                break
            }
        }
        if (-not $isSkipped) { $filteredFiles += $file }
    }

    $syntaxErrors = 0
    foreach ($file in $filteredFiles) {
        $lintOutput = & php -l $file.FullName 2>&1
        if ($LASTEXITCODE -ne 0) {
            $syntaxErrors++
            Write-Host "    SYNTAX ERROR: $($file.FullName)" -ForegroundColor Red
            Write-Host "    $($lintOutput | Out-String)" -ForegroundColor DarkRed
        }
    }

    if ($syntaxErrors -gt 0) {
        Write-Host "  PHP syntax check FAILED: $syntaxErrors error(s) found" -ForegroundColor Red
        exit 4
    }

    $phpWatch.Stop()
    $phpElapsed = [math]::Round($phpWatch.Elapsed.TotalSeconds, 1)
    $skipLabel = if ($skippedCount -gt 0) { " ($skippedCount skipped in vendor)" } else { "" }
    Write-Host "  PHP syntax OK: $($filteredFiles.Count) files checked$skipLabel - ${phpElapsed}s" -ForegroundColor Green
} else {
    Write-Host "  PHP CLI not found — skipping syntax check" -ForegroundColor DarkYellow
}

# ==========================================================================
# UPLOAD SCRIPT SETUP
# ==========================================================================
$uploadScript = Join-Path $ScriptDir "upload-plugin-v2.ps1"

if (-not (Test-Path $uploadScript)) {
    Write-Host "ERROR: upload-plugin-v2.ps1 not found at: $uploadScript" -ForegroundColor Red
    exit 5
}

$shouldActivate = $true
if ($plugin -and $null -ne $plugin.activate) {
    $shouldActivate = $plugin.activate
}

# ============================================================================
# PING FUNCTION — verifies plugin is active on the site after upload
# ============================================================================
function Invoke-PluginPing {
    param(
        [string]$SiteUrl,
        [string]$Endpoint,
        [string]$Username,
        [string]$Password,
        [string]$SiteName,
        [string]$Label = ""
    )

    if ([string]::IsNullOrWhiteSpace($Endpoint)) { return }

    $pingUrl = $SiteUrl.TrimEnd('/') + $Endpoint
    Write-Host "  ${Label}Pinging $pingUrl ..." -ForegroundColor DarkCyan

    try {
        $cleanPwd = $Password -replace '\s', ''
        $base64Auth = [Convert]::ToBase64String([Text.Encoding]::ASCII.GetBytes("${Username}:${cleanPwd}"))
        $headers = @{
            "Authorization" = "Basic $base64Auth"
            "Accept" = "application/json"
        }

        $response = Invoke-RestMethod -Uri $pingUrl -Method Get -Headers $headers -TimeoutSec 15 -ErrorAction Stop
        Write-Host "  ${Label}PING OK - $SiteName responded" -ForegroundColor Green

        # Show standard ping fields
        $pingData = $response
        if ($response.Results -and $response.Results.Count -gt 0) {
            $pingData = $response.Results[0]
        }
        if ($pingData.Author) {
            Write-Host "  ${Label}  Author:  $($pingData.Author)" -ForegroundColor DarkGray
        }
        if ($pingData.Company) {
            Write-Host "  ${Label}  Company: $($pingData.Company)" -ForegroundColor DarkGray
        }
        if ($pingData.Version) {
            Write-Host "  ${Label}  Version: $($pingData.Version)" -ForegroundColor DarkGray
        }
    } catch {
        $statusCode = ""
        if ($_.Exception.Response) {
            $statusCode = " (HTTP $([int]$_.Exception.Response.StatusCode))"
        }
        Write-Host "  ${Label}PING FAILED$statusCode - $($_.Exception.Message)" -ForegroundColor DarkYellow
    }
}

$successCount = 0
$failCount = 0
$totalSites = $targetSites.Count

Write-Host ""

for ($idx = 0; $idx -lt $totalSites; $idx++) {
    $targetSite = $targetSites[$idx]
    $siteLabel = if ($totalSites -gt 1) { "[$($idx+1)/$totalSites] " } else { "" }

    $siteWatch = [System.Diagnostics.Stopwatch]::StartNew()
    Write-Host "  ${siteLabel}Uploading to $($targetSite.name) ($($targetSite.url))..." -ForegroundColor Yellow

    $cleanPassword = $targetSite.appPassword -replace '\s', ''
    $uploadConfig = @{
        pluginFolderPath     = $pluginFolderPath
        wordPressSiteURL     = $targetSite.url
        username             = $targetSite.username
        appPassword          = $cleanPassword
        outputZipPath        = $zipPath
        activateAfterInstall = $shouldActivate
        deleteZipAfterUpload = $true
        pluginSlug           = $Slug
    } | ConvertTo-Json -Compress

    try {
        if ($VerboseOutput) {
            & $uploadScript -JsonConfig $uploadConfig -SkipGitPull -DebugMode
        } else {
            & $uploadScript -JsonConfig $uploadConfig -SkipGitPull
        }
        $uploadExitCode = $LASTEXITCODE

        if ($uploadExitCode -eq 0) {
            $siteWatch.Stop()
            $siteElapsed = [math]::Round($siteWatch.Elapsed.TotalSeconds, 1)
            Write-Host "  ${siteLabel}SUCCESS - $Slug uploaded to $($targetSite.name) - ${siteElapsed}s" -ForegroundColor Green
            $successCount++

            if ($pingEndpoint -ne "") {
                Invoke-PluginPing `
                    -SiteUrl $targetSite.url `
                    -Endpoint $pingEndpoint `
                    -Username $targetSite.username `
                    -Password $cleanPassword `
                    -SiteName $targetSite.name `
                    -Label $siteLabel
            }
        } else {
            Write-Host "  ${siteLabel}FAILED - Exit code: $uploadExitCode" -ForegroundColor Red
            $failCount++
        }
    } catch {
        $siteWatch.Stop()
        Write-Host "  ${siteLabel}FAILED - $($_.Exception.Message)" -ForegroundColor Red
        if ($_.ScriptStackTrace) {
            Write-Host "  ${siteLabel}STACKTRACE: $($_.ScriptStackTrace)" -ForegroundColor DarkRed
        }
        $failCount++
    }

    Write-Host ""
}

# ============================================================================
# CLEANUP
# ============================================================================
if ((Test-Path $zipPath) -and $failCount -eq 0) {
    Remove-Item $zipPath -Force -ErrorAction SilentlyContinue
}

if ($failCount -gt 0 -and (Test-Path $zipPath)) {
    Write-Host "  ZIP preserved for debugging: $zipPath" -ForegroundColor DarkYellow
}

# ============================================================================
# SUMMARY
# ============================================================================
$totalWatch.Stop()
$totalElapsed = [math]::Round($totalWatch.Elapsed.TotalSeconds, 1)

Write-Host "========================================" -ForegroundColor Cyan
if ($failCount -eq 0) {
    Write-Host "  ${Slug}: $successCount/$totalSites sites completed successfully" -ForegroundColor Green
} else {
    Write-Host "  ${Slug}: $successCount succeeded, $failCount failed (out of $totalSites)" -ForegroundColor Red
}
Write-Host "  Total elapsed: ${totalElapsed}s" -ForegroundColor DarkGray
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

if ($failCount -gt 0) {
    exit 5
}
exit 0
