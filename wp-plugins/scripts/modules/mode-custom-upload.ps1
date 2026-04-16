# Module: mode-custom-upload.ps1
# Handles -ucp (upload custom plugin) mode for run.ps1
# Dot-sourced by run.ps1 — expects $ScriptDir, $WpScriptsDir to be set.

function Invoke-CustomPluginUploadMode {
    param(
        [string]$PluginSlug = "",
        [switch]$AllSites = $false,
        [string]$SiteName = "",
        [switch]$ListPlugins = $false,
        [switch]$ShowHelp = $false,
        [switch]$VerboseMode = $false
    )

    $customUploadScript = Join-Path (Join-Path (Join-Path $ScriptDir "wp-plugins") "scripts") "upload-custom-plugin.ps1"

    if (-not (Test-Path $customUploadScript)) {
        Write-Host "ERROR: upload-custom-plugin.ps1 not found at: $customUploadScript" -ForegroundColor Red
        exit 1
    }

    $scriptArgs = @()

    if ($ShowHelp) {
        $scriptArgs += "-Help"
        & $customUploadScript @scriptArgs
        exit 0
    }

    if ($ListPlugins) {
        $scriptArgs += "-List"
        & $customUploadScript @scriptArgs
        exit 0
    }

    if ([string]::IsNullOrWhiteSpace($PluginSlug)) {
        Write-Host "ERROR: Plugin slug required. Usage: .\run.ps1 -ucp <slug>" -ForegroundColor Red
        Write-Host "Use '.\run.ps1 -ucp -list' to see registered plugins." -ForegroundColor Yellow
        exit 2
    }

    $scriptArgs += @("-Slug", $PluginSlug)

    if ($AllSites) {
        $scriptArgs += "-All"
    } elseif ($SiteName -ne "") {
        $scriptArgs += @("-Site", $SiteName)
    }

    if ($VerboseMode) {
        $scriptArgs += "-Verbose"
    }

    & $customUploadScript @scriptArgs
    exit $LASTEXITCODE
}
