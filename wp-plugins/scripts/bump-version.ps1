# WordPress Project Version Bump Automation
# Keeps all version numbers in sync across the project.
#
# Usage:
#   .\bump-version.ps1 -Target app -Bump patch          # 1.19.4 -> 1.19.5
#   .\bump-version.ps1 -Target app -Bump minor          # 1.19.4 -> 1.20.0
#   .\bump-version.ps1 -Target app -Bump major          # 1.19.4 -> 2.0.0
#   .\bump-version.ps1 -Target plugin -Bump patch       # bump plugin version
#   .\bump-version.ps1 -Target script -Bump minor       # bump script version
#   .\bump-version.ps1 -Target all -Bump patch          # bump everything
#   .\bump-version.ps1 -Target app -Set "2.0.0"         # set exact version
#   .\bump-version.ps1 -DryRun                          # preview changes only

param(
    [Parameter(Mandatory=$false)]
    [ValidateSet("app","plugin","script","qupload","all")]
    [string]$Target = "app",

    [Parameter(Mandatory=$false)]
    [ValidateSet("major","minor","patch")]
    [string]$Bump = "patch",

    [Parameter(Mandatory=$false)]
    [string]$Set = "",

    [Parameter(Mandatory=$false)]
    [switch]$DryRun = $false
)

$ErrorActionPreference = "Stop"

# --- Self-lint ---
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
        exit 1
    }
}

# --- Helpers ---
function Bump-Semver {
    param([string]$Version, [string]$Part)
    $parts = $Version -split "\."
    $major = [int]$parts[0]
    $minor = if ($parts.Length -gt 1) { [int]$parts[1] } else { 0 }
    $patch = if ($parts.Length -gt 2) { [int]$parts[2] } else { 0 }

    switch ($Part) {
        "major" { $major++; $minor = 0; $patch = 0 }
        "minor" { $minor++; $patch = 0 }
        "patch" { $patch++ }
    }
    return "$major.$minor.$patch"
}

function Write-Change {
    param([string]$File, [string]$Old, [string]$New)
    if ($DryRun) {
        Write-Host "  [DRY RUN] " -NoNewline -ForegroundColor Yellow
    } else {
        Write-Host "  [UPDATED] " -NoNewline -ForegroundColor Green
    }
    Write-Host "$File : $Old -> $New" -ForegroundColor White
}

# --- Resolve project root (two levels up from scripts/) ---
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$ProjectRoot = (Resolve-Path (Join-Path $ScriptDir "..\..")).Path

# --- File paths ---
$versionJsonPath    = Join-Path $ProjectRoot "public\version.json"
$runPs1Path         = Join-Path $ProjectRoot "run.ps1"
$powershellJsonPath = Join-Path $ProjectRoot "powershell.json"
$specOverviewPath   = Join-Path $ProjectRoot "spec\12-powershell-integration\00-overview.md"
$pluginEnumPath     = Join-Path $ProjectRoot "wp-plugins\riseup-asia-uploader\includes\Enums\PluginConfigType.php"
$quploadEnumPath    = Join-Path $ProjectRoot "wp-plugins\qupload\includes\Enums\PluginConfigType.php"
$quploadMainPath    = Join-Path $ProjectRoot "wp-plugins\qupload\qupload.php"

# --- Read current versions ---
$versionJson = $null
if (Test-Path $versionJsonPath) {
    $versionJson = Get-Content $versionJsonPath -Raw | ConvertFrom-Json
}

$currentAppVersion     = if ($versionJson) { $versionJson.version } else { "0.0.0" }
$currentScriptVersion  = if ($versionJson) { $versionJson.scriptVersion } else { "0.0.0" }
$currentPluginVersion  = if ($versionJson) { $versionJson.wpPluginVersion } else { "0.0.0" }
$currentQUploadVersion = if ($versionJson -and $versionJson.PSObject.Properties['quploadVersion']) { $versionJson.quploadVersion } else { "1.0.0" }

Write-Host ""
Write-Host "Version Bump Automation" -ForegroundColor Cyan
Write-Host "=======================" -ForegroundColor Cyan
Write-Host "  Target: $Target" -ForegroundColor Gray
Write-Host "  Mode:   $(if ($Set -ne '') { 'Set to ' + $Set } else { 'Bump ' + $Bump })" -ForegroundColor Gray
Write-Host ""
Write-Host "Current Versions:" -ForegroundColor Yellow
Write-Host "  App:     $currentAppVersion" -ForegroundColor White
Write-Host "  Script:  $currentScriptVersion" -ForegroundColor White
Write-Host "  Plugin:  $currentPluginVersion" -ForegroundColor White
Write-Host "  QUpload: $currentQUploadVersion" -ForegroundColor White
Write-Host ""

# --- Compute new versions ---
$newAppVersion     = $currentAppVersion
$newScriptVersion  = $currentScriptVersion
$newPluginVersion  = $currentPluginVersion
$newQUploadVersion = $currentQUploadVersion

$bumpApp     = ($Target -eq "app") -or ($Target -eq "all")
$bumpScript  = ($Target -eq "script") -or ($Target -eq "all")
$bumpPlugin  = ($Target -eq "plugin") -or ($Target -eq "all")
$bumpQUpload = ($Target -eq "qupload") -or ($Target -eq "all")

if ($bumpApp) {
    $newAppVersion = if ($Set -ne "") { $Set } else { Bump-Semver $currentAppVersion $Bump }
}
if ($bumpScript) {
    $newScriptVersion = if ($Set -ne "") { $Set } else { Bump-Semver $currentScriptVersion $Bump }
}
if ($bumpPlugin) {
    $newPluginVersion = if ($Set -ne "") { $Set } else { Bump-Semver $currentPluginVersion $Bump }
}
if ($bumpQUpload) {
    $newQUploadVersion = if ($Set -ne "") { $Set } else { Bump-Semver $currentQUploadVersion $Bump }
}

Write-Host "New Versions:" -ForegroundColor Green
if ($bumpApp)     { Write-Host "  App:     $currentAppVersion -> $newAppVersion" -ForegroundColor White }
if ($bumpScript)  { Write-Host "  Script:  $currentScriptVersion -> $newScriptVersion" -ForegroundColor White }
if ($bumpPlugin)  { Write-Host "  Plugin:  $currentPluginVersion -> $newPluginVersion" -ForegroundColor White }
if ($bumpQUpload) { Write-Host "  QUpload: $currentQUploadVersion -> $newQUploadVersion" -ForegroundColor White }
Write-Host ""

$changeCount = 0

# =============================================================
# APP VERSION: public/version.json
# =============================================================
if ($bumpApp -and (Test-Path $versionJsonPath)) {
    $content = Get-Content $versionJsonPath -Raw
    $content = $content -replace """version"":\s*""[^""]+""", """version"": ""$newAppVersion"""
    $content = $content -replace """releaseDate"":\s*""[^""]+""", """releaseDate"": ""$(Get-Date -Format 'yyyy-MM-dd')"""
    if (-not $DryRun) { $content | Set-Content $versionJsonPath -Encoding UTF8 -NoNewline }
    Write-Change "public/version.json (version)" $currentAppVersion $newAppVersion
    $changeCount++
}

# =============================================================
# SCRIPT VERSION: run.ps1, powershell.json, version.json, spec
# =============================================================
if ($bumpScript) {
    # run.ps1 header comment
    if (Test-Path $runPs1Path) {
        $content = Get-Content $runPs1Path -Raw
        $content = $content -replace "# Version: [0-9]+\.[0-9]+\.[0-9]+", "# Version: $newScriptVersion"
        if (-not $DryRun) { $content | Set-Content $runPs1Path -Encoding UTF8 -NoNewline }
        Write-Change "run.ps1 (header)" $currentScriptVersion $newScriptVersion
        $changeCount++
    }

    # powershell.json
    if (Test-Path $powershellJsonPath) {
        $content = Get-Content $powershellJsonPath -Raw
        $content = $content -replace """version"":\s*""[^""]+""", """version"": ""$newScriptVersion"""
        if (-not $DryRun) { $content | Set-Content $powershellJsonPath -Encoding UTF8 -NoNewline }
        Write-Change "powershell.json" $currentScriptVersion $newScriptVersion
        $changeCount++
    }

    # public/version.json scriptVersion field
    if (Test-Path $versionJsonPath) {
        $content = Get-Content $versionJsonPath -Raw
        $content = $content -replace """scriptVersion"":\s*""[^""]+""", """scriptVersion"": ""$newScriptVersion"""
        if (-not $DryRun) { $content | Set-Content $versionJsonPath -Encoding UTF8 -NoNewline }
        Write-Change "public/version.json (scriptVersion)" $currentScriptVersion $newScriptVersion
        $changeCount++
    }

    # spec overview
    if (Test-Path $specOverviewPath) {
        $content = Get-Content $specOverviewPath -Raw
        $content = $content -replace "Script Version:\s*[0-9]+\.[0-9]+\.[0-9]+", "Script Version: $newScriptVersion"
        if (-not $DryRun) { $content | Set-Content $specOverviewPath -Encoding UTF8 -NoNewline }
        Write-Change "spec/12-powershell-integration/00-overview.md" $currentScriptVersion $newScriptVersion
        $changeCount++
    }
}

# =============================================================
# PLUGIN VERSION: PluginConfigType.php, version.json
# =============================================================
if ($bumpPlugin) {
    # PluginConfigType.php
    if (Test-Path $pluginEnumPath) {
        $content = Get-Content $pluginEnumPath -Raw
        $oldMatch = [regex]::Match($content, "case Version\s*=\s*'([^']+)'")
        $oldPluginVer = if ($oldMatch.Success) { $oldMatch.Groups[1].Value } else { $currentPluginVersion }
        $content = $content -replace "case Version\s*=\s*'[^']+'", "case Version         = '$newPluginVersion'"
        if (-not $DryRun) { $content | Set-Content $pluginEnumPath -Encoding UTF8 -NoNewline }
        Write-Change "PluginConfigType.php" $oldPluginVer $newPluginVersion
        $changeCount++
    }

    # public/version.json wpPluginVersion field
    if (Test-Path $versionJsonPath) {
        $content = Get-Content $versionJsonPath -Raw
        $content = $content -replace """wpPluginVersion"":\s*""[^""]+""", """wpPluginVersion"": ""$newPluginVersion"""
        if (-not $DryRun) { $content | Set-Content $versionJsonPath -Encoding UTF8 -NoNewline }
        Write-Change "public/version.json (wpPluginVersion)" $currentPluginVersion $newPluginVersion
        $changeCount++
    }
}

# =============================================================
# QUPLOAD VERSION: qupload PluginConfigType.php, qupload.php, version.json
# =============================================================
if ($bumpQUpload) {
    # QUpload PluginConfigType.php
    if (Test-Path $quploadEnumPath) {
        $content = Get-Content $quploadEnumPath -Raw
        $oldMatch = [regex]::Match($content, "case Version\s*=\s*'([^']+)'")
        $oldQVer = if ($oldMatch.Success) { $oldMatch.Groups[1].Value } else { $currentQUploadVersion }
        $content = $content -replace "case Version\s*=\s*'[^']+'", "case Version       = '$newQUploadVersion'"
        if (-not $DryRun) { $content | Set-Content $quploadEnumPath -Encoding UTF8 -NoNewline }
        Write-Change "qupload/PluginConfigType.php" $oldQVer $newQUploadVersion
        $changeCount++
    }

    # QUpload main plugin file header
    if (Test-Path $quploadMainPath) {
        $content = Get-Content $quploadMainPath -Raw
        $content = $content -replace "\* Version:\s*[0-9]+\.[0-9]+\.[0-9]+", "* Version: $newQUploadVersion"
        if (-not $DryRun) { $content | Set-Content $quploadMainPath -Encoding UTF8 -NoNewline }
        Write-Change "qupload/qupload.php (header)" $currentQUploadVersion $newQUploadVersion
        $changeCount++
    }

    # public/version.json quploadVersion field
    if (Test-Path $versionJsonPath) {
        $content = Get-Content $versionJsonPath -Raw
        if ($content -match '"quploadVersion"') {
            $content = $content -replace """quploadVersion"":\s*""[^""]+""", """quploadVersion"": ""$newQUploadVersion"""
        } else {
            # Add field after wpPluginVersion if missing
            $content = $content -replace "(""wpPluginVersion"":\s*""[^""]+"")", "`$1,`n  ""quploadVersion"": ""$newQUploadVersion"""
        }
        if (-not $DryRun) { $content | Set-Content $versionJsonPath -Encoding UTF8 -NoNewline }
        Write-Change "public/version.json (quploadVersion)" $currentQUploadVersion $newQUploadVersion
        $changeCount++
    }
}

Write-Host ""
if ($DryRun) {
    Write-Host "DRY RUN complete - $changeCount file(s) would be updated" -ForegroundColor Yellow
} else {
    Write-Host "Done! $changeCount file(s) updated" -ForegroundColor Green
}
Write-Host ""
