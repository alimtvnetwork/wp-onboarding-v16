# Module: upload-single.ps1
# Atomic upload operation: uploads one plugin ZIP to one WordPress site.
# Supports cross-upload: QUpload uploaded via Riseup Asia API, Riseup uploaded via QUpload API.
# Dot-sourced by run.ps1 - expects plugin-helpers.ps1 loaded.

# Cross-upload mapping: which API namespace to use for each plugin slug.
# QUpload should be uploaded via Riseup Asia API (cross-upload) for resilience.
# Riseup Asia should be uploaded via QUpload API (already the default).
# Other plugins use QUpload API (default).
$script:CrossUploadMap = @{
    'qupload' = 'riseup-asia-api/v1'
}

function Test-CrossUploadAvailable {
    param(
        [Parameter(Mandatory)][string]$SiteUrl,
        [Parameter(Mandatory)][string]$ApiNamespace,
        [Parameter(Mandatory)][string]$Username,
        [Parameter(Mandatory)][string]$Password
    )

    $statusUrl = "$($SiteUrl.TrimEnd('/'))/wp-json/$ApiNamespace/status"
    $authString = "$($Username):$($Password)"
    $authBytes = [System.Text.Encoding]::UTF8.GetBytes($authString)
    $authBase64 = [Convert]::ToBase64String($authBytes)

    try {
        $response = Invoke-WebRequest -Uri $statusUrl -Method Get -Headers @{
            "Authorization" = "Basic $authBase64"
            "Accept" = "application/json"
        } -TimeoutSec 10 -UseBasicParsing -ErrorAction Stop

        $parsed = $response.Content | ConvertFrom-Json
        $isSuccess = $parsed.Status.IsSuccess
        return $isSuccess -eq $true
    } catch {
        return $false
    }
}

function Get-UploadApiNamespace {
    param(
        [Parameter(Mandatory)][string]$PluginSlug,
        [Parameter(Mandatory)][string]$SiteUrl,
        [Parameter(Mandatory)][string]$Username,
        [Parameter(Mandatory)][string]$Password
    )

    $crossNamespace = $script:CrossUploadMap[$PluginSlug]
    $hasCrossUpload = ($null -ne $crossNamespace)

    if (-not $hasCrossUpload) {
        return "qupload-api/v1"
    }

    Write-Host "    Cross-upload: checking $crossNamespace availability..." -ForegroundColor DarkGray
    $isCrossAvailable = Test-CrossUploadAvailable -SiteUrl $SiteUrl -ApiNamespace $crossNamespace -Username $Username -Password $Password

    if ($isCrossAvailable) {
        Write-Host "    Cross-upload: $crossNamespace available — using cross-upload for resilience" -ForegroundColor Green
        return $crossNamespace
    }

    Write-Host "    Cross-upload: $crossNamespace NOT available — falling back to self-upload (qupload-api/v1)" -ForegroundColor Yellow
    return "qupload-api/v1"
}

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
        [switch]$Quiet,
        [switch]$VerboseMode
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

    # Determine API namespace (cross-upload or default)
    $apiNamespace = Get-UploadApiNamespace -PluginSlug $PluginSlug -SiteUrl $SiteUrl -Username $Username -Password $Password

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
            $verboseFlag = @()
            if ($VerboseMode) { $verboseFlag += "-vb" }
            $result.Output = (& $QUploadScript -jc $jsonConfigStr -a -api $apiNamespace -spc @verboseFlag 2>&1 | Out-String)
            $invokeSucceeded = $true
        } else {
            $verboseFlag = @()
            if ($VerboseMode) { $verboseFlag += "-vb" }
            & $QUploadScript -jc $jsonConfigStr -a -api $apiNamespace -spc @verboseFlag
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
