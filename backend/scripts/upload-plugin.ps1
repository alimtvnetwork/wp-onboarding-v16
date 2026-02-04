# WordPress Plugin Uploader - Pure PowerShell
# Reads configuration from JSON file
# Uses custom Plugin Uploader Helper endpoint for reliable uploads

param(
    [Parameter(Mandatory=$false)]
    [string]$ConfigPath = ""
)

# Find config file
if ($ConfigPath -eq "") {
    $scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
    $ConfigPath = Join-Path $scriptDir "wp-plugin-config.json"
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  WordPress Plugin Uploader" -ForegroundColor Cyan
Write-Host "  Using Plugin Uploader Helper API" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Load configuration from JSON
if (-not (Test-Path $ConfigPath)) {
    Write-Host "Error: Config file not found at: $ConfigPath" -ForegroundColor Red
    Write-Host "Please create wp-plugin-config.json with the following structure:" -ForegroundColor Yellow
    Write-Host @"
{
  "pluginFolderPath": "C:\\path\\to\\your\\plugin",
  "wordPressSiteURL": "https://your-site.com",
  "username": "admin",
  "appPassword": "your-application-password",
  "outputZipPath": "",
  "activateAfterInstall": true,
  "deleteZipAfterUpload": false
}
"@ -ForegroundColor Gray
    exit 1
}

Write-Host "Loading config from: $ConfigPath" -ForegroundColor Gray
$config = Get-Content $ConfigPath -Raw | ConvertFrom-Json

$PluginFolderPath = $config.pluginFolderPath
$WordPressSiteURL = $config.wordPressSiteURL.TrimEnd('/')
$Username = $config.username
$AppPassword = $config.appPassword
$OutputZipPath = $config.outputZipPath
$ActivateAfterInstall = $config.activateAfterInstall
$DeleteZipAfterUpload = $config.deleteZipAfterUpload

# Step 1: Verify plugin folder exists
if (-not (Test-Path $PluginFolderPath)) {
    Write-Host "Error: Plugin folder not found at: $PluginFolderPath" -ForegroundColor Red
    exit 1
}

$folderName = Split-Path $PluginFolderPath -Leaf
Write-Host "[1/5] Plugin Folder: $folderName" -ForegroundColor Yellow
Write-Host "      Path: $PluginFolderPath" -ForegroundColor Gray

# Step 2: Create ZIP file
if ($OutputZipPath -eq "") {
    $OutputZipPath = Join-Path $PWD.Path "$folderName.zip"
}

# Remove existing ZIP if it exists
if (Test-Path $OutputZipPath) {
    Write-Host "      Removing existing ZIP file..." -ForegroundColor Gray
    Remove-Item $OutputZipPath -Force
}

Write-Host ""
Write-Host "[2/5] Creating ZIP file..." -ForegroundColor Yellow

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
    Write-Host "      Success! ZIP created: $OutputZipPath" -ForegroundColor Green
    Write-Host "      Size: $zipSizeKB KB" -ForegroundColor Gray
} catch {
    Write-Host "      Error creating ZIP file: $_" -ForegroundColor Red
    exit 1
}

# Create Basic Auth header
$CleanAppPassword = $AppPassword -replace '\s', ''
$base64Auth = [Convert]::ToBase64String([Text.Encoding]::ASCII.GetBytes("${Username}:${CleanAppPassword}"))

# Step 3: Check if Plugin Uploader Helper is installed
Write-Host ""
Write-Host "[3/5] Checking Plugin Uploader Helper..." -ForegroundColor Yellow

$statusUrl = "$WordPressSiteURL/wp-json/plugin-uploader/v1/status"
$headers = @{
    "Authorization" = "Basic $base64Auth"
}

$helperInstalled = $false
try {
    $statusResponse = Invoke-RestMethod -Uri $statusUrl -Method Get -Headers $headers -ErrorAction Stop
    if ($statusResponse.status -eq "ok") {
        Write-Host "      Plugin Uploader Helper is active!" -ForegroundColor Green
        $helperInstalled = $true
    }
} catch {
    Write-Host "      Plugin Uploader Helper not found." -ForegroundColor Yellow
    Write-Host "      Please install 'plugin-uploader-helper' plugin first!" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "Installation Steps:" -ForegroundColor Cyan
    Write-Host "  1. Copy the 'plugin-uploader-helper' folder to your WordPress plugins directory" -ForegroundColor White
    Write-Host "  2. Activate it in WordPress Admin > Plugins" -ForegroundColor White
    Write-Host "  3. Run this script again" -ForegroundColor White
    Write-Host ""
    
    # Fallback to standard API
    Write-Host "Attempting fallback to standard WordPress REST API..." -ForegroundColor Yellow
}

# Step 4: Upload plugin
Write-Host ""
Write-Host "[4/5] Uploading plugin to WordPress..." -ForegroundColor Yellow
Write-Host "      Site: $WordPressSiteURL" -ForegroundColor Gray
Write-Host "      User: $Username" -ForegroundColor Gray

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
        
        Write-Host "      Uploading via Plugin Uploader Helper..." -ForegroundColor Gray
        $response = Invoke-RestMethod -Uri $uploadUrl -Method Post -Headers $uploadHeaders -Body $uploadBody -TimeoutSec 300
        
        Write-Host "      Success! Plugin installed." -ForegroundColor Green
        
        Write-Host ""
        Write-Host "[5/5] Installation Complete!" -ForegroundColor Yellow
        
        Write-Host ""
        Write-Host "========================================" -ForegroundColor Green
        Write-Host "  SUCCESS! Plugin Deployed!" -ForegroundColor Green
        Write-Host "========================================" -ForegroundColor Green
        Write-Host ""
        Write-Host "Plugin Details:" -ForegroundColor Cyan
        Write-Host "  - Plugin: $($response.plugin)" -ForegroundColor White
        Write-Host "  - Activated: $($response.activated)" -ForegroundColor White
        if ($response.plugin_details) {
            Write-Host "  - Name: $($response.plugin_details.name)" -ForegroundColor White
            Write-Host "  - Version: $($response.plugin_details.version)" -ForegroundColor White
        }
        Write-Host ""
        
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
        
        $pluginSlug = $response.plugin
        Write-Host "      Success! Plugin installed: $pluginSlug" -ForegroundColor Green
        
        # Step 5: Activate plugin
        if ($ActivateAfterInstall) {
            Write-Host ""
            Write-Host "[5/5] Activating plugin..." -ForegroundColor Yellow
            
            $activateUrl = "$WordPressSiteURL/wp-json/wp/v2/plugins/$pluginSlug"
            
            $activateBody = @{
                status = "active"
            } | ConvertTo-Json
            
            $activateHeaders = @{
                "Authorization" = "Basic $base64Auth"
                "Content-Type" = "application/json"
            }
            
            try {
                $activateResponse = Invoke-RestMethod -Uri $activateUrl -Method Post -Headers $activateHeaders -Body $activateBody
                Write-Host "      Success! Plugin activated!" -ForegroundColor Green
            } catch {
                Write-Host "      Warning: Could not activate plugin automatically" -ForegroundColor Yellow
                Write-Host "      Please activate manually in WordPress admin" -ForegroundColor Yellow
            }
        } else {
            Write-Host ""
            Write-Host "[5/5] Skipping activation (disabled in config)" -ForegroundColor Gray
        }
        
        Write-Host ""
        Write-Host "========================================" -ForegroundColor Green
        Write-Host "  SUCCESS! Plugin Deployed!" -ForegroundColor Green
        Write-Host "========================================" -ForegroundColor Green
        Write-Host ""
        Write-Host "Plugin Details:" -ForegroundColor Cyan
        Write-Host "  - Name: $($response.name)" -ForegroundColor White
        Write-Host "  - Version: $($response.version)" -ForegroundColor White
        Write-Host "  - Status: $($response.status)" -ForegroundColor White
        Write-Host "  - Plugin: $pluginSlug" -ForegroundColor White
        Write-Host ""
        
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
        exit 1
    }
}

# Cleanup ZIP if configured
if ($DeleteZipAfterUpload -and (Test-Path $OutputZipPath)) {
    Remove-Item $OutputZipPath -Force
    Write-Host "ZIP file deleted as per config" -ForegroundColor Gray
}

Write-Host "Done!" -ForegroundColor Cyan
Write-Host ""
