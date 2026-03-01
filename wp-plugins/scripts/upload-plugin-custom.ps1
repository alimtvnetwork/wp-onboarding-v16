# WordPress Plugin Uploader - Custom Path Wrapper
# Upload any plugin by specifying its full folder path.
#
# Usage:
#   .\upload-plugin-custom.ps1 -p "C:\path\to\my-plugin"
#   .\upload-plugin-custom.ps1 -PluginPath "C:\path\to\my-plugin" -Activate
#   .\upload-plugin-custom.ps1 -p "C:\path\to\my-plugin" -SiteUrl "https://example.com" -User "admin" -Password "xxxx"

param(
    [Alias('p')]
    [Parameter(Mandatory=$true)]
    [string]$PluginPath,

    [Parameter(Mandatory=$false)]
    [switch]$Activate = $false,

    [Parameter(Mandatory=$false)]
    [string]$SiteUrl = "",

    [Parameter(Mandatory=$false)]
    [string]$User = "",

    [Parameter(Mandatory=$false)]
    [string]$Password = "",

    [Parameter(Mandatory=$false)]
    [switch]$SkipGitPull = $false,

    [Parameter(Mandatory=$false)]
    [switch]$Quiet = $false
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

# Validate plugin path
if (-not (Test-Path $PluginPath)) {
    Write-Host "ERROR: Plugin folder not found: $PluginPath" -ForegroundColor Red
    exit 1
}

# Find upload-plugin-v2.ps1
$uploadScript = Join-Path $ScriptDir "upload-plugin-v2.ps1"
if (-not (Test-Path $uploadScript)) {
    Write-Host "ERROR: upload-plugin-v2.ps1 not found at: $uploadScript" -ForegroundColor Red
    exit 1
}

# If site credentials provided directly, pass them through
if ($SiteUrl -ne "" -and $User -ne "" -and $Password -ne "") {
    $args = @(
        "-PluginPath", $PluginPath,
        "-SiteUrl", $SiteUrl,
        "-User", $User,
        "-Password", $Password
    )
    if ($Activate) { $args += "-Activate" }
    if ($SkipGitPull) { $args += "-SkipGitPull" }
    if ($Quiet) { $args += "-Quiet" }

    & $uploadScript @args
    exit $LASTEXITCODE
}

# Otherwise, use wp-plugin-config.json for site credentials
$wpConfigPath = Join-Path $ScriptDir "wp-plugin-config.json"
if (-not (Test-Path $wpConfigPath)) {
    Write-Host "ERROR: wp-plugin-config.json not found at: $wpConfigPath" -ForegroundColor Red
    Write-Host "Either provide -SiteUrl, -User, -Password or create wp-plugin-config.json" -ForegroundColor Yellow
    exit 1
}

$wpConfig = Get-Content $wpConfigPath -Raw | ConvertFrom-Json
$wpConfig.pluginFolderPath = $PluginPath
$jsonConfigStr = ($wpConfig | ConvertTo-Json -Compress)

Write-Host ""
Write-Host "  Plugin Path: $PluginPath" -ForegroundColor Cyan
Write-Host "  Site:        $($wpConfig.wordPressSiteURL)" -ForegroundColor Gray
Write-Host ""

$scriptArgs = @("-JsonConfig", $jsonConfigStr)
if ($Activate) { $scriptArgs += "-Activate" }
if ($SkipGitPull) { $scriptArgs += "-SkipGitPull" }
if ($Quiet) { $scriptArgs += "-Quiet" }

& $uploadScript @scriptArgs
exit $LASTEXITCODE
