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

# Strip HTML tags and decode entities to plain text
function ConvertFrom-Html {
    param([string]$Html)
    if ([string]::IsNullOrWhiteSpace($Html)) { return "" }
    $text = $Html -replace '<style[^>]*>[\s\S]*?</style>', ''
    $text = $text -replace '<script[^>]*>[\s\S]*?</script>', ''
    $text = $text -replace '<br\s*/?>', "`n"
    $text = $text -replace '</p>', "`n"
    $text = $text -replace '</div>', "`n"
    $text = $text -replace '</li>', "`n"
    $text = $text -replace '<[^>]+>', ''
    $text = $text -replace '&amp;', '&'
    $text = $text -replace '&lt;', '<'
    $text = $text -replace '&gt;', '>'
    $text = $text -replace '&quot;', '"'
    $text = $text -replace '&#039;', "'"
    $text = $text -replace '&rsaquo;', '>'
    $text = $text -replace '&nbsp;', ' '
    $text = ($text -split "`n" | ForEach-Object { $_.Trim() } | Where-Object { $_ -ne '' }) -join "`n"
    return $text.Trim()
}

function Test-IsHtml {
    param([string]$Text)
    return ($Text -match '<[a-zA-Z][^>]*>' -or $Text.Trim().StartsWith("<!"))
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

# Print error response body as-is (raw, no HTML stripping)
function Write-ErrorBody {
    param([string]$Body, [string]$Label = "Response Body")
    if ($Body -eq "") { return }
    Write-Host ""
    Write-Host "  ── $Label ──" -ForegroundColor DarkGray
    # Truncate to 2000 chars for readability
    $preview = if ($Body.Length -gt 2000) { $Body.Substring(0, 2000) + "`n  ... (truncated, total $($Body.Length) chars)" } else { $Body }
    Write-Host "  $preview" -ForegroundColor Gray
    Write-Host "  ────────────────────" -ForegroundColor DarkGray
}

$script:serverCriticalError = $false

function Test-ServerCriticalError {
    param([string]$Body)
    if ($Body -match "critical error on this website" -or $Body -match "internal_server_error") {
        $script:serverCriticalError = $true
        return $true
    }
    return $false
}

function Write-ServerErrorBanner {
    if (-not $script:serverCriticalError) { return }
    Write-Host ""
    Write-Host "  ╔══════════════════════════════════════════════════════════╗" -ForegroundColor Red
    Write-Host "  ║          ⚠  SERVER-SIDE CRITICAL ERROR DETECTED  ⚠     ║" -ForegroundColor Red
    Write-Host "  ╠══════════════════════════════════════════════════════════╣" -ForegroundColor Red
    Write-Host "  ║  WordPress is returning a fatal PHP error.             ║" -ForegroundColor Yellow
    Write-Host "  ║  This is NOT a script issue — the server is crashing.  ║" -ForegroundColor Yellow
    Write-Host "  ║                                                        ║" -ForegroundColor Yellow
    Write-Host "  ║  Troubleshooting steps:                                ║" -ForegroundColor Yellow
    Write-Host "  ║  1. Check wp-content/debug.log on the server           ║" -ForegroundColor White
    Write-Host "  ║  2. Check wp-content/uploads/riseup-asia-uploader/     ║" -ForegroundColor White
    Write-Host "  ║     fatal-errors.log                                   ║" -ForegroundColor White
    Write-Host "  ║  3. Verify PHP version (requires 7.4+)                 ║" -ForegroundColor White
    Write-Host "  ║  4. Check server error logs (Apache/Nginx)             ║" -ForegroundColor White
    Write-Host "  ║  5. Ensure WP_DEBUG is enabled in wp-config.php        ║" -ForegroundColor White
    Write-Host "  ╚══════════════════════════════════════════════════════════╝" -ForegroundColor Red
    Write-Host ""
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
Write-Status "  Git Pull → Version Compare → Publish → Verify" -Color Cyan
Write-Status "===============================================" -Color Cyan
Write-Status ""

$folderName = Split-Path $PluginFolderPath -Leaf
if ($PluginSlug -eq "") { $PluginSlug = $folderName }

# ============================================================================
# STEP 1: GIT PULL
# ============================================================================
if (-not $SkipGitPull) {
    Write-Status "[1/7] Git pull (current branch)..." -Color Yellow

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
    Write-Status "[1/7] Skipping git pull (-SkipGitPull)" -Color Gray
}
Write-Status ""

# ============================================================================
# STEP 2: READ LOCAL VERSION
# ============================================================================
Write-Status "[2/7] Reading local plugin version..." -Color Yellow

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
Write-Status "[3/7] Checking remote version on $WordPressSiteURL..." -Color Yellow

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

        # Unwrap Universal Response Envelope: data is in Results[0]
        $statusData = $statusResponse
        if ($statusResponse.Results -and $statusResponse.Results.Count -gt 0) {
            $statusData = $statusResponse.Results[0]
        }

        # Support both PascalCase (envelope) and lowercase (legacy) field names
        $detectedVersion = if ($statusData.Version) { $statusData.Version } elseif ($statusData.version) { $statusData.version } else { $null }
        $isSuccess = ($statusResponse.Status -and $statusResponse.Status.IsSuccess -eq $true) -or ($statusResponse.success -eq $true) -or $detectedVersion

        if ($isSuccess -or $detectedVersion) {
            $RemoteVersion = $detectedVersion
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
Write-Status "[4/7] Version comparison..." -Color Yellow

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
        Write-Status "      ▲ UPGRADE: $LocalVersion → $RemoteVersion" -Color Green
    } elseif ($comparison -lt 0) {
        $VersionAction = "downgrade"
        Write-Status "      ▼ DOWNGRADE: $LocalVersion → $RemoteVersion" -Color Red
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
# STEP 5: CREATE ZIP (with cache deduplication)
# ============================================================================
Write-Status "[5/7] Creating ZIP file..." -Color Yellow

if (-not (Test-Path $PluginFolderPath)) {
    Write-Host "Error: Plugin folder not found at: $PluginFolderPath" -ForegroundColor Red
    exit 1
}

# Cache directory: %TEMP%\RiseupUploader\{version}\
$cacheDir = Join-Path $env:TEMP "RiseupUploader\$LocalVersion"
if (-not (Test-Path $cacheDir)) {
    New-Item -ItemType Directory -Path $cacheDir -Force | Out-Null
}

if ($OutputZipPath -eq "") {
    $OutputZipPath = Join-Path $cacheDir "$folderName-$(Get-Date -Format 'yyyyMMddHHmmss').zip"
}

if (Test-Path $OutputZipPath) {
    Remove-Item $OutputZipPath -Force
}

try {
    $tempDir = Join-Path $env:TEMP "wp-plugin-upload-$(Get-Random)"
    $pluginTempDir = Join-Path $tempDir $folderName
    New-Item -ItemType Directory -Path $pluginTempDir -Force | Out-Null
    Copy-Item -Path "$PluginFolderPath\*" -Destination $pluginTempDir -Recurse
    # Use System.IO.Compression for SmallestSize (better than Compress-Archive Optimal)
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    [System.IO.Compression.ZipFile]::CreateFromDirectory(
        $pluginTempDir,
        $OutputZipPath,
        [System.IO.Compression.CompressionLevel]::SmallestSize,
        $true  # includeBaseDirectory — keeps the slug folder as root
    )
    Remove-Item $tempDir -Recurse -Force

    $zipSize = (Get-Item $OutputZipPath).Length
    $zipSizeKB = [math]::Round($zipSize / 1KB, 2)
    Write-Status "      ✓ ZIP created: $zipSizeKB KB" -Color Green
} catch {
    Write-Host "      Error creating ZIP: $_" -ForegroundColor Red
    exit 1
}

# Hash deduplication — compare with cached zips
$newHash = (Get-FileHash $OutputZipPath -Algorithm SHA256).Hash
Write-Status "      Hash: $newHash" -Color Gray

$cachedZips = Get-ChildItem $cacheDir -Filter "*.zip" | Where-Object { $_.FullName -ne $OutputZipPath } | Sort-Object LastWriteTime -Descending

if ($cachedZips.Count -gt 0) {
    $latestCachedHash = (Get-FileHash $cachedZips[0].FullName -Algorithm SHA256).Hash
    Write-Status "      Cached hash: $latestCachedHash" -Color Gray

    if ($newHash -eq $latestCachedHash) {
        Write-Status ""
        Write-Status "  ═══════════════════════════════════════════" -Color Cyan
        Write-Status "  ✓ ZIP hash matches cached version — SKIP UPLOAD" -Color Cyan
        Write-Status "    Version $LocalVersion ($VersionAction) is already" -Color Cyan
        Write-Status "    deployed with identical content." -Color Cyan
        Write-Status "  ═══════════════════════════════════════════" -Color Cyan
        Write-Status ""
        # Remove the duplicate zip we just created
        Remove-Item $OutputZipPath -Force
        Write-Status "Done! (no upload needed)" -Color Green
        exit 0
    }
}

# Housekeeping: keep only the last 2 zips per version
$allCachedZips = Get-ChildItem $cacheDir -Filter "*.zip" | Sort-Object LastWriteTime -Descending
if ($allCachedZips.Count -gt 2) {
    $allCachedZips | Select-Object -Skip 2 | ForEach-Object {
        Remove-Item $_.FullName -Force
        Write-Status "      Pruned old cache: $($_.Name)" -Color Gray
    }
}

Write-Status ""

# ============================================================================
# STEP 6: REST API HEALTH CHECK
# ============================================================================
Write-Status "[6/7] REST API health check..." -Color Yellow
$healthUrl = "$WordPressSiteURL/wp-json/"
Write-Status "      ── Request ──" -Color DarkGray
Write-Status "      GET $healthUrl" -Color White
Write-Status "      Auth: Basic (user=$Username)" -Color Gray
Write-Status "      ────────────" -Color DarkGray

$restApiHealthy = $false
try {
    $healthResponse = Invoke-RestMethod -Uri $healthUrl -Method Get -Headers @{ "Authorization" = "Basic $base64Auth" } -TimeoutSec 15 -ErrorAction Stop
    
    # Always dump raw response
    try {
        $rawHealth = ($healthResponse | ConvertTo-Json -Depth 3 -Compress)
        if ($rawHealth.Length -gt 1000) { $rawHealth = $rawHealth.Substring(0, 1000) + "..." }
        Write-Status "      Raw response: $rawHealth" -Color DarkGray
    } catch {
        Write-Status "      Raw response: $healthResponse" -Color DarkGray
    }

    if ($healthResponse.name -or $healthResponse.namespaces) {
        $restApiHealthy = $true
        $siteName = if ($healthResponse.name) { $healthResponse.name } else { "Unknown" }
        Write-Status "      ✓ REST API is reachable (Site: $siteName)" -Color Green
        if ($healthResponse.namespaces) {
            $hasRiseup = $healthResponse.namespaces -contains "riseup-asia-uploader/v1"
            if ($hasRiseup) {
                Write-Status "      ✓ riseup-asia-uploader/v1 namespace registered" -Color Green
            } else {
                Write-Status "      ⚠ riseup-asia-uploader/v1 namespace NOT found" -Color Yellow
                Write-Status "        Available: $($healthResponse.namespaces -join ', ')" -Color Gray
            }
        }
    } else {
        Write-Status "      ⚠ REST API responded but format unexpected" -Color Yellow
    }
} catch {
    $healthErr = Get-ErrorResponseBody $_
    Write-Status "      ✗ REST API health check failed: $($_.Exception.Message)" -Color Red
    if ($healthErr -ne "") {
        Write-Status "      Raw error response: $healthErr" -Color Gray
    }
    Write-Status "      ⚠ Continuing anyway..." -Color Yellow
}

Write-Status ""

# ============================================================================
# STEP 7: UPLOAD & PUBLISH
# ============================================================================
Write-Status "[7/7] Publishing to WordPress..." -Color Yellow
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

        $machineName = $env:COMPUTERNAME
        $uploadBody = @{
            plugin_zip     = $base64Data
            slug           = $PluginSlug
            activate       = $ActivateAfterInstall
            upload_source  = "upload_script"
            plugin_version = $LocalVersion
            machine_name   = $machineName
        } | ConvertTo-Json

        $bodySizeKB = [math]::Round($uploadBody.Length / 1KB, 1)
        Write-Status "      Body: {slug: `"$PluginSlug`", activate: $ActivateAfterInstall, upload_source: `"upload_script`", plugin_version: `"$LocalVersion`", machine_name: `"$machineName`", plugin_zip: `"<base64 $bodySizeKB KB>`"}" -Color Gray
        Write-Status "      Machine: $machineName" -Color Gray
        Write-Status "      ────────────" -Color DarkGray

        $uploadHeaders = @{
            "Authorization"              = "Basic $base64Auth"
            "Content-Type"               = "application/json"
            "X-Riseup-Source-Machine"    = $machineName
            "X-Riseup-Plugin-Version"    = $LocalVersion
        }

        Write-Status "      Uploading via Riseup Asia Uploader..." -Color Gray
        $response = Invoke-RestMethod -Uri $uploadUrl -Method Post -Headers $uploadHeaders -Body $uploadBody -TimeoutSec 300
        $uploadSuccess = $true

        # Unwrap Universal Response Envelope: data is in Results[0]
        $resultData = $response
        if ($response.Results -and $response.Results.Count -gt 0) {
            $resultData = $response.Results[0]
        }

        # Debug: dump full raw response JSON
        try {
            $rawJson = ($response | ConvertTo-Json -Depth 5 -Compress)
            if ($rawJson.Length -gt 1000) { $rawJson = $rawJson.Substring(0, 1000) + "..." }
            Write-Status "      Raw response: $rawJson" -Color DarkGray
        } catch {
            Write-Status "      Raw response: $response" -Color DarkGray
        }
        Write-Status "      Response keys: $( ($resultData | Get-Member -MemberType NoteProperty | Select-Object -ExpandProperty Name) -join ', ' )" -Color Gray

        Write-Status ""
        Write-Status "===============================================" -Color Green
        Write-Status "  ✓ PUBLISH COMPLETE!" -Color Green
        Write-Status "===============================================" -Color Green
        Write-Status ""
        # Try multiple possible field names for robustness
        $pSlug = if ($resultData.plugin_slug) { $resultData.plugin_slug } elseif ($resultData.slug) { $resultData.slug } elseif ($resultData.pluginSlug) { $resultData.pluginSlug } else { $PluginSlug }
        $pUpdate = if ($null -ne $resultData.is_update) { $resultData.is_update } elseif ($null -ne $resultData.isUpdate) { $resultData.isUpdate } else { "N/A" }
        $pActivated = if ($null -ne $resultData.activated) { $resultData.activated } elseif ($null -ne $resultData.active) { $resultData.active } else { "N/A" }
        $responseVersion = if ($resultData.plugin_version) { $resultData.plugin_version } elseif ($resultData.version) { $resultData.version } else { "unknown" }
        Write-Status "  Plugin:     $pSlug" -Color White
        Write-Status "  Version:    $LocalVersion (sent)" -Color White
        Write-Status "  Resp. Ver:  $responseVersion (server response)" -Color $(if ($responseVersion -eq $LocalVersion) { "Green" } else { "Yellow" })
        Write-Status "  Action:     $VersionAction" -Color White
        Write-Status "  Is Update:  $pUpdate" -Color White
        Write-Status "  Activated:  $pActivated" -Color White
        if ($resultData.activation_error) {
            Write-Status "  Activation Error: $($resultData.activation_error)" -Color Yellow
        }

        # =================================================================
        # POST-UPLOAD VERIFICATION: Call /status to confirm actual version
        # This is critical for self-updates where the response version
        # comes from the OLD code that processed the request.
        # =================================================================
        Write-Status ""
        Write-Status "[8/8] Post-upload version verification..." -Color Yellow
        Start-Sleep -Seconds 2  # Brief pause for plugin activation to settle
        
        $verifyUrl = "$WordPressSiteURL/wp-json/$activeNamespace/status"
        Write-Status "      GET $verifyUrl" -Color Gray
        try {
            $verifyResponse = Invoke-RestMethod -Uri $verifyUrl -Method Get -Headers $headers -TimeoutSec 15 -ErrorAction Stop
            
            $verifyData = $verifyResponse
            if ($verifyResponse.Results -and $verifyResponse.Results.Count -gt 0) {
                $verifyData = $verifyResponse.Results[0]
            }
            
            $deployedVersion = if ($verifyData.Version) { $verifyData.Version } elseif ($verifyData.version) { $verifyData.version } else { "unknown" }
            
            if ($deployedVersion -eq $LocalVersion) {
                Write-Status "      ✓ VERIFIED: Server is running v$deployedVersion" -Color Green
            } elseif ($deployedVersion -eq "unknown") {
                Write-Status "      ⚠ Could not determine deployed version from /status" -Color Yellow
            } else {
                Write-Status ""
                Write-Status "  ╔══════════════════════════════════════════════════════════╗" -ForegroundColor Red
                Write-Status "  ║          ⚠  VERSION MISMATCH DETECTED  ⚠               ║" -ForegroundColor Red
                Write-Status "  ╠══════════════════════════════════════════════════════════╣" -ForegroundColor Red
                Write-Status "  ║  Uploaded:  v$LocalVersion" -ForegroundColor White -NoNewline
                Write-Status (" " * [Math]::Max(0, 42 - $LocalVersion.Length)) -ForegroundColor Red -NoNewline
                Write-Status "║" -ForegroundColor Red
                Write-Status "  ║  Deployed:  v$deployedVersion" -ForegroundColor Yellow -NoNewline
                Write-Status (" " * [Math]::Max(0, 42 - $deployedVersion.Length)) -ForegroundColor Red -NoNewline
                Write-Status "║" -ForegroundColor Red
                Write-Status "  ║                                                        ║" -ForegroundColor Red
                Write-Status "  ║  The server reports a different version than expected.  ║" -ForegroundColor Yellow
                Write-Status "  ║  This can happen on the FIRST self-update because the   ║" -ForegroundColor Yellow
                Write-Status "  ║  OLD plugin code generated the upload response.         ║" -ForegroundColor Yellow
                Write-Status "  ║                                                        ║" -ForegroundColor Yellow
                Write-Status "  ║  If this is the first deploy after a large version gap, ║" -ForegroundColor White
                Write-Status "  ║  run the upload AGAIN — the new code will now handle    ║" -ForegroundColor White
                Write-Status "  ║  it correctly.                                          ║" -ForegroundColor White
                Write-Status "  ╚══════════════════════════════════════════════════════════╝" -ForegroundColor Red
            }
        } catch {
            Write-Status "      ⚠ Verification call failed: $($_.Exception.Message)" -Color Yellow
            Write-Status "      The upload likely succeeded — check the WordPress admin." -Color Gray
        }
        
        Write-Status ""

        if ($Quiet) {
            $result = @{
                success = $true
                plugin = $PluginSlug
                localVersion = $LocalVersion
                remoteVersion = $RemoteVersion
                deployedVersion = $deployedVersion
                action = $VersionAction
                activated = $resultData.activated
            }
            Write-Output ($result | ConvertTo-Json -Compress)
        }

    } catch {
        $errorMessage = $_.Exception.Message
        $errorBody = Get-ErrorResponseBody $_
        Test-ServerCriticalError $errorBody | Out-Null

        Write-Host ""
        Write-Host "  ⚠ Riseup Uploader API failed: $errorMessage" -ForegroundColor Yellow
        Write-Host "  Status code: $($_.Exception.Response.StatusCode.value__)" -ForegroundColor Gray
        if ($errorBody -ne "") {
            $previewBody = if ($errorBody.Length -gt 500) { $errorBody.Substring(0, 500) + "..." } else { $errorBody }
            Write-Host "  Error body: $previewBody" -ForegroundColor Gray
        }
        Write-ErrorBody $errorBody "Riseup API Error"
        Write-ServerErrorBanner
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
