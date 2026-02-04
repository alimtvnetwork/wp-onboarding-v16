# WordPress Plugin Uploader - Pure PowerShell
# Supports both config file and direct command-line parameters
# Uses custom Plugin Uploader Helper endpoint for reliable uploads

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
Write-Status "  Using Plugin Uploader Helper API" -Color Cyan
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

# Step 3: Check if Plugin Uploader Helper is installed
Write-Status ""
Write-Status "[3/5] Checking Plugin Uploader Helper..." -Color Yellow

$statusUrl = "$WordPressSiteURL/wp-json/plugin-uploader/v1/status"
$headers = @{
    "Authorization" = "Basic $base64Auth"
}

$helperInstalled = $false
try {
    $statusResponse = Invoke-RestMethod -Uri $statusUrl -Method Get -Headers $headers -ErrorAction Stop
    if ($statusResponse.status -eq "ok") {
        Write-Status "      Plugin Uploader Helper is active! (v$($statusResponse.version))" -Color Green
        $helperInstalled = $true
    }
} catch {
    Write-Status "      Plugin Uploader Helper not found." -Color Yellow
    Write-Status "      Attempting fallback to standard WordPress REST API..." -Color Yellow
}

# Step 4: Upload plugin
Write-Status ""
Write-Status "[4/5] Uploading plugin to WordPress..." -Color Yellow
Write-Status "      Site: $WordPressSiteURL" -Color Gray
Write-Status "      User: $Username" -Color Gray

$fileName = Split-Path $OutputZipPath -Leaf

if ($helperInstalled) {
    # Use Plugin Uploader Helper (Base64 method - most reliable)
    $uploadUrl = "$WordPressSiteURL/wp-json/plugin-uploader/v1/upload"
    
    try {
        $fileBytes = [System.IO.File]::ReadAllBytes($OutputZipPath)
        $base64Data = [Convert]::ToBase64String($fileBytes)
        
        $uploadBody = @{
            plugin_name = $fileName
            plugin_data = $base64Data
            activate = $ActivateAfterInstall
        } | ConvertTo-Json
        
        $uploadHeaders = @{
            "Authorization" = "Basic $base64Auth"
            "Content-Type" = "application/json"
        }
        
        Write-Status "      Uploading via Plugin Uploader Helper..." -Color Gray
        $response = Invoke-RestMethod -Uri $uploadUrl -Method Post -Headers $uploadHeaders -Body $uploadBody -TimeoutSec 300
        
        Write-Status "      Success! Plugin installed." -Color Green
        
        Write-Status ""
        Write-Status "[5/5] Installation Complete!" -Color Yellow
        
        Write-Status ""
        Write-Status "========================================" -Color Green
        Write-Status "  SUCCESS! Plugin Deployed!" -Color Green
        Write-Status "========================================" -Color Green
        Write-Status ""
        Write-Status "Plugin Details:" -Color Cyan
        Write-Status "  - Plugin: $($response.plugin)" -Color White
        Write-Status "  - Activated: $($response.activated)" -Color White
        if ($response.plugin_details) {
            Write-Status "  - Name: $($response.plugin_details.name)" -Color White
            Write-Status "  - Version: $($response.plugin_details.version)" -Color White
        }
        Write-Status ""
        
        # Output JSON result for programmatic parsing
        if ($Quiet) {
            $result = @{
                success = $true
                plugin = $response.plugin
                activated = $response.activated
                message = $response.message
            }
            Write-Output ($result | ConvertTo-Json -Compress)
        }
        
    } catch {
        $errorMessage = $_.Exception.Message
        
        if ($_.Exception.Response) {
            try {
                $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
                $reader.BaseStream.Position = 0
                $responseBody = $reader.ReadToEnd()
                $errorData = $responseBody | ConvertFrom-Json
                $errorMessage = $errorData.message
            } catch {}
        }
        
        Write-Host ""
        Write-Host "Upload Failed!" -ForegroundColor Red
        Write-Host "Error: $errorMessage" -ForegroundColor Red
        
        if ($Quiet) {
            $result = @{ success = $false; error = $errorMessage }
            Write-Output ($result | ConvertTo-Json -Compress)
        }
        exit 1
    }
    
} else {
    # Fallback: Standard WordPress REST API
    $uploadUrl = "$WordPressSiteURL/wp-json/wp/v2/plugins"
    
    try {
        $fileBytes = [System.IO.File]::ReadAllBytes($OutputZipPath)
        
        # Create multipart form data
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
        
        $pluginSlugResult = $response.plugin
        Write-Status "      Success! Plugin installed: $pluginSlugResult" -Color Green
        
        # Step 5: Activate plugin
        if ($ActivateAfterInstall) {
            Write-Status ""
            Write-Status "[5/5] Activating plugin..." -Color Yellow
            
            $activateUrl = "$WordPressSiteURL/wp-json/wp/v2/plugins/$pluginSlugResult"
            
            $activateBody = @{
                status = "active"
            } | ConvertTo-Json
            
            $activateHeaders = @{
                "Authorization" = "Basic $base64Auth"
                "Content-Type" = "application/json"
            }
            
            try {
                $activateResponse = Invoke-RestMethod -Uri $activateUrl -Method Post -Headers $activateHeaders -Body $activateBody
                Write-Status "      Success! Plugin activated!" -Color Green
            } catch {
                Write-Status "      Warning: Could not activate plugin automatically" -Color Yellow
                Write-Status "      Please activate manually in WordPress admin" -Color Yellow
            }
        } else {
            Write-Status ""
            Write-Status "[5/5] Skipping activation (not requested)" -Color Gray
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
        Write-Status "  - Plugin: $pluginSlugResult" -Color White
        Write-Status ""
        
        if ($Quiet) {
            $result = @{
                success = $true
                plugin = $pluginSlugResult
                activated = ($response.status -eq "active")
                message = "Plugin installed"
            }
            Write-Output ($result | ConvertTo-Json -Compress)
        }
        
    } catch {
        $statusCode = $null
        $errorMessage = $_.Exception.Message
        
        if ($_.Exception.Response) {
            $statusCode = [int]$_.Exception.Response.StatusCode
            try {
                $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
                $reader.BaseStream.Position = 0
                $responseBody = $reader.ReadToEnd()
                $errorData = $responseBody | ConvertFrom-Json
                $errorMessage = $errorData.message
            } catch {}
        }
        
        Write-Host ""
        Write-Host "Upload Failed!" -ForegroundColor Red
        Write-Host "Status: $statusCode" -ForegroundColor Red
        Write-Host "Error: $errorMessage" -ForegroundColor Red
        Write-Host ""
        Write-Host "Please install the Plugin Uploader Helper for reliable uploads:" -ForegroundColor Yellow
        Write-Host "  1. Copy 'plugin-uploader-helper' folder to wp-content/plugins/" -ForegroundColor White
        Write-Host "  2. Activate in WordPress Admin" -ForegroundColor White
        Write-Host "  3. Run this script again" -ForegroundColor White
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
