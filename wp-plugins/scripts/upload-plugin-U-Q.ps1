# Quick Upload Plugin Uploader - PowerShell Script
# Uploads a plugin ZIP to a WordPress site via the QUpload plugin API
# Script name: upload-plugin-U-Q.ps1

param(
    [Parameter(Mandatory=$false)]
    [string]$ConfigPath = "",

    [Parameter(Mandatory=$false)]
    [string]$PluginPath = "",

    [Parameter(Mandatory=$false)]
    [string]$SiteUrl = "",

    [Parameter(Mandatory=$false)]
    [string]$User = "",

    [Parameter(Mandatory=$false)]
    [string]$Password = "",

    [Parameter(Mandatory=$false)]
    [string]$Slug = "",

    [Parameter(Mandatory=$false)]
    [switch]$Activate = $false,

    [Parameter(Mandatory=$false)]
    [switch]$DeleteZip = $false,

    [Parameter(Mandatory=$false)]
    [switch]$Quiet = $false,

    [Parameter(Mandatory=$false)]
    [string]$JsonConfig = ""
)

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

# Helper function for output
function Write-Status {
    param([string]$Message, [string]$Color = "White", [switch]$NoNewline)
    if (-not $Quiet) {
        if ($NoNewline) {
            Write-Host $Message -ForegroundColor $Color -NoNewline
        } else {
            Write-Host $Message -ForegroundColor $Color
        }
    }
}

# Initialize variables
$PluginFolderPath = ""
$WordPressSiteURL = ""
$Username = ""
$AppPassword = ""
$ActivateAfterInstall = $true
$DeleteZipAfterUpload = $false
$PluginSlug = ""

# Priority 1: JSON config string from command line
if ($JsonConfig -ne "") {
    Write-Status "Parsing inline JSON config..." -Color Gray
    try {
        $config = $JsonConfig | ConvertFrom-Json
        $PluginFolderPath = $config.pluginFolderPath
        $WordPressSiteURL = $config.wordPressSiteURL.TrimEnd("/")
        $Username = $config.username
        $AppPassword = $config.appPassword
        if ($null -ne $config.activateAfterInstall) { $ActivateAfterInstall = $config.activateAfterInstall }
        if ($null -ne $config.deleteZipAfterUpload) { $DeleteZipAfterUpload = $config.deleteZipAfterUpload }
        if ($config.pluginSlug) { $PluginSlug = $config.pluginSlug }
    } catch {
        Write-Host "Error: Invalid JSON config: $_" -ForegroundColor Red
        exit 1
    }
}
# Priority 2: Direct command-line parameters
elseif ($PluginPath -ne "" -and $SiteUrl -ne "" -and $User -ne "" -and $Password -ne "") {
    Write-Status "Using command-line parameters..." -Color Gray
    $PluginFolderPath = $PluginPath
    $WordPressSiteURL = $SiteUrl.TrimEnd("/")
    $Username = $User
    $AppPassword = $Password
    $ActivateAfterInstall = $Activate.IsPresent
    $DeleteZipAfterUpload = $DeleteZip.IsPresent
    if ($Slug -ne "") { $PluginSlug = $Slug }
}
# Priority 3: Config file
else {
    if ($ConfigPath -eq "") {
        $scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
        $ConfigPath = Join-Path $scriptDir "qupload-config.json"
    }

    if (-not (Test-Path $ConfigPath)) {
        Write-Host ""
        Write-Host "Quick Upload - Plugin Uploader" -ForegroundColor Cyan
        Write-Host "==============================" -ForegroundColor Cyan
        Write-Host ""
        Write-Host "Usage:" -ForegroundColor Yellow
        Write-Host "  .\upload-plugin-U-Q.ps1 -PluginPath 'C:\path\to\plugin' -SiteUrl 'https://site.com' -User 'admin' -Password 'app-password' [-Activate]" -ForegroundColor Gray
        Write-Host ""
        Write-Host "Or create a config file at: $ConfigPath" -ForegroundColor Yellow
        exit 1
    }

    Write-Status "Loading config from: $ConfigPath" -Color Gray
    $config = Get-Content $ConfigPath -Raw | ConvertFrom-Json
    $PluginFolderPath = $config.pluginFolderPath
    $WordPressSiteURL = $config.wordPressSiteURL.TrimEnd("/")
    $Username = $config.username
    $AppPassword = $config.appPassword
    if ($null -ne $config.activateAfterInstall) { $ActivateAfterInstall = $config.activateAfterInstall }
    if ($null -ne $config.deleteZipAfterUpload) { $DeleteZipAfterUpload = $config.deleteZipAfterUpload }
    if ($config.pluginSlug) { $PluginSlug = $config.pluginSlug }
}

# Validate required parameters
if ($PluginFolderPath -eq "" -or $WordPressSiteURL -eq "" -or $Username -eq "" -or $AppPassword -eq "") {
    Write-Host "Error: Missing required parameters (PluginPath, SiteUrl, User, Password)" -ForegroundColor Red
    exit 1
}

Write-Status ""
Write-Status "========================================" -Color Cyan
Write-Status "  Quick Upload - Plugin Uploader" -Color Cyan
Write-Status "  Using QUpload API" -Color Cyan
Write-Status "========================================" -Color Cyan
Write-Status ""

# Step 1: Verify plugin folder exists
if (-not (Test-Path $PluginFolderPath)) {
    Write-Host "Error: Plugin folder not found at: $PluginFolderPath" -ForegroundColor Red
    exit 1
}

$folderName = Split-Path $PluginFolderPath -Leaf
if ($PluginSlug -eq "") { $PluginSlug = $folderName }

Write-Status "[1/4] Plugin: $folderName" -Color Yellow
Write-Status "      Path: $PluginFolderPath" -Color Gray
Write-Status "      Slug: $PluginSlug" -Color Gray

# Read local version from plugin header
$LocalVersion = "unknown"
$mainFiles = Get-ChildItem $PluginFolderPath -Filter "*.php" | Where-Object {
    (Get-Content $_.FullName -Head 5) -match "Plugin Name:"
} | Select-Object -First 1

if ($mainFiles) {
    $verContent = Get-Content $mainFiles.FullName -Raw
    if ($verContent -match "Version:\s*([0-9]+\.[0-9]+\.[0-9]+)") {
        $LocalVersion = $Matches[1]
    }
}

Write-Status "      Version: $LocalVersion" -Color Gray

# Step 2: Create ZIP file
$OutputZipPath = Join-Path $env:TEMP "qupload-$PluginSlug-$(Get-Date -Format 'yyyyMMddHHmmss').zip"

if (Test-Path $OutputZipPath) {
    Remove-Item $OutputZipPath -Force
}

Write-Status ""
Write-Status "[2/4] Creating ZIP file..." -Color Yellow

try {
    $tempDir = Join-Path $env:TEMP "qupload-zip-$(Get-Random)"
    $pluginTempDir = Join-Path $tempDir $folderName
    New-Item -ItemType Directory -Path $pluginTempDir -Force | Out-Null
    Copy-Item -Path "$PluginFolderPath\*" -Destination $pluginTempDir -Recurse

    Add-Type -AssemblyName System.IO.Compression.FileSystem
    [System.IO.Compression.ZipFile]::CreateFromDirectory(
        $tempDir,
        $OutputZipPath,
        [System.IO.Compression.CompressionLevel]::SmallestSize,
        $false
    )

    Remove-Item $tempDir -Recurse -Force

    $zipSize = (Get-Item $OutputZipPath).Length
    $zipSizeMB = [math]::Round($zipSize / 1MB, 2)
    Write-Status "      ZIP created: $OutputZipPath ($zipSizeMB MB)" -Color Green
} catch {
    Write-Host "Error creating ZIP: $_" -ForegroundColor Red
    exit 1
}

# Step 3: Upload to QUpload API
Write-Status ""
Write-Status "[3/4] Uploading to QUpload API..." -Color Yellow
Write-Status "      Site: $WordPressSiteURL" -Color Gray
Write-Status "      Endpoint: /wp-json/qupload/v1/upload" -Color Gray

$uploadUrl = "$WordPressSiteURL/wp-json/qupload/v1/upload"
$authString = "$($Username):$($AppPassword)"
$authBytes = [System.Text.Encoding]::UTF8.GetBytes($authString)
$authBase64 = [Convert]::ToBase64String($authBytes)

try {
    $zipBytes = [System.IO.File]::ReadAllBytes($OutputZipPath)
    $zipBase64 = [Convert]::ToBase64String($zipBytes)

    $body = @{
        plugin_zip = $zipBase64
        slug = $PluginSlug
        activate = $ActivateAfterInstall
    } | ConvertTo-Json

    $headers = @{
        "Authorization" = "Basic $authBase64"
        "Accept" = "application/json"
    }

    $response = Invoke-WebRequest -Uri $uploadUrl -Method Post -Headers $headers -Body $body -ContentType "application/json" -TimeoutSec 120 -UseBasicParsing -ErrorAction Stop
    $parsed = $response.Content | ConvertFrom-Json

    $isSuccess = $parsed.Status.IsSuccess

    if ($isSuccess) {
        $result = $parsed.Results[0]
        Write-Status ""
        Write-Status "[4/4] Upload successful!" -Color Green
        Write-Status "      Plugin: $($result.PluginSlug)" -Color White
        Write-Status "      Version: $($result.PluginVersion)" -Color White
        Write-Status "      Is Update: $($result.IsUpdate)" -Color White
        Write-Status "      Activated: $($result.Activated)" -Color White

        if ($Quiet) {
            $quietOutput = @{
                success = $true
                plugin = $result.PluginSlug
                activated = $result.Activated
            } | ConvertTo-Json -Compress
            Write-Output $quietOutput
        }
    } else {
        Write-Host ""
        Write-Host "[4/4] Upload failed!" -ForegroundColor Red
        Write-Host "      Message: $($parsed.Status.Message)" -ForegroundColor Yellow

        if ($parsed.Errors) {
            Write-Host "      Error: $($parsed.Errors.BackendMessage)" -ForegroundColor Yellow
        }

        if ($Quiet) {
            $quietOutput = @{
                success = $false
                error = $parsed.Status.Message
            } | ConvertTo-Json -Compress
            Write-Output $quietOutput
        }

        exit 1
    }
} catch {
    Write-Host ""
    Write-Host "[4/4] Upload request failed!" -ForegroundColor Red
    Write-Host "      Error: $($_.Exception.Message)" -ForegroundColor Yellow

    # Try to extract response body
    if ($_.ErrorDetails -and $_.ErrorDetails.Message) {
        Write-Host "      Response: $($_.ErrorDetails.Message)" -ForegroundColor Gray
    }

    if ($Quiet) {
        $quietOutput = @{
            success = $false
            error = $_.Exception.Message
        } | ConvertTo-Json -Compress
        Write-Output $quietOutput
    }

    exit 1
}

# Cleanup ZIP if requested
if ($DeleteZipAfterUpload -or $DeleteZip) {
    if (Test-Path $OutputZipPath) {
        Remove-Item $OutputZipPath -Force
        Write-Status "      ZIP file deleted" -Color Gray
    }
}

Write-Status ""
Write-Status "========================================" -Color Cyan
Write-Status "  Done!" -Color Green
Write-Status "========================================" -Color Cyan
