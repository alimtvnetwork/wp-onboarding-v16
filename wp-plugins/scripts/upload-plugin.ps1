# WordPress Plugin Uploader - Pure PowerShell
# Supports both config file and direct command-line parameters
# Uses Riseup Asia Uploader API endpoint for reliable uploads

param(
    [Parameter(Mandatory=$false)]
    [string]$ConfigPath = "",
    
    # Direct parameters (override config file if provided)
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
    [switch]$Force = $false,

    [Parameter(Mandatory=$false)]
    [switch]$DebugMode = $false,
    
    # JSON string with all config (alternative to individual params)
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

# Debug-only output
function Write-Debug-Log {
    param([string]$Message)
    if ($DebugMode) {
        Write-Host "      [DEBUG] $Message" -ForegroundColor Magenta
    }
}

# Invoke a web request with HTML challenge detection & retry
function Invoke-SafeRestRequest {
    param(
        [string]$Uri,
        [string]$Method = "Get",
        [hashtable]$Headers = @{},
        [string]$Body = "",
        [string]$ContentType = "",
        [int]$TimeoutSec = 30,
        [int]$MaxRetries = 2,
        [int]$RetryDelaySec = 5,
        [string]$Label = "Request"
    )

    # Ensure Accept header is set so WordPress returns JSON, not HTML
    if (-not $Headers.ContainsKey("Accept")) {
        $Headers["Accept"] = "application/json"
    }

    for ($attempt = 1; $attempt -le $MaxRetries; $attempt++) {
        Write-Debug-Log "$Label → Attempt $attempt/$MaxRetries"
        Write-Debug-Log "$Label → $Method $Uri"
        Write-Debug-Log "$Label → Headers: $($Headers.Keys -join ', ')"

        try {
            $reqParams = @{
                Uri             = $Uri
                Method          = $Method
                Headers         = $Headers
                TimeoutSec      = $TimeoutSec
                UseBasicParsing = $true
                ErrorAction     = "Stop"
            }
            if ($Body -ne "") {
                $reqParams.Body = $Body
                $reqParams.ContentType = $ContentType
            }

            $webResponse = Invoke-WebRequest @reqParams
            $statusCode = $webResponse.StatusCode
            $respContentType = $webResponse.Headers["Content-Type"]
            $rawBody = $webResponse.Content

            Write-Debug-Log "$Label → Status: $statusCode, Content-Type: $respContentType"
            Write-Debug-Log "$Label → Body (first 500): $($rawBody.Substring(0, [Math]::Min(500, $rawBody.Length)))"

            # Detect HTML challenge pages — only specific spinner/challenge pages
            if ($rawBody -match "One moment, please" -or $rawBody -match "Checking your browser") {
                Write-Status "      ⚠ Got HTML challenge page (security plugin/CDN)" -Color Yellow
                if ($attempt -lt $MaxRetries) {
                    Write-Status "        Waiting ${RetryDelaySec}s and retrying..." -Color Yellow
                    Start-Sleep -Seconds $RetryDelaySec
                    continue
                } else {
                    Write-Status "        All $MaxRetries attempts returned HTML challenge." -Color Red
                    return $null
                }
            }

            # HTML but NOT a challenge — log clearly
            if ($respContentType -and $respContentType -match "text/html") {
                Write-Status "      ⚠ Server returned HTML (not JSON) — Status: $statusCode" -Color Yellow
                Write-Debug-Log "$Label → HTML response (first 800):"
                Write-Debug-Log ($rawBody.Substring(0, [Math]::Min(800, $rawBody.Length)))
                return $null
            }

            try {
                $parsed = $rawBody | ConvertFrom-Json
                return $parsed
            } catch {
                Write-Status "      ⚠ Response is not valid JSON" -Color Yellow
                Write-Debug-Log "$Label → Raw (first 500): $($rawBody.Substring(0, [Math]::Min(500, $rawBody.Length)))"
                return $null
            }

        } catch {
            $errBody = Get-ErrorResponseBody $_
            Write-Debug-Log "$Label → Exception: $($_.Exception.Message)"

            if ($attempt -lt $MaxRetries) {
                Write-Status "      ⚠ $Label failed, retrying in ${RetryDelaySec}s..." -Color Yellow
                Start-Sleep -Seconds $RetryDelaySec
            } else {
                throw $_
            }
        }
    }
    return $null
}

# Strip HTML tags and decode entities to plain text
function ConvertFrom-Html {
    param([string]$Html)
    if ([string]::IsNullOrWhiteSpace($Html)) { return "" }
    # Remove style/script blocks
    $text = $Html -replace '<style[^>]*>[\s\S]*?</style>', ''
    $text = $text -replace '<script[^>]*>[\s\S]*?</script>', ''
    # Replace <br>, <p>, <div>, <li> with newlines
    $text = $text -replace '<br\s*/?>', "`n"
    $text = $text -replace '</p>', "`n"
    $text = $text -replace '</div>', "`n"
    $text = $text -replace '</li>', "`n"
    # Strip all remaining tags
    $text = $text -replace '<[^>]+>', ''
    # Decode common HTML entities
    $text = $text -replace '&amp;', '&'
    $text = $text -replace '&lt;', '<'
    $text = $text -replace '&gt;', '>'
    $text = $text -replace '&quot;', '"'
    $text = $text -replace '&#039;', "'"
    $text = $text -replace '&rsaquo;', '>'
    $text = $text -replace '&nbsp;', ' '
    # Collapse whitespace
    $text = ($text -split "`n" | ForEach-Object { $_.Trim() } | Where-Object { $_ -ne '' }) -join "`n"
    return $text.Trim()
}

# Detect if a string contains HTML
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
    Write-Status ""
    Write-Status "      ── $Label ──" -Color DarkGray
    # Truncate to 2000 chars for readability
    $preview = if ($Body.Length -gt 2000) { $Body.Substring(0, 2000) + "`n      ... (truncated, total $($Body.Length) chars)" } else { $Body }
    Write-Status "      $preview" -Color Gray
    Write-Status "      ────────────────" -Color DarkGray
}

# Track if any response had a server-side critical error
$script:serverCriticalError = $false
$script:criticalErrorDetails = @()

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

# Priority 1: JSON config string from command line
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
# Priority 2: Direct command-line parameters
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
# Priority 3: Config file
else {
    if ($ConfigPath -eq "") {
        $scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
        $ConfigPath = Join-Path $scriptDir "wp-plugin-config.json"
    }

    if (-not (Test-Path $ConfigPath)) {
        Write-Host ""
        Write-Host "WordPress Plugin Uploader" -ForegroundColor Cyan
        Write-Host "=========================" -ForegroundColor Cyan
        Write-Host ""
        Write-Host "Usage Options:" -ForegroundColor Yellow
        Write-Host ""
        Write-Host "  1. Command-line parameters:" -ForegroundColor White
        Write-Host "     .\upload-plugin.ps1 -PluginPath 'C:\path\to\plugin' -SiteUrl 'https://site.com' -User 'admin' -Password 'app-password' [-Activate] [-Slug 'my-plugin']" -ForegroundColor Gray
        Write-Host ""
        Write-Host "  2. Inline JSON:" -ForegroundColor White
        Write-Host '     .\upload-plugin.ps1 -JsonConfig ''{"pluginFolderPath":"C:\\path","wordPressSiteURL":"https://site.com","username":"admin","appPassword":"xxx"}''' -ForegroundColor Gray
        Write-Host ""
        Write-Host "  3. Config file:" -ForegroundColor White
        Write-Host "     .\upload-plugin.ps1 -ConfigPath 'path\to\config.json'" -ForegroundColor Gray
        Write-Host ""
        Write-Host "Config file structure:" -ForegroundColor Yellow
        Write-Host @"
{
  "pluginFolderPath": "C:\\path\\to\\your\\plugin",
  "wordPressSiteURL": "https://your-site.com",
  "username": "admin",
  "appPassword": "your-application-password",
  "pluginSlug": "my-plugin",
  "outputZipPath": "",
  "activateAfterInstall": true,
  "deleteZipAfterUpload": false
}
"@ -ForegroundColor Gray
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
Write-Status "========================================" -Color Cyan
Write-Status "  WordPress Plugin Uploader" -Color Cyan
Write-Status "  Using Riseup Asia Uploader API" -Color Cyan
Write-Status "========================================" -Color Cyan
Write-Status ""

# Step 1: Verify plugin folder exists
if (-not (Test-Path $PluginFolderPath)) {
    Write-Host "Error: Plugin folder not found at: $PluginFolderPath" -ForegroundColor Red
    exit 1
}

$folderName = Split-Path $PluginFolderPath -Leaf
if ($PluginSlug -eq "") { $PluginSlug = $folderName }

Write-Status "[1/6] Plugin Folder: $folderName" -Color Yellow
Write-Status "      Path: $PluginFolderPath" -Color Gray
Write-Status "      Slug: $PluginSlug" -Color Gray

# Read local version from plugin header or constants file
$LocalVersion = "unknown"
$constantsFile = Join-Path $PluginFolderPath "includes/constants.php"
if (Test-Path $constantsFile) {
    $verContent = Get-Content $constantsFile -Raw
    if ($verContent -match "RISEUP_VERSION.*?'([0-9]+\.[0-9]+\.[0-9]+)'") {
        $LocalVersion = $Matches[1]
    }
} else {
    $mainFile = Get-ChildItem $PluginFolderPath -Filter "*.php" | Where-Object {
        (Get-Content $_.FullName -Head 5) -match 'Plugin Name:'
    } | Select-Object -First 1
    if ($mainFile) {
        $verContent = Get-Content $mainFile.FullName -Raw
        if ($verContent -match "Version:\s*([0-9]+\.[0-9]+\.[0-9]+)") {
            $LocalVersion = $Matches[1]
        }
    }
}
Write-Status "      Version: $LocalVersion" -Color Gray

# Step 2: Create ZIP file (with cache deduplication)
$cacheDir = Join-Path $env:TEMP "RiseupUploader\$LocalVersion"
if (-not (Test-Path $cacheDir)) {
    New-Item -ItemType Directory -Path $cacheDir -Force | Out-Null
}

if ($OutputZipPath -eq "") {
    $OutputZipPath = Join-Path $cacheDir "$folderName-$(Get-Date -Format 'yyyyMMddHHmmss').zip"
}

# Remove existing ZIP if it exists at the output path
if (Test-Path $OutputZipPath) {
    Remove-Item $OutputZipPath -Force
}

Write-Status ""
Write-Status "[2/6] Creating ZIP file..." -Color Yellow

try {
    # Create a temp directory for proper ZIP structure
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
    
    # Cleanup temp directory
    Remove-Item $tempDir -Recurse -Force
    
    $zipSize = (Get-Item $OutputZipPath).Length
    $zipSizeKB = [math]::Round($zipSize / 1KB, 2)
    Write-Status "      ✓ ZIP created: $OutputZipPath" -Color Green
    Write-Status "      Size: $zipSizeKB KB" -Color Gray
    Write-Debug-Log "ZIP path: $OutputZipPath"
    Write-Debug-Log "ZIP size: $zipSizeKB KB"
    Write-Debug-Log "Cache dir: $cacheDir"
} catch {
    Write-Host "      Error creating ZIP file: $_" -ForegroundColor Red
    exit 1
}

# Step 2b: Hash deduplication — compare with cached zips
$newHash = (Get-FileHash $OutputZipPath -Algorithm SHA256).Hash
Write-Status "      Hash: $newHash" -Color Gray

# Get existing cached zips (sorted newest first), excluding the one we just created
$cachedZips = Get-ChildItem $cacheDir -Filter "*.zip" | Where-Object { $_.FullName -ne $OutputZipPath } | Sort-Object LastWriteTime -Descending

if ($cachedZips.Count -gt 0) {
    $latestCachedHash = (Get-FileHash $cachedZips[0].FullName -Algorithm SHA256).Hash
    Write-Status "      Cached hash: $latestCachedHash" -Color Gray

    if ($newHash -eq $latestCachedHash -and -not $Force) {
        Write-Status ""
        Write-Status "  ═══════════════════════════════════════════" -Color Cyan
        Write-Status "  ✓ ZIP hash matches cached version — SKIP UPLOAD" -Color Cyan
        Write-Status "    Version $LocalVersion is already deployed with" -Color Cyan
        Write-Status "    identical content." -Color Cyan
        Write-Status "  ═══════════════════════════════════════════" -Color Cyan
        Write-Status ""
        # Remove the duplicate zip we just created
        Remove-Item $OutputZipPath -Force
        Write-Status "Done! (no upload needed)" -Color Green
        exit 0
    } elseif ($newHash -eq $latestCachedHash -and $Force) {
        Write-Status "      ⚠ Hash matches cache but -Force specified, re-uploading..." -Color Yellow
    }
}

# Housekeeping: keep only the last 2 zips in this version's cache folder
$allCachedZips = Get-ChildItem $cacheDir -Filter "*.zip" | Sort-Object LastWriteTime -Descending
if ($allCachedZips.Count -gt 2) {
    $allCachedZips | Select-Object -Skip 2 | ForEach-Object {
        Remove-Item $_.FullName -Force
        Write-Status "      Pruned old cache: $($_.Name)" -Color Gray
    }
}

# Create Basic Auth header
$CleanAppPassword = $AppPassword -replace '\s', ''
$base64Auth = [Convert]::ToBase64String([Text.Encoding]::ASCII.GetBytes("${Username}:${CleanAppPassword}"))

# Pre-upload health check: verify REST API is reachable
Write-Status ""
Write-Status "[3/6] Pre-upload health check..." -Color Yellow
$healthUrl = "$WordPressSiteURL/wp-json/"
Write-Status "      ── Request ──" -Color DarkGray
Write-Status "      GET $healthUrl" -Color White
Write-Status "      Auth: Basic (user=$Username)" -Color Gray
Write-Status "      ────────────" -Color DarkGray
Write-Debug-Log "Health check URL: $healthUrl"

$restApiHealthy = $false
try {
    $healthResponse = Invoke-SafeRestRequest -Uri $healthUrl -Headers @{ "Authorization" = "Basic $base64Auth" } -TimeoutSec 15 -Label "Health Check" -MaxRetries 3 -RetryDelaySec 5

    if ($null -eq $healthResponse) {
        Write-Status "      ✗ REST API unreachable (HTML challenge or error)" -Color Red
        Write-Status "      ⚠ Continuing anyway..." -Color Yellow
    } elseif ($healthResponse.name -or $healthResponse.namespaces) {
        $restApiHealthy = $true
        $siteName = if ($healthResponse.name) { $healthResponse.name } else { "Unknown" }
        Write-Status "      ✓ REST API is reachable (Site: $siteName)" -Color Green
        if ($healthResponse.namespaces) {
            $hasRiseup = $healthResponse.namespaces -contains "riseup-asia-uploader/v1"
            if ($hasRiseup) {
                Write-Status "      ✓ riseup-asia-uploader/v1 namespace registered" -Color Green
            } else {
                Write-Status "      ⚠ riseup-asia-uploader/v1 namespace NOT found" -Color Yellow
                Write-Debug-Log "Available: $($healthResponse.namespaces -join ', ')"
            }
        }
    } else {
        Write-Status "      ⚠ REST API responded but format unexpected" -Color Yellow
    }
} catch {
    $healthErr = Get-ErrorResponseBody $_
    Write-Status "      ✗ REST API health check failed: $($_.Exception.Message)" -Color Red
    Write-Debug-Log "Health error: $healthErr"
    Write-Status "      ⚠ Continuing anyway..." -Color Yellow
}

# Step 4: Upload plugin (try Riseup Asia Uploader first, then WP Core)
Write-Status ""
Write-Status "[4/6] Uploading plugin to WordPress..." -Color Yellow
Write-Status "      Site: $WordPressSiteURL" -Color Gray
Write-Status "      User: $Username" -Color Gray

$uploadSuccess = $false

# --- Attempt 1: Riseup Asia Uploader (direct upload, no status check) ---
$apiNamespaces = @(
    @{ name = "riseup-asia-uploader/v1"; display = "Riseup Asia Uploader" },
    @{ name = "riseup-uploader/v1"; display = "Riseup Uploader (Legacy)" }
)

foreach ($ns in $apiNamespaces) {
    if ($uploadSuccess) { break }

    $uploadUrl = "$WordPressSiteURL/wp-json/$($ns.name)/upload"
    Write-Status "      Trying $($ns.display)..." -Color Gray
    Write-Status "      ── Request ──" -Color DarkGray
    Write-Status "      POST $uploadUrl" -Color White
    Write-Status "      Auth: Basic (user=$Username)" -Color Gray
    Write-Status "      Content-Type: application/json" -Color Gray

    try {
        $fileBytes = [System.IO.File]::ReadAllBytes($OutputZipPath)
        $base64Data = [Convert]::ToBase64String($fileBytes)

        $machineName = $env:COMPUTERNAME
        $uploadBody = @{
            plugin_zip    = $base64Data
            slug          = $PluginSlug
            activate      = $ActivateAfterInstall
            upload_source = "upload_script"
        } | ConvertTo-Json

        $bodySizeKB = [math]::Round($uploadBody.Length / 1KB, 1)
        Write-Status "      Body: {slug: `"$PluginSlug`", activate: $ActivateAfterInstall, upload_source: `"upload_script`", plugin_zip: `"<base64 $bodySizeKB KB>`"}" -Color Gray
        Write-Status "      Machine: $machineName" -Color Gray
        Write-Status "      ────────────" -Color DarkGray

        $uploadHeaders = @{
            "Authorization"            = "Basic $base64Auth"
            "Content-Type"             = "application/json"
            "X-Riseup-Source-Machine"  = $machineName
        }

        # Health check and upload endpoints
        Write-Debug-Log "Upload endpoint: $uploadUrl"
        Write-Debug-Log "ZIP file: $OutputZipPath"
        Write-Debug-Log "ZIP size: $bodySizeKB KB"

        $uploadHeaders = @{
            "Authorization"            = "Basic $base64Auth"
            "X-Riseup-Source-Machine"  = $machineName
        }

        $response = Invoke-SafeRestRequest -Uri $uploadUrl -Method "Post" -Headers $uploadHeaders -Body $uploadBody -ContentType "application/json" -TimeoutSec 300 -Label "Upload ($($ns.display))" -MaxRetries 3 -RetryDelaySec 8

        if ($null -eq $response) {
            Write-Status "      ✗ $($ns.display) returned non-JSON response" -Color Yellow
            continue
        }

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

        Write-Status "      ✓ Uploaded via $($ns.display)!" -Color Green
        Write-Status ""
        Write-Status "[5/6] Installation Complete!" -Color Yellow
        Write-Status ""
        Write-Status "========================================" -Color Green
        Write-Status "  SUCCESS! Plugin Deployed!" -Color Green
        Write-Status "========================================" -Color Green
        Write-Status ""
        Write-Status "Plugin Details:" -Color Cyan
        # Try multiple possible field names
        $pSlug = if ($resultData.plugin_slug) { $resultData.plugin_slug } elseif ($resultData.slug) { $resultData.slug } elseif ($resultData.pluginSlug) { $resultData.pluginSlug } else { $PluginSlug }
        $pUpdate = if ($null -ne $resultData.is_update) { $resultData.is_update } elseif ($null -ne $resultData.isUpdate) { $resultData.isUpdate } else { "N/A" }
        $pActivated = if ($null -ne $resultData.activated) { $resultData.activated } elseif ($null -ne $resultData.active) { $resultData.active } else { "N/A" }
        Write-Status "  - Plugin Slug: $pSlug" -Color White
        Write-Status "  - Is Update:   $pUpdate" -Color White
        Write-Status "  - Activated:   $pActivated" -Color White
        if ($resultData.activation_error) {
            Write-Status "  - Activation Error: $($resultData.activation_error)" -Color Yellow
        }
        Write-Status ""

        if ($Quiet) {
            $result = @{
                success = $true
                plugin = $resultData.plugin_slug
                activated = $resultData.activated
                is_update = $resultData.is_update
                message = "Plugin installed successfully"
            }
            Write-Output ($result | ConvertTo-Json -Compress)
        }

    } catch {
        $errMsg = $_.Exception.Message
        $errBody = Get-ErrorResponseBody $_
        Test-ServerCriticalError $errBody | Out-Null
        Write-Status "      ✗ $($ns.display) failed: $errMsg" -Color Yellow
        Write-Debug-Log "Error body: $errBody"
        Write-ErrorBody $errBody "$($ns.display) Error"
    }
}

# --- Attempt 2: Standard WordPress REST API (fallback) ---
if (-not $uploadSuccess) {
    Write-Status ""
    Write-Status "      Falling back to WordPress Core REST API..." -Color Yellow

    $fileName = Split-Path $OutputZipPath -Leaf
    $uploadUrl = "$WordPressSiteURL/wp-json/wp/v2/plugins"

    Write-Status "      ── Request ──" -Color DarkGray
    Write-Status "      POST $uploadUrl" -Color White
    Write-Status "      Auth: Basic (user=$Username)" -Color Gray
    Write-Status "      Content-Type: multipart/form-data" -Color Gray
    Write-Status "      File: $fileName ($([math]::Round((Get-Item $OutputZipPath).Length / 1KB, 1)) KB)" -Color Gray
    Write-Status "      ────────────" -Color DarkGray

    try {
        $fileBytes = [System.IO.File]::ReadAllBytes($OutputZipPath)

        $boundary = [System.Guid]::NewGuid().ToString()
        $LF = "`r`n"

        $bodyStart = "--$boundary$LF" +
                     "Content-Disposition: form-data; name=`"file`"; filename=`"$fileName`"$LF" +
                     "Content-Type: application/zip$LF$LF"
        $bodyEnd = "$LF--$boundary--$LF"

        $bodyStartBytes = [System.Text.Encoding]::UTF8.GetBytes($bodyStart)
        $bodyEndBytes = [System.Text.Encoding]::UTF8.GetBytes($bodyEnd)

        $body = New-Object byte[] ($bodyStartBytes.Length + $fileBytes.Length + $bodyEndBytes.Length)
        [System.Buffer]::BlockCopy($bodyStartBytes, 0, $body, 0, $bodyStartBytes.Length)
        [System.Buffer]::BlockCopy($fileBytes, 0, $body, $bodyStartBytes.Length, $fileBytes.Length)
        [System.Buffer]::BlockCopy($bodyEndBytes, 0, $body, $bodyStartBytes.Length + $fileBytes.Length, $bodyEndBytes.Length)

        $uploadHeaders = @{
            "Authorization" = "Basic $base64Auth"
            "Content-Type" = "multipart/form-data; boundary=$boundary"
        }

        $response = Invoke-RestMethod -Uri $uploadUrl -Method Post -Headers $uploadHeaders -Body $body
        $uploadSuccess = $true

        $pluginSlugResult = $response.plugin
        Write-Status "      ✓ Uploaded via WordPress Core API!" -Color Green

        # Activate if requested
        if ($ActivateAfterInstall) {
            Write-Status ""
            Write-Status "[5/6] Activating plugin..." -Color Yellow

            $activateUrl = "$WordPressSiteURL/wp-json/wp/v2/plugins/$pluginSlugResult"
            $activateBody = @{ status = "active" } | ConvertTo-Json
            $activateHeaders = @{
                "Authorization" = "Basic $base64Auth"
                "Content-Type" = "application/json"
            }

            try {
                Invoke-RestMethod -Uri $activateUrl -Method Post -Headers $activateHeaders -Body $activateBody | Out-Null
                Write-Status "      ✓ Plugin activated!" -Color Green
            } catch {
                Write-Status "      ⚠ Could not activate plugin automatically" -Color Yellow
            }
        }

        Write-Status ""
        Write-Status "========================================" -Color Green
        Write-Status "  SUCCESS! Plugin Deployed!" -Color Green
        Write-Status "========================================" -Color Green
        Write-Status ""
        Write-Status "Plugin Details:" -Color Cyan
        Write-Status "  - Name: $($response.name)" -Color White
        Write-Status "  - Version: $($response.version)" -Color White
        Write-Status "  - Status: $($response.status)" -Color White
        Write-Status ""

        if ($Quiet) {
            $result = @{
                success = $true
                plugin = $pluginSlugResult
                activated = ($response.status -eq "active")
                message = "Plugin installed via WP Core"
            }
            Write-Output ($result | ConvertTo-Json -Compress)
        }

    } catch {
        $statusCode = $null
        $errorMessage = $_.Exception.Message
        if ($_.Exception.Response) {
            $statusCode = [int]$_.Exception.Response.StatusCode
        }
        $errorBody = Get-ErrorResponseBody $_
        Test-ServerCriticalError $errorBody | Out-Null

        Write-Host ""
        Write-Host "All upload methods failed!" -ForegroundColor Red
        if ($statusCode) { Write-Host "Status: $statusCode" -ForegroundColor Red }
        Write-ErrorBody $errorBody "WP Core Error"
        if ($errorBody -eq "") {
            Write-Host "Error: $errorMessage" -ForegroundColor Red
        }

        Write-ServerErrorBanner
        Write-Host ""

        if ($Quiet) {
            $result = @{ success = $false; error = $errorMessage; statusCode = $statusCode }
            Write-Output ($result | ConvertTo-Json -Compress)
        }
        exit 1
    }
}

# Cleanup ZIP if configured
if ($DeleteZipAfterUpload -or $DeleteZip.IsPresent) {
    if (Test-Path $OutputZipPath) {
        Remove-Item $OutputZipPath -Force
        Write-Status "ZIP file deleted" -Color Gray
    }
}

Write-Status "Done!" -Color Cyan
Write-Status ""
