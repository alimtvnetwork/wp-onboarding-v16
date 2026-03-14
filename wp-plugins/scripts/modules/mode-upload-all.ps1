# Module: mode-upload-all.ps1
# Upload All mode: -ua (ZIP all plugins except QUpload, upload via QUpload API)
# Dot-sourced by run.ps1 — expects all helpers and plugin-helpers loaded.
# Expects: $exclude, $ScriptDir, $Config

function Invoke-UploadAllMode {
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  Upload All Mode (-ua)" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""

    $quploadScript = Join-Path $ScriptDir "wp-plugins" "scripts" "upload-plugin-U-Q.ps1"
    if (-not (Test-Path $quploadScript)) {
        Write-Host "ERROR: upload-plugin-U-Q.ps1 not found at: $quploadScript" -ForegroundColor Red
        exit 1
    }

    $qConfigPath = Join-Path $ScriptDir "wp-plugins" "scripts" "qupload-config.json"
    if (-not (Test-Path $qConfigPath)) {
        Write-Host "ERROR: qupload-config.json not found at: $qConfigPath" -ForegroundColor Red
        exit 1
    }

    $qConfigTemplate = Get-Content $qConfigPath -Raw | ConvertFrom-Json

    $wpPluginsDir = Join-Path $ScriptDir "wp-plugins"
    if (-not (Test-Path $wpPluginsDir)) {
        Write-Host "ERROR: wp-plugins/ directory not found" -ForegroundColor Red
        exit 1
    }

    $extraSkip = @()
    if ($exclude -ne "") {
        $extraSkip = @($exclude -split ',' | ForEach-Object { $_.Trim() })
        Write-Host "  User-excluded plugins: $($extraSkip -join ', ')" -ForegroundColor Yellow
    }

    $discovery = Get-UploadablePlugins -ExtraSkipList $extraSkip
    $pluginFolders = $discovery.Plugins
    $skipList = $discovery.SkipList

    if ($pluginFolders.Count -eq 0) {
        Write-Host "No plugins found to upload (QUpload excluded)" -ForegroundColor Yellow
        exit 0
    }

    Clear-PluginZips

    Write-Host "  Found $($pluginFolders.Count) plugin(s) to ZIP and upload:" -ForegroundColor Cyan
    foreach ($folder in $pluginFolders) {
        Write-Host "    - $($folder.Name)" -ForegroundColor Gray
    }
    Write-Host "  Excluded: $($skipList -join ', ')" -ForegroundColor DarkGray
    Write-Host ""

    $uploadResults = @()

    foreach ($folder in $pluginFolders) {
        $pluginName = $folder.Name
        Write-Host "----------------------------------------" -ForegroundColor DarkGray
        Write-Host "  [$($uploadResults.Count + 1)/$($pluginFolders.Count)] $pluginName" -ForegroundColor Cyan
        Write-Host "----------------------------------------" -ForegroundColor DarkGray

        Write-Host "  [ZIP] Creating archive..." -ForegroundColor Yellow
        New-PluginZip $folder.FullName

        Write-Host "  [UPLOAD] Uploading via QUpload API..." -ForegroundColor Yellow
        $qConfig = $qConfigTemplate.PSObject.Copy()
        $qConfig.pluginFolderPath = $folder.FullName
        $jsonConfigStr = ($qConfig | ConvertTo-Json -Compress)

        try {
            & $quploadScript -jc $jsonConfigStr -a
            $uploadExitCode = $LASTEXITCODE
            if ($uploadExitCode -eq 0) {
                $uploadResults += @{ Name = $pluginName; Status = "OK" }
                Write-Host "  $pluginName uploaded successfully" -ForegroundColor Green
            } else {
                $uploadResults += @{ Name = $pluginName; Status = "FAILED (exit $uploadExitCode)" }
                Write-Host "  $pluginName upload failed (exit code: $uploadExitCode)" -ForegroundColor Red
            }
        } catch {
            $uploadResults += @{ Name = $pluginName; Status = "ERROR: $_" }
            Write-Host "  $pluginName upload error: $_" -ForegroundColor Red
        }
        Write-Host ""
    }

    # Summary
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  Upload All Summary" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan
    $successCount = ($uploadResults | Where-Object { $_.Status -eq "OK" }).Count
    $failCount = $uploadResults.Count - $successCount
    foreach ($r in $uploadResults) {
        $color = if ($r.Status -eq "OK") { "Green" } else { "Red" }
        Write-Host "  $($r.Name): $($r.Status)" -ForegroundColor $color
    }
    Write-Host ""
    Write-Host "  Total: $($uploadResults.Count) | Success: $successCount | Failed: $failCount" -ForegroundColor $(if ($failCount -eq 0) { "Green" } else { "Yellow" })
    Write-Host "========================================" -ForegroundColor Cyan

    exit $(if ($failCount -eq 0) { 0 } else { 1 })
}
