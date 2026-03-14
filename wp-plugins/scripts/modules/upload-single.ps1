# Module: upload-single.ps1
# Atomic upload operation: uploads one plugin ZIP to one WordPress site via QUpload API.
# Dot-sourced by run.ps1 - expects plugin-helpers.ps1 loaded.

function Invoke-SinglePluginUpload {
    param(
        [Parameter(Mandatory)][string]$QUploadScript,
        [Parameter(Mandatory)][string]$PluginPath,
        [Parameter(Mandatory)][string]$ZipPath,
        [Parameter(Mandatory)][string]$SiteUrl,
        [Parameter(Mandatory)][string]$Username,
        [Parameter(Mandatory)][string]$Password,
        [Parameter(Mandatory)][string]$PluginSlug,
        [Parameter(Mandatory)][string]$SiteName,
        [Parameter(Mandatory)][string]$PluginVersion,
        [switch]$Quiet
    )

    $result = @{
        Site     = $SiteName
        SiteUrl  = $SiteUrl
        Plugin   = $PluginSlug
        Version  = $PluginVersion
        Status   = "FAILED"
        ExitCode = 1
        Output   = ""
        Error    = $null
        Duration = 0
    }

    $sw = [System.Diagnostics.Stopwatch]::StartNew()

    $uploadConfig = @{
        pluginFolderPath     = $PluginPath
        outputZipPath        = $ZipPath
        wordPressSiteURL     = $SiteUrl.TrimEnd("/")
        username             = $Username
        appPassword          = $Password
        activateAfterInstall = $true
        deleteZipAfterUpload = $false
    }
    $jsonConfigStr = ($uploadConfig | ConvertTo-Json -Compress)

    $invokeSucceeded = $false

    try {
        if ($Quiet) {
            $ErrorActionPreference = "Stop"
            $global:LASTEXITCODE = $null
            $result.Output = (& $QUploadScript -jc $jsonConfigStr -a 2>&1 | Out-String)
            $invokeSucceeded = $true
        } else {
            & $QUploadScript -jc $jsonConfigStr -a
            $invokeSucceeded = $true
        }
    } catch {
        $result.Output = ($_ | Out-String).Trim()
        if ([string]::IsNullOrWhiteSpace($result.Output)) {
            $result.Output = $_.Exception.Message
        }
        $result.Error = $_.Exception.Message
    }

    $nativeExitCode = $LASTEXITCODE
    $hasNativeExitCode = ($null -ne $nativeExitCode -and "$nativeExitCode" -match '^-?\d+$')

    if ($hasNativeExitCode) {
        $result.ExitCode = [int]$nativeExitCode
    } elseif ($invokeSucceeded) {
        $result.ExitCode = 0
    }

    $result.Status = if ($result.ExitCode -eq 0) { "OK" } else { "FAILED (exit $($result.ExitCode))" }

    $sw.Stop()
    $result.Duration = $sw.Elapsed.TotalSeconds

    return $result
}
