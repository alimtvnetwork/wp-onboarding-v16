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
    
    # JSON string with all config (alternative to individual params)
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
    # PS 7+ stores parsed error body here
    if ($ErrorRecord.ErrorDetails -and $ErrorRecord.ErrorDetails.Message) {
        return $ErrorRecord.ErrorDetails.Message
    }
    # PS 5.1 / WebException path
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

# Pretty-print an error response body (JSON or raw)
function Write-ErrorBody {
    param([string]$Body, [string]$Label = "Response Body")
    if ($Body -eq "") { return }
    Write-Status ""
    Write-Status "      ── $Label ──" -Color DarkGray
    try {
        $errJson = $Body | ConvertFrom-Json
        if ($errJson.message) {
            Write-Status "      Message: $($errJson.message)" -Color Red
        }
        if ($errJson.error -and $errJson.error.message) {
            Write-Status "      Error:   $($errJson.error.message)" -Color Red
        }
        if ($errJson.code) {
            Write-Status "      Code:    $($errJson.code)" -Color Red
        }
        if ($errJson.data -and $errJson.data.status) {
            Write-Status "      Status:  $($errJson.data.status)" -Color Red
        }
        # Stack trace (string)
        if ($errJson.stackTrace) {
            Write-Status "      Stack Trace:" -Color Yellow
            Write-Status $errJson.stackTrace -Color Gray
        }
        # Stack trace frames (array)
        if ($errJson.stackTraceFrames) {
            Write-Status "      Stack Frames:" -Color Yellow
            foreach ($frame in $errJson.stackTraceFrames) {
                $loc = "        $($frame.file):$($frame.line)"
                if ($frame.class) { $loc += " -> $($frame.class)::$($frame.function)" }
                elseif ($frame.function) { $loc += " -> $($frame.function)" }
                Write-Status $loc -Color Gray
            }
        }
        # Nested error.details
        if ($errJson.error -and $errJson.error.details) {
            $d = $errJson.error.details
            if ($d.stackTrace) {
                Write-Status "      Stack Trace:" -Color Yellow
                Write-Status $d.stackTrace -Color Gray
            }
            if ($d.stackTraceFrames) {
                Write-Status "      Stack Frames:" -Color Yellow
                foreach ($frame in $d.stackTraceFrames) {
                    $loc = "        $($frame.file):$($frame.line)"
                    if ($frame.class) { $loc += " -> $($frame.class)::$($frame.function)" }
                    elseif ($frame.function) { $loc += " -> $($frame.function)" }
                    Write-Status $loc -Color Gray
                }
            }
        }
        # If nothing matched, dump whole thing
        $hasKnown = $errJson.message -or $errJson.error -or $errJson.stackTrace -or $errJson.code
        if (-not $hasKnown) {
            Write-Status $Body -Color Gray
        }
    } catch {
        # Not JSON, dump raw
        Write-Status $Body -Color Gray
    }
    Write-Status "      ────────────────" -Color DarkGray
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

Write-Status "[1/5] Plugin Folder: $folderName" -Color Yellow
Write-Status "      Path: $PluginFolderPath" -Color Gray
Write-Status "      Slug: $PluginSlug" -Color Gray

# Step 2: Create ZIP file
if ($OutputZipPath -eq "") {
    $OutputZipPath = Join-Path $env:TEMP "$folderName-$(Get-Date -Format 'yyyyMMddHHmmss').zip"
}

# Remove existing ZIP if it exists
if (Test-Path $OutputZipPath) {
    Write-Status "      Removing existing ZIP file..." -Color Gray
    Remove-Item $OutputZipPath -Force
}

Write-Status ""
Write-Status "[2/5] Creating ZIP file..." -Color Yellow

try {
    # Create a temp directory for proper ZIP structure
    $tempDir = Join-Path $env:TEMP "wp-plugin-upload-$(Get-Random)"
    $pluginTempDir = Join-Path $tempDir $folderName
    
    New-Item -ItemType Directory -Path $pluginTempDir -Force | Out-Null
    Copy-Item -Path "$PluginFolderPath\*" -Destination $pluginTempDir -Recurse
    
    Compress-Archive -Path $pluginTempDir -DestinationPath $OutputZipPath -CompressionLevel Optimal
    
    # Cleanup temp directory
    Remove-Item $tempDir -Recurse -Force
    
    $zipSize = (Get-Item $OutputZipPath).Length
    $zipSizeKB = [math]::Round($zipSize / 1KB, 2)
    Write-Status "      Success! ZIP created: $OutputZipPath" -Color Green
    Write-Status "      Size: $zipSizeKB KB" -Color Gray
} catch {
    Write-Host "      Error creating ZIP file: $_" -ForegroundColor Red
    exit 1
}

# Create Basic Auth header
$CleanAppPassword = $AppPassword -replace '\s', ''
$base64Auth = [Convert]::ToBase64String([Text.Encoding]::ASCII.GetBytes("${Username}:${CleanAppPassword}"))

# Step 3: Upload plugin (try Riseup Asia Uploader first, then WP Core)
Write-Status ""
Write-Status "[3/5] Uploading plugin to WordPress..." -Color Yellow
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

    try {
        $fileBytes = [System.IO.File]::ReadAllBytes($OutputZipPath)
        $base64Data = [Convert]::ToBase64String($fileBytes)

        $uploadBody = @{
            plugin_zip = $base64Data
            slug = $PluginSlug
            activate = $ActivateAfterInstall
        } | ConvertTo-Json

        $uploadHeaders = @{
            "Authorization" = "Basic $base64Auth"
            "Content-Type" = "application/json"
        }

        $response = Invoke-RestMethod -Uri $uploadUrl -Method Post -Headers $uploadHeaders -Body $uploadBody -TimeoutSec 300
        $uploadSuccess = $true

        Write-Status "      ✓ Uploaded via $($ns.display)!" -Color Green
        Write-Status ""
        Write-Status "[4/5] Installation Complete!" -Color Yellow
        Write-Status ""
        Write-Status "========================================" -Color Green
        Write-Status "  SUCCESS! Plugin Deployed!" -Color Green
        Write-Status "========================================" -Color Green
        Write-Status ""
        Write-Status "Plugin Details:" -Color Cyan
        Write-Status "  - Plugin Slug: $($response.plugin_slug)" -Color White
        Write-Status "  - Is Update: $($response.is_update)" -Color White
        Write-Status "  - Activated: $($response.activated)" -Color White
        if ($response.activation_error) {
            Write-Status "  - Activation Error: $($response.activation_error)" -Color Yellow
        }
        Write-Status ""

        if ($Quiet) {
            $result = @{
                success = $true
                plugin = $response.plugin_slug
                activated = $response.activated
                is_update = $response.is_update
                message = "Plugin installed successfully"
            }
            Write-Output ($result | ConvertTo-Json -Compress)
        }

    } catch {
        $errMsg = $_.Exception.Message
        $errBody = Get-ErrorResponseBody $_
        Write-Status "      ✗ $($ns.display) failed: $errMsg" -Color Yellow
        Write-ErrorBody $errBody "$($ns.display) Error"
    }
}

# --- Attempt 2: Standard WordPress REST API (fallback) ---
if (-not $uploadSuccess) {
    Write-Status ""
    Write-Status "      Falling back to WordPress Core REST API..." -Color Yellow

    $fileName = Split-Path $OutputZipPath -Leaf
    $uploadUrl = "$WordPressSiteURL/wp-json/wp/v2/plugins"

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
            Write-Status "[4/5] Activating plugin..." -Color Yellow

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

        Write-Host ""
        Write-Host "All upload methods failed!" -ForegroundColor Red
        if ($statusCode) { Write-Host "Status: $statusCode" -ForegroundColor Red }
        Write-ErrorBody $errorBody "WP Core Error"
        if ($errorBody -eq "") {
            Write-Host "Error: $errorMessage" -ForegroundColor Red
        }
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
