# =============================================================================
# Quick Upload Plugin Uploader — PowerShell Script
# Uploads a plugin ZIP to a WordPress site via the QUpload REST API.
#
# Script: upload-plugin-U-Q.ps1
# Version: 1.1.0
#
# USAGE:
#   .\upload-plugin-U-Q.ps1 -h                      # Show help
#   .\upload-plugin-U-Q.ps1                          # Use qupload-config.json
#   .\upload-plugin-U-Q.ps1 -pp 'C:\plugin' -s 'https://site.com' -u 'admin' -pw 'pass'
#   .\upload-plugin-U-Q.ps1 -jc '{"pluginFolderPath":"...","wordPressSiteURL":"..."}'
#   .\upload-plugin-U-Q.ps1 -z -pp 'C:\plugin'      # ZIP only (no upload)
#
# FLAGS:
#   -h,  -help          Show help and exit
#   -cp, -configpath    Path to qupload-config.json
#   -pp, -pluginpath    Plugin folder path
#   -s,  -siteurl       WordPress site URL
#   -u,  -user          WordPress username
#   -pw, -password      WordPress application password
#   -sl, -slug          Plugin slug override
#   -a,  -activate      Activate plugin after install
#   -dz, -deletezip     Delete ZIP file after upload
#   -q,  -quiet         Suppress output (JSON-only output)
#   -jc, -jsonconfig    Inline JSON config string (overrides all other config)
#   -z,  -ziponly       Create ZIP only, do not upload
#
# CONFIGURATION (qupload-config.json):
#   {
#     "pluginFolderPath": "path/to/plugin",
#     "wordPressSiteURL": "https://yoursite.com",
#     "username": "admin",
#     "appPassword": "xxxx xxxx xxxx xxxx",
#     "activateAfterInstall": true,
#     "deleteZipAfterUpload": false
#   }
#
# COMPRESSION:
#   Uses System.IO.Compression with SmallestSize (level 9) for best compression.
# =============================================================================

param(
    # ── Help ─────────────────────────────────────────────────────────────────
    [Alias('h')]
    [switch]$Help,

    # ── Config file path ─────────────────────────────────────────────────────
    [Alias('cp')]
    [string]$ConfigPath = "",

    # ── Plugin folder path ───────────────────────────────────────────────────
    [Alias('pp')]
    [string]$PluginPath = "",

    # ── WordPress site URL ───────────────────────────────────────────────────
    [Alias('s')]
    [string]$SiteUrl = "",

    # ── WordPress username ───────────────────────────────────────────────────
    [Alias('u')]
    [string]$User = "",

    # ── WordPress application password ───────────────────────────────────────
    [Alias('pw')]
    [string]$Password = "",

    # ── Plugin slug override ─────────────────────────────────────────────────
    [Alias('sl')]
    [string]$Slug = "",

    # ── Activate plugin after install ────────────────────────────────────────
    [Alias('a')]
    [switch]$Activate = $false,

    # ── Delete ZIP after upload ──────────────────────────────────────────────
    [Alias('dz')]
    [switch]$DeleteZip = $false,

    # ── Suppress output (JSON-only) ──────────────────────────────────────────
    [Alias('q')]
    [switch]$Quiet = $false,

    # ── Inline JSON config (overrides file config) ───────────────────────────
    [Alias('jc')]
    [string]$JsonConfig = "",

    # ── ZIP-only mode: create ZIP without uploading ──────────────────────────
    [Alias('z')]
    [switch]$ZipOnly = $false
)

# =============================================================================
# SELF-LINT: Detect parse errors before execution
# =============================================================================
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

# =============================================================================
# HELP MODE
# =============================================================================
if ($Help) {
    Write-Host ""
    Write-Host "Quick Upload - Plugin Uploader (QUpload API)" -ForegroundColor Cyan
    Write-Host "=============================================" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "USAGE:" -ForegroundColor Yellow
    Write-Host "  .\upload-plugin-U-Q.ps1 [flags]"
    Write-Host ""
    Write-Host "FLAGS:" -ForegroundColor Yellow
    Write-Host "  -h,  -help          Show this help and exit"
    Write-Host "  -cp, -configpath    Path to qupload-config.json"
    Write-Host "  -pp, -pluginpath    Plugin folder path"
    Write-Host "  -s,  -siteurl       WordPress site URL"
    Write-Host "  -u,  -user          WordPress username"
    Write-Host "  -pw, -password      WordPress application password"
    Write-Host "  -sl, -slug          Plugin slug override (default: folder name)"
    Write-Host "  -a,  -activate      Activate plugin after install"
    Write-Host "  -dz, -deletezip     Delete ZIP after successful upload"
    Write-Host "  -q,  -quiet         JSON-only output (for scripting)"
    Write-Host "  -jc, -jsonconfig    Inline JSON config string"
    Write-Host "  -z,  -ziponly       Create ZIP only, skip upload"
    Write-Host ""
    Write-Host "EXAMPLES:" -ForegroundColor Yellow
    Write-Host "  .\upload-plugin-U-Q.ps1                          # Use qupload-config.json"
    Write-Host "  .\upload-plugin-U-Q.ps1 -pp 'wp-plugins/qupload' -s 'https://site.com' -u admin -pw 'pass' -a"
    Write-Host "  .\upload-plugin-U-Q.ps1 -jc '{...}'             # Inline JSON config"
    Write-Host "  .\upload-plugin-U-Q.ps1 -z -pp 'wp-plugins/qupload'  # ZIP only, no upload"
    Write-Host "  .\upload-plugin-U-Q.ps1 -z                       # ZIP default plugin only"
    Write-Host ""
    Write-Host "ZIP BEHAVIOR:" -ForegroundColor Yellow
    Write-Host "  ZIPs are created with maximum compression (SmallestSize)."
    Write-Host "  The ZIP filename includes the version from the PHP header:"
    Write-Host "    qupload-v1.0.0.zip, riseup-asia-uploader-v1.36.0.zip"
    Write-Host ""
    Write-Host "CONFIG FILE (qupload-config.json):" -ForegroundColor Yellow
    Write-Host "  {" -ForegroundColor Gray
    Write-Host "    ""pluginFolderPath"": ""path/to/plugin""," -ForegroundColor Gray
    Write-Host "    ""wordPressSiteURL"": ""https://yoursite.com""," -ForegroundColor Gray
    Write-Host "    ""username"": ""admin""," -ForegroundColor Gray
    Write-Host "    ""appPassword"": ""xxxx xxxx xxxx xxxx""," -ForegroundColor Gray
    Write-Host "    ""activateAfterInstall"": true," -ForegroundColor Gray
    Write-Host "    ""deleteZipAfterUpload"": false" -ForegroundColor Gray
    Write-Host "  }" -ForegroundColor Gray
    Write-Host ""
    exit 0
}

# =============================================================================
# HELPER FUNCTIONS
# =============================================================================

# Write-Status: Conditional output helper (suppressed in -Quiet mode)
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

# Get-PluginVersionFromHeader: Extracts version from WordPress plugin PHP header
function Get-PluginVersionFromHeader($PluginDir) {
    $mainFiles = Get-ChildItem $PluginDir -Filter "*.php" | Where-Object {
        (Get-Content $_.FullName -Head 5) -match "Plugin Name:"
    } | Select-Object -First 1

    if ($mainFiles) {
        $content = Get-Content $mainFiles.FullName -Raw
        $match = [regex]::Match($content, "Version:\s*(\d+\.\d+\.\d+)")
        if ($match.Success) { return $match.Groups[1].Value }
    }

    return "unknown"
}

# New-PluginZipFile: Creates a versioned ZIP with best compression
function New-PluginZipFile($PluginDir, $PluginSlug) {
    $version = Get-PluginVersionFromHeader $PluginDir
    $zipFileName = "$PluginSlug-v$version.zip"
    $zipOutputPath = Join-Path $env:TEMP "qupload-$PluginSlug-$(Get-Date -Format 'yyyyMMddHHmmss').zip"

    if (Test-Path $zipOutputPath) {
        Remove-Item $zipOutputPath -Force
    }

    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $tempDir = Join-Path $env:TEMP "qupload-zip-$(Get-Random)"
    $pluginTempDir = Join-Path $tempDir $PluginSlug
    New-Item -ItemType Directory -Path $pluginTempDir -Force | Out-Null
    Copy-Item -Path "$PluginDir\*" -Destination $pluginTempDir -Recurse

    # Best compression: SmallestSize (level 9)
    [System.IO.Compression.ZipFile]::CreateFromDirectory(
        $tempDir,
        $zipOutputPath,
        [System.IO.Compression.CompressionLevel]::SmallestSize,
        $false
    )

    Remove-Item $tempDir -Recurse -Force

    return @{
        Path = $zipOutputPath
        FileName = $zipFileName
        Version = $version
    }
}

# =============================================================================
# CONFIGURATION LOADING
# Priority: 1) -JsonConfig  2) ZIP-only quick mode (-z + -pp)  3) Full CLI params  4) Config file
# =============================================================================

$PluginFolderPath = ""
$WordPressSiteURL = ""
$Username = ""
$AppPassword = ""
$ActivateAfterInstall = $true
$DeleteZipAfterUpload = $false
$PluginSlug = ""

$useZipOnlyQuickPath = $ZipOnly -and $PluginPath -ne "" -and $JsonConfig -eq "" -and $SiteUrl -eq "" -and $User -eq "" -and $Password -eq ""

# Priority 1: Inline JSON config string
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
# Priority 2: ZIP-only quick mode with plugin path (no site creds required)
elseif ($useZipOnlyQuickPath) {
    Write-Status "Using ZIP-only quick mode with plugin path..." -Color Gray
    $PluginFolderPath = $PluginPath
    if ($Slug -ne "") { $PluginSlug = $Slug }
    $DeleteZipAfterUpload = $DeleteZip.IsPresent
}
# Priority 3: Direct CLI parameters for upload mode
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
# Priority 4: Config file (qupload-config.json)
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
        Write-Host "No config found. Run with -h for usage, or create:" -ForegroundColor Yellow
        Write-Host "  $ConfigPath" -ForegroundColor Gray
        Write-Host ""
        Write-Host "Or pass params directly:" -ForegroundColor Yellow
        Write-Host "  .\upload-plugin-U-Q.ps1 -pp 'path' -s 'https://site.com' -u 'admin' -pw 'pass'" -ForegroundColor Gray
        Write-Host "  .\upload-plugin-U-Q.ps1 -z -pp 'path'   # ZIP-only quick mode (no config needed)" -ForegroundColor Gray
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

# =============================================================================
# VALIDATION
# =============================================================================

# For ZIP-only mode, we only need the plugin path
if ($ZipOnly) {
    if ($PluginFolderPath -eq "" -and $PluginPath -ne "") {
        $PluginFolderPath = $PluginPath
    }
    if ($PluginFolderPath -eq "") {
        Write-Host "Error: Plugin path required for ZIP mode. Use -pp <path>" -ForegroundColor Red
        exit 1
    }
} else {
    # Full upload mode requires all credentials
    if ($PluginFolderPath -eq "" -or $WordPressSiteURL -eq "" -or $Username -eq "" -or $AppPassword -eq "") {
        Write-Host "Error: Missing required parameters (PluginPath, SiteUrl, User, Password)" -ForegroundColor Red
        Write-Host "Run with -h for usage." -ForegroundColor Yellow
        exit 1
    }
}

# Verify plugin folder exists
if (-not (Test-Path $PluginFolderPath)) {
    Write-Host "Error: Plugin folder not found at: $PluginFolderPath" -ForegroundColor Red
    exit 1
}

$folderName = Split-Path $PluginFolderPath -Leaf
if ($PluginSlug -eq "") { $PluginSlug = $folderName }

# =============================================================================
# BANNER
# =============================================================================
$modeLabel = if ($ZipOnly) { "ZIP Only" } else { "Upload" }

Write-Status ""
Write-Status "========================================" -Color Cyan
Write-Status "  Quick Upload - Plugin $modeLabel" -Color Cyan
Write-Status "  Using QUpload API" -Color Cyan
Write-Status "========================================" -Color Cyan
Write-Status ""

# =============================================================================
# STEP 1: Read plugin info
# =============================================================================
Write-Status "[1/4] Plugin: $folderName" -Color Yellow
Write-Status "      Path: $PluginFolderPath" -Color Gray
Write-Status "      Slug: $PluginSlug" -Color Gray

$LocalVersion = Get-PluginVersionFromHeader $PluginFolderPath
Write-Status "      Version: $LocalVersion" -Color Gray

# =============================================================================
# STEP 2: Create ZIP file (best compression)
# =============================================================================
Write-Status ""
Write-Status "[2/4] Creating ZIP file (SmallestSize compression)..." -Color Yellow

try {
    $zipResult = New-PluginZipFile $PluginFolderPath $PluginSlug
    $OutputZipPath = $zipResult.Path
    $zipSize = (Get-Item $OutputZipPath).Length
    $zipSizeMB = [math]::Round($zipSize / 1MB, 2)
    Write-Status "      ZIP created: $($zipResult.FileName) ($zipSizeMB MB)" -Color Green
} catch {
    Write-Host "Error creating ZIP: $_" -ForegroundColor Red
    exit 1
}

# =============================================================================
# ZIP-ONLY MODE: Exit after creating ZIP
# =============================================================================
if ($ZipOnly) {
    # Copy ZIP to plugin parent directory with versioned name
    $finalZipPath = Join-Path (Split-Path $PluginFolderPath -Parent) $zipResult.FileName
    Copy-Item $OutputZipPath $finalZipPath -Force
    Remove-Item $OutputZipPath -Force

    $finalSize = (Get-Item $finalZipPath).Length
    $finalSizeKB = [math]::Round($finalSize / 1024, 1)
    $finalSizeMB = [math]::Round($finalSize / 1048576, 2)
    $sizeLabel = if ($finalSizeMB -ge 1) { "$finalSizeMB MB" } else { "$finalSizeKB KB" }

    Write-Status ""
    Write-Status "  ZIP saved: $finalZipPath" -Color Green
    Write-Status "  Size: $sizeLabel" -Color White
    Write-Status ""
    Write-Status "========================================" -Color Cyan
    Write-Status "  Done! (ZIP only)" -Color Green
    Write-Status "========================================" -Color Cyan
    exit 0
}

# =============================================================================
# STEP 3: Upload to QUpload API
# =============================================================================
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

# =============================================================================
# CLEANUP: Delete ZIP if requested
# =============================================================================
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
