# WordPress Plugin Uploader V2
# Enhanced version with Git Pull, Version Comparison, and Publish
# Uses Riseup Asia Uploader API for reliable uploads

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
    [switch]$SkipGitPull = $false,

    [Parameter(Mandatory=$false)]
    [switch]$Quiet = $false,

    [Parameter(Mandatory=$false)]
    [string]$JsonConfig = ""
)

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

# Extract error response body from exception (works on PS 5.1 and PS 7+)
function Get-ErrorResponseBody {
    param($ErrorRecord)
    if ($ErrorRecord.ErrorDetails -and $ErrorRecord.ErrorDetails.Message) {
        return $ErrorRecord.ErrorDetails.Message
    }
    if ($ErrorRecord.Exception.Response) {
        try {
            $stream = $ErrorRecord.Exception.Response.GetResponseStream()
            if ($stream) {
                $reader = New-Object System.IO.StreamReader($stream)
                try { $reader.BaseStream.Position = 0 } catch {}
                $body = $reader.ReadToEnd()
                $reader.Close()
                return $body
            }
        } catch {}
    }
    return ""
}

function Write-ErrorBody {
    param([string]$Body, [string]$Label = "Response Body")
    if ($Body -eq "") { return }
    Write-Host ""
    Write-Host "  ── $Label ──" -ForegroundColor DarkGray
    try {
        $errJson = $Body | ConvertFrom-Json
        if ($errJson.message) { Write-Host "  Message: $($errJson.message)" -ForegroundColor Red }
        if ($errJson.error -and $errJson.error.message) { Write-Host "  Error:   $($errJson.error.message)" -ForegroundColor Red }
        if ($errJson.code) { Write-Host "  Code:    $($errJson.code)" -ForegroundColor Red }
        if ($errJson.data -and $errJson.data.status) { Write-Host "  Status:  $($errJson.data.status)" -ForegroundColor Red }
        if ($errJson.stackTrace) {
            Write-Host "  Stack Trace:" -ForegroundColor Yellow
            Write-Host $errJson.stackTrace -ForegroundColor Gray
        }
        if ($errJson.stackTraceFrames) {
            Write-Host "  Stack Frames:" -ForegroundColor Yellow
            foreach ($frame in $errJson.stackTraceFrames) {
                $loc = "    $($frame.file):$($frame.line)"
                if ($frame.class) { $loc += " -> $($frame.class)::$($frame.function)" }
                elseif ($frame.function) { $loc += " -> $($frame.function)" }
                Write-Host $loc -ForegroundColor Gray
            }
        }
        if ($errJson.error -and $errJson.error.details) {
            $d = $errJson.error.details
            if ($d.stackTrace) {
                Write-Host "  Stack Trace:" -ForegroundColor Yellow
                Write-Host $d.stackTrace -ForegroundColor Gray
            }
            if ($d.stackTraceFrames) {
                Write-Host "  Stack Frames:" -ForegroundColor Yellow
                foreach ($frame in $d.stackTraceFrames) {
                    $loc = "    $($frame.file):$($frame.line)"
                    if ($frame.class) { $loc += " -> $($frame.class)::$($frame.function)" }
                    elseif ($frame.function) { $loc += " -> $($frame.function)" }
                    Write-Host $loc -ForegroundColor Gray
                }
            }
        }
        $hasKnown = $errJson.message -or $errJson.error -or $errJson.stackTrace -or $errJson.code
        if (-not $hasKnown) { Write-Host $Body -ForegroundColor Gray }
    } catch {
        Write-Host $Body -ForegroundColor Gray
    }
    Write-Host "  ────────────────────" -ForegroundColor DarkGray
}

# Initialize variables
$PluginFolderPath = ""
$WordPressSiteURL = ""
$Username = ""
$AppPassword = ""
$OutputZipPath = ""
$ActivateAfterInstall = $false
$DeleteZipAfterUpload = $false
$PluginSlug = ""

# ============================================================================
# CONFIG LOADING (same priority as V1)
# ============================================================================

if ($JsonConfig -ne "") {
    Write-Status "Parsing inline JSON config..." -Color Gray
    try {
        $config = $JsonConfig | ConvertFrom-Json
        $PluginFolderPath = $config.pluginFolderPath
        $WordPressSiteURL = $config.wordPressSiteURL.TrimEnd('/')
        $Username = $config.username
        $AppPassword = $config.appPassword
        if ($config.outputZipPath) { $OutputZipPath = $config.outputZipPath }
        if ($null -ne $config.activateAfterInstall) { $ActivateAfterInstall = $config.activateAfterInstall }
        if ($null -ne $config.deleteZipAfterUpload) { $DeleteZipAfterUpload = $config.deleteZipAfterUpload }
        if ($config.pluginSlug) { $PluginSlug = $config.pluginSlug }
    } catch {
        Write-Host "Error: Invalid JSON config: $_" -ForegroundColor Red
        exit 1
    }
}
elseif ($PluginPath -ne "" -and $SiteUrl -ne "" -and $User -ne "" -and $Password -ne "") {
    Write-Status "Using command-line parameters..." -Color Gray
    $PluginFolderPath = $PluginPath
    $WordPressSiteURL = $SiteUrl.TrimEnd('/')
    $Username = $User
    $AppPassword = $Password
    $ActivateAfterInstall = $Activate.IsPresent
    $DeleteZipAfterUpload = $DeleteZip.IsPresent
    if ($Slug -ne "") { $PluginSlug = $Slug }
}
else {
    if ($ConfigPath -eq "") {
        $scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
        $ConfigPath = Join-Path $scriptDir "wp-plugin-config.json"
    }

    if (-not (Test-Path $ConfigPath)) {
        Write-Host ""
        Write-Host "WordPress Plugin Uploader V2" -ForegroundColor Cyan
        Write-Host "============================" -ForegroundColor Cyan
        Write-Host ""
        Write-Host "Usage:" -ForegroundColor Yellow
        Write-Host "  .\upload-plugin-v2.ps1 -PluginPath 'C:\path' -SiteUrl 'https://site.com' -User 'admin' -Password 'pass'" -ForegroundColor Gray
        Write-Host "  .\upload-plugin-v2.ps1 -ConfigPath 'path\to\config.json'" -ForegroundColor Gray
        Write-Host "  .\upload-plugin-v2.ps1 -SkipGitPull  # skip git pull step" -ForegroundColor Gray
        Write-Host ""
        exit 1
    }

    Write-Status "Loading config from: $ConfigPath" -Color Gray
    $config = Get-Content $ConfigPath -Raw | ConvertFrom-Json
    $PluginFolderPath = $config.pluginFolderPath
    $WordPressSiteURL = $config.wordPressSiteURL.TrimEnd('/')
    $Username = $config.username
    $AppPassword = $config.appPassword
    if ($config.outputZipPath) { $OutputZipPath = $config.outputZipPath }
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
Write-Status "===============================================" -Color Cyan
Write-Status "  WordPress Plugin Uploader V2" -Color Cyan
Write-Status "  Git Pull → Version Compare → Publish" -Color Cyan
Write-Status "===============================================" -Color Cyan
Write-Status ""

$folderName = Split-Path $PluginFolderPath -Leaf
if ($PluginSlug -eq "") { $PluginSlug = $folderName }

# ============================================================================
# STEP 1: GIT PULL
# ============================================================================
if (-not $SkipGitPull) {
    Write-Status "[1/6] Git pull (current branch)..." -Color Yellow

    # Find git root from plugin folder
    $gitDir = $PluginFolderPath
    $foundGit = $false
    for ($i = 0; $i -lt 10; $i++) {
        if (Test-Path (Join-Path $gitDir ".git")) {
            $foundGit = $true
            break
        }
        $parent = Split-Path $gitDir -Parent
        if ($parent -eq $gitDir) { break }
        $gitDir = $parent
    }

    if ($foundGit) {
        Push-Location $gitDir
        try {
            $branch = (git rev-parse --abbrev-ref HEAD 2>&1).Trim()
            Write-Status "      Branch: $branch" -Color Gray
            git pull 2>&1 | Out-Host
            if ($LASTEXITCODE -ne 0) {
                Write-Host "  WARNING: git pull failed, continuing anyway..." -ForegroundColor Yellow
            } else {
                Write-Status "      ✓ Git pull complete" -Color Green
            }
        } finally {
            Pop-Location
        }
    } else {
        Write-Status "      Skipping git pull (no .git found)" -Color Gray
    }
} else {
    Write-Status "[1/6] Skipping git pull (-SkipGitPull)" -Color Gray
}
Write-Status ""

# ============================================================================
# STEP 2: READ LOCAL VERSION
# ============================================================================
Write-Status "[2/6] Reading local plugin version..." -Color Yellow

$LocalVersion = "unknown"
$constantsFile = Join-Path $PluginFolderPath "includes/constants.php"
if (-not (Test-Path $constantsFile)) {
    # Try main plugin file header
    $mainFile = Get-ChildItem $PluginFolderPath -Filter "*.php" | Where-Object {
        (Get-Content $_.FullName -Head 5 -Raw) -match 'Plugin Name:'
    } | Select-Object -First 1
    if ($mainFile) {
        $content = Get-Content $mainFile.FullName -Raw
        if ($content -match "Version:\s*([0-9]+\.[0-9]+\.[0-9]+)") {
            $LocalVersion = $Matches[1]
        }
    }
} else {
    $content = Get-Content $constantsFile -Raw
    if ($content -match "RISEUP_VERSION.*?'([0-9]+\.[0-9]+\.[0-9]+)'") {
        $LocalVersion = $Matches[1]
    }
}

Write-Status "      Local Version:  $LocalVersion" -Color White
Write-Status "      Plugin Folder:  $folderName" -Color Gray
Write-Status "      Slug:           $PluginSlug" -Color Gray
Write-Status ""

# ============================================================================
# STEP 3: GET REMOTE VERSION
# ============================================================================
Write-Status "[3/6] Checking remote version on $WordPressSiteURL..." -Color Yellow

$RemoteVersion = "not installed"
$CleanAppPassword = $AppPassword -replace '\s', ''
$base64Auth = [Convert]::ToBase64String([Text.Encoding]::ASCII.GetBytes("${Username}:${CleanAppPassword}"))

$headers = @{
    "Authorization" = "Basic $base64Auth"
}

$apiNamespaces = @(
    @{ name = "riseup-asia-uploader/v1"; display = "Riseup Asia Uploader" },
    @{ name = "riseup-uploader/v1"; display = "Riseup Uploader (Legacy)" }
)

$activeNamespace = $null
foreach ($ns in $apiNamespaces) {
    $statusUrl = "$WordPressSiteURL/wp-json/$($ns.name)/status"
    try {
        $statusResponse = Invoke-RestMethod -Uri $statusUrl -Method Get -Headers $headers -ErrorAction Stop
        if ($statusResponse.success -eq $true -or $statusResponse.version) {
            $RemoteVersion = $statusResponse.version
            $activeNamespace = $ns.name
            Write-Status "      $($ns.display) is active!" -Color Green
            break
        }
    } catch {
        # Try next namespace
    }
}

Write-Status "      Remote Version: $RemoteVersion" -Color White
Write-Status ""

# ============================================================================
# STEP 4: VERSION COMPARISON
# ============================================================================
Write-Status "[4/6] Version comparison..." -Color Yellow

$VersionAction = "install"
if ($RemoteVersion -ne "not installed" -and $RemoteVersion -ne "unknown") {
    # Compare versions numerically
    $localParts = $LocalVersion -split '\.' | ForEach-Object { [int]$_ }
    $remoteParts = $RemoteVersion -split '\.' | ForEach-Object { [int]$_ }

    $maxLen = [Math]::Max($localParts.Count, $remoteParts.Count)
    $comparison = 0
    for ($i = 0; $i -lt $maxLen; $i++) {
        $l = if ($i -lt $localParts.Count) { $localParts[$i] } else { 0 }
        $r = if ($i -lt $remoteParts.Count) { $remoteParts[$i] } else { 0 }
        if ($l -gt $r) { $comparison = 1; break }
        if ($l -lt $r) { $comparison = -1; break }
    }

    if ($comparison -gt 0) {
        $VersionAction = "upgrade"
        Write-Status "      ▲ UPGRADE: $RemoteVersion → $LocalVersion" -Color Green
    } elseif ($comparison -lt 0) {
        $VersionAction = "downgrade"
        Write-Status "      ▼ DOWNGRADE: $RemoteVersion → $LocalVersion" -Color Red
    } else {
        $VersionAction = "reinstall"
        Write-Status "      ═ SAME VERSION: $LocalVersion (reinstall)" -Color Yellow
    }
} else {
    Write-Status "      ★ FRESH INSTALL: $LocalVersion" -Color Cyan
}

Write-Status ""
Write-Status "  ┌──────────────────────────────────────────┐" -Color DarkGray
Write-Status "  │  Local:  v$LocalVersion" -Color White -NoNewline
Write-Status (" " * [Math]::Max(0, 33 - $LocalVersion.Length)) -Color DarkGray -NoNewline
Write-Status "│" -Color DarkGray
Write-Status "  │  Remote: v$RemoteVersion" -Color White -NoNewline
Write-Status (" " * [Math]::Max(0, 33 - $RemoteVersion.Length)) -Color DarkGray -NoNewline
Write-Status "│" -Color DarkGray
Write-Status "  │  Action: $VersionAction" -Color White -NoNewline
Write-Status (" " * [Math]::Max(0, 33 - $VersionAction.Length)) -Color DarkGray -NoNewline
Write-Status "│" -Color DarkGray
Write-Status "  └──────────────────────────────────────────┘" -Color DarkGray
Write-Status ""

# ============================================================================
# STEP 5: CREATE ZIP
# ============================================================================
Write-Status "[5/6] Creating ZIP file..." -Color Yellow

if (-not (Test-Path $PluginFolderPath)) {
    Write-Host "Error: Plugin folder not found at: $PluginFolderPath" -ForegroundColor Red
    exit 1
}

if ($OutputZipPath -eq "") {
    $OutputZipPath = Join-Path $env:TEMP "$folderName-$(Get-Date -Format 'yyyyMMddHHmmss').zip"
}

if (Test-Path $OutputZipPath) {
    Remove-Item $OutputZipPath -Force
}

try {
    $tempDir = Join-Path $env:TEMP "wp-plugin-upload-$(Get-Random)"
    $pluginTempDir = Join-Path $tempDir $folderName
    New-Item -ItemType Directory -Path $pluginTempDir -Force | Out-Null
    Copy-Item -Path "$PluginFolderPath\*" -Destination $pluginTempDir -Recurse
    Compress-Archive -Path $pluginTempDir -DestinationPath $OutputZipPath -CompressionLevel Optimal
    Remove-Item $tempDir -Recurse -Force

    $zipSize = (Get-Item $OutputZipPath).Length
    $zipSizeKB = [math]::Round($zipSize / 1KB, 2)
    Write-Status "      ✓ ZIP created: $zipSizeKB KB" -Color Green
} catch {
    Write-Host "      Error creating ZIP: $_" -ForegroundColor Red
    exit 1
}

Write-Status ""

# ============================================================================
# STEP 6: UPLOAD & PUBLISH
# ============================================================================
Write-Status "[6/6] Publishing to WordPress..." -Color Yellow
Write-Status "      Site: $WordPressSiteURL" -Color Gray
Write-Status "      User: $Username" -Color Gray

$uploadSuccess = $false

if ($activeNamespace) {
    # Use Riseup Asia Uploader
    $uploadUrl = "$WordPressSiteURL/wp-json/$activeNamespace/upload"

    Write-Status "      ── Request ──" -Color DarkGray
    Write-Status "      POST $uploadUrl" -Color White
    Write-Status "      Auth: Basic (user=$Username)" -Color Gray
    Write-Status "      Content-Type: application/json" -Color Gray

    try {
        $fileBytes = [System.IO.File]::ReadAllBytes($OutputZipPath)
        $base64Data = [Convert]::ToBase64String($fileBytes)

        $uploadBody = @{
            plugin_zip = $base64Data
            slug = $PluginSlug
            activate = $ActivateAfterInstall
        } | ConvertTo-Json

        $bodySizeKB = [math]::Round($uploadBody.Length / 1KB, 1)
        Write-Status "      Body: {slug: `"$PluginSlug`", activate: $ActivateAfterInstall, plugin_zip: `"<base64 $bodySizeKB KB>`"}" -Color Gray
        Write-Status "      ────────────" -Color DarkGray

        $uploadHeaders = @{
            "Authorization" = "Basic $base64Auth"
            "Content-Type" = "application/json"
        }

        Write-Status "      Uploading via Riseup Asia Uploader..." -Color Gray
        $response = Invoke-RestMethod -Uri $uploadUrl -Method Post -Headers $uploadHeaders -Body $uploadBody -TimeoutSec 300
        $uploadSuccess = $true

        Write-Status ""
        Write-Status "===============================================" -Color Green
        Write-Status "  ✓ PUBLISH COMPLETE!" -Color Green
        Write-Status "===============================================" -Color Green
        Write-Status ""
        Write-Status "  Plugin:     $PluginSlug" -Color White
        Write-Status "  Version:    $LocalVersion" -Color White
        Write-Status "  Action:     $VersionAction" -Color White
        Write-Status "  Is Update:  $($response.is_update)" -Color White
        Write-Status "  Activated:  $($response.activated)" -Color White
        if ($response.activation_error) {
            Write-Status "  Activation Error: $($response.activation_error)" -Color Yellow
        }
        Write-Status ""

        if ($Quiet) {
            $result = @{
                success = $true
                plugin = $PluginSlug
                localVersion = $LocalVersion
                remoteVersion = $RemoteVersion
                action = $VersionAction
                activated = $response.activated
            }
            Write-Output ($result | ConvertTo-Json -Compress)
        }

    } catch {
        $errorMessage = $_.Exception.Message
        $errorBody = Get-ErrorResponseBody $_

        Write-Host ""
        Write-Host "  ⚠ Riseup Uploader API failed: $errorMessage" -ForegroundColor Yellow
        Write-ErrorBody $errorBody "Riseup API Error"
        Write-Host ""
        Write-Host "  Falling back to basic upload script..." -ForegroundColor Yellow
        Write-Host ""
    }
}

# ============================================================================
# FALLBACK: Use basic upload-plugin.ps1 if V2 API failed or was not found
# ============================================================================
if (-not $uploadSuccess) {
    $basicScript = Join-Path (Split-Path -Parent $MyInvocation.MyCommand.Path) "upload-plugin.ps1"

    if (-not (Test-Path $basicScript)) {
        Write-Host "Error: Basic upload script not found at: $basicScript" -ForegroundColor Red
        exit 1
    }

    Write-Status "      Using basic upload script (upload-plugin.ps1)..." -Color Yellow

    # Build a JSON config for the basic script
    $fallbackConfig = @{
        pluginFolderPath     = $PluginFolderPath
        wordPressSiteURL     = $WordPressSiteURL
        username             = $Username
        appPassword          = $AppPassword
        outputZipPath        = $OutputZipPath
        activateAfterInstall = $ActivateAfterInstall
        deleteZipAfterUpload = $DeleteZipAfterUpload
    } | ConvertTo-Json -Compress

    try {
        & $basicScript -JsonConfig $fallbackConfig
        if ($LASTEXITCODE -ne 0) {
            Write-Host "Error: Basic upload script failed with exit code $LASTEXITCODE" -ForegroundColor Red
            exit 1
        }
        $uploadSuccess = $true
    } catch {
        Write-Host "Error: Basic upload script failed: $_" -ForegroundColor Red
        exit 1
    }
}

# Cleanup ZIP
if ($DeleteZipAfterUpload -or $DeleteZip.IsPresent) {
    if (Test-Path $OutputZipPath) {
        Remove-Item $OutputZipPath -Force
        Write-Status "ZIP file deleted" -Color Gray
    }
}

Write-Status "Done!" -Color Cyan
Write-Status ""
