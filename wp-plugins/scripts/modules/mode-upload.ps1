# Module: mode-upload.ps1
# Upload modes: -u, -q, -u -q (combo)
# Dot-sourced by run.ps1 — expects all helpers and plugin-helpers loaded.
# Expects: $pluginpath, $site, $debug, $ScriptDir, $Config

function Invoke-UploadComboMode {
    # -u -q = Upload Riseup Asia Uploader via QUpload API
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  Upload via QUpload Mode (-u -q)" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""

    $defaultUploader = $null
    if ($Config.wpPlugins -and $Config.wpPlugins.defaultUploader) {
        $defaultUploader = $Config.wpPlugins.defaultUploader
    }
    if (-not $defaultUploader -or -not $Config.wpPlugins.plugins.$defaultUploader) {
        Write-Host "ERROR: No default uploader configured in powershell.json (wpPlugins.defaultUploader)" -ForegroundColor Red
        exit 1
    }

    $pluginConfig = $Config.wpPlugins.plugins.$defaultUploader
    $riseupPath = Resolve-RelativePath $pluginConfig.path

    if (-not (Test-Path $riseupPath)) {
        Write-Host "ERROR: Plugin folder not found: $riseupPath" -ForegroundColor Red
        exit 1
    }

    Write-Host "  Plugin: $defaultUploader (via QUpload)" -ForegroundColor Yellow

    $quploadScript = Join-Path $ScriptDir "wp-plugins" "scripts" "upload-plugin-U-Q.ps1"
    if (-not (Test-Path $quploadScript)) {
        Write-Host "ERROR: upload-plugin-U-Q.ps1 not found at: $quploadScript" -ForegroundColor Red
        exit 1
    }

    $qConfigPath = Join-Path $ScriptDir "wp-plugins" "scripts" "qupload-config.json"
    if (Test-Path $qConfigPath) {
        $qConfig = Get-Content $qConfigPath -Raw | ConvertFrom-Json
        $qConfig.pluginFolderPath = $riseupPath
        $jsonConfigStr = ($qConfig | ConvertTo-Json -Compress)
        Write-Host "  Path:   $riseupPath" -ForegroundColor Gray
        Write-Host "  Site:   $($qConfig.wordPressSiteURL)" -ForegroundColor Gray
        Write-Host ""
        & $quploadScript -jc $jsonConfigStr -a
    } else {
        Write-Host "ERROR: qupload-config.json not found at: $qConfigPath" -ForegroundColor Red
        exit 1
    }

    exit 0
}

function Invoke-UploadMode {
    # -u = Upload default plugin
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  Upload Mode (-u)" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""

    if ($pluginpath -ne "") {
        $pluginPath = $pluginpath
        if (-not [System.IO.Path]::IsPathRooted($pluginPath)) {
            $pluginPath = Join-Path $ScriptDir $pluginPath
        }
        if (-not (Test-Path $pluginPath)) {
            Write-Host "ERROR: Plugin folder not found: $pluginPath" -ForegroundColor Red
            exit 1
        }
        Write-Host "  Using custom plugin path: $pluginPath" -ForegroundColor Cyan
    } else {
        $defaultUploader = $null
        if ($Config.wpPlugins -and $Config.wpPlugins.defaultUploader) {
            $defaultUploader = $Config.wpPlugins.defaultUploader
        }
        if (-not $defaultUploader -or -not $Config.wpPlugins.plugins.$defaultUploader) {
            Write-Host "ERROR: No default uploader configured in powershell.json (wpPlugins.defaultUploader)" -ForegroundColor Red
            exit 1
        }
        $pluginConfig = $Config.wpPlugins.plugins.$defaultUploader
        $pluginPath = Resolve-RelativePath $pluginConfig.path
        if (-not (Test-Path $pluginPath)) {
            Write-Host "ERROR: Plugin folder not found: $pluginPath" -ForegroundColor Red
            exit 1
        }
        Write-Host "  Plugin: $defaultUploader" -ForegroundColor Yellow
    }

    $uploadScript = Join-Path $ScriptDir "wp-plugins" "scripts" "upload-plugin-v2.ps1"
    if (-not (Test-Path $uploadScript)) {
        Write-Host "ERROR: upload-plugin-v2.ps1 not found at: $uploadScript" -ForegroundColor Red
        exit 1
    }

    if ($site -ne "") {
        if (-not $Config.wpPlugins -or -not $Config.wpPlugins.sites) {
            Write-Host "ERROR: No sites configured in powershell.json (wpPlugins.sites)" -ForegroundColor Red
            exit 1
        }

        $matchedSite = $Config.wpPlugins.sites | Where-Object { $_.name -eq $site }
        if (-not $matchedSite) {
            Write-Host "ERROR: Site '$site' not found in configuration." -ForegroundColor Red
            Write-Host "Available sites:" -ForegroundColor Yellow
            foreach ($s in $Config.wpPlugins.sites) { Write-Host "  - $($s.name)" -ForegroundColor Gray }
            exit 1
        }

        $cred = Get-DefaultSiteCredential $matchedSite
        if (-not $cred) {
            Write-Host "ERROR: No valid credentials for site '$site'" -ForegroundColor Red
            exit 1
        }

        $quploadScript = Join-Path $ScriptDir "wp-plugins" "scripts" "upload-plugin-U-Q.ps1"
        if (-not (Test-Path $quploadScript)) {
            Write-Host "ERROR: upload-plugin-U-Q.ps1 not found at: $quploadScript" -ForegroundColor Red
            exit 1
        }

        Write-Host "  Site:   $($matchedSite.name)" -ForegroundColor Yellow
        Write-Host "  URL:    $($matchedSite.url)" -ForegroundColor Gray
        Write-Host "  User:   $($cred.Username)" -ForegroundColor Gray
        Write-Host ""

        $uploadConfig = @{
            pluginFolderPath     = $pluginPath
            wordPressSiteURL     = $matchedSite.url.TrimEnd("/")
            username             = $cred.Username
            appPassword          = $cred.Password
            activateAfterInstall = $true
            deleteZipAfterUpload = $false
        }
        $jsonConfigStr = ($uploadConfig | ConvertTo-Json -Compress)
        $debugArgs = @()
        if ($debug) { $debugArgs += "-DebugMode" }
        & $quploadScript -jc $jsonConfigStr -a @debugArgs
    } else {
        $wpConfigPath = Join-Path $ScriptDir "wp-plugins" "scripts" "wp-plugin-config.json"
        if (Test-Path $wpConfigPath) {
            $wpConfig = Get-Content $wpConfigPath -Raw | ConvertFrom-Json
            $wpConfig.pluginFolderPath = $pluginPath
            $jsonConfigStr = ($wpConfig | ConvertTo-Json -Compress)
            Write-Host "  Path:   $pluginPath" -ForegroundColor Gray
            Write-Host "  Site:   $($wpConfig.wordPressSiteURL)" -ForegroundColor Gray
            Write-Host ""
            $debugArgs = @()
            if ($debug) { $debugArgs += "-DebugMode" }
            & $uploadScript -JsonConfig $jsonConfigStr -Activate @debugArgs
        } else {
            Write-Host "ERROR: wp-plugin-config.json not found at: $wpConfigPath" -ForegroundColor Red
            Write-Host "Create it with site URL, username, and app password." -ForegroundColor Yellow
            exit 1
        }
    }

    exit 0
}

function Invoke-QUploadMode {
    # -q = Upload plugin via QUpload API
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  QUpload Mode (-q)" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""

    $qPluginPath = ""
    if ($pluginpath -ne "") {
        $qPluginPath = $pluginpath
        if (-not [System.IO.Path]::IsPathRooted($qPluginPath)) {
            $qPluginPath = Join-Path $ScriptDir $qPluginPath
        }
        if (-not (Test-Path $qPluginPath)) {
            Write-Host "ERROR: Plugin folder not found: $qPluginPath" -ForegroundColor Red
            exit 1
        }
        Write-Host "  Using custom plugin path: $qPluginPath" -ForegroundColor Cyan
    } else {
        $defaultQUploader = $null
        if ($Config.wpPlugins -and $Config.wpPlugins.defaultQUploader) {
            $defaultQUploader = $Config.wpPlugins.defaultQUploader
        }
        if (-not $defaultQUploader -and $Config.wpPlugins -and $Config.wpPlugins.defaultUploader) {
            $defaultQUploader = $Config.wpPlugins.defaultUploader
        }
        if (-not $defaultQUploader -or -not $Config.wpPlugins.plugins.$defaultQUploader) {
            Write-Host "ERROR: No default QUploader configured in powershell.json (wpPlugins.defaultQUploader)" -ForegroundColor Red
            exit 1
        }
        $pluginCfg = $Config.wpPlugins.plugins.$defaultQUploader
        $qPluginPath = Resolve-RelativePath $pluginCfg.path
        if (-not (Test-Path $qPluginPath)) {
            Write-Host "ERROR: Plugin folder not found: $qPluginPath" -ForegroundColor Red
            exit 1
        }
        Write-Host "  Plugin: $defaultQUploader" -ForegroundColor Yellow
    }

    $quploadScript = Join-Path $ScriptDir "wp-plugins" "scripts" "upload-plugin-U-Q.ps1"
    if (-not (Test-Path $quploadScript)) {
        Write-Host "ERROR: upload-plugin-U-Q.ps1 not found at: $quploadScript" -ForegroundColor Red
        exit 1
    }

    $qConfigPath = Join-Path $ScriptDir "wp-plugins" "scripts" "qupload-config.json"
    if (Test-Path $qConfigPath) {
        $qConfig = Get-Content $qConfigPath -Raw | ConvertFrom-Json
        $qConfig.pluginFolderPath = $qPluginPath
        $jsonConfigStr = ($qConfig | ConvertTo-Json -Compress)
        Write-Host "  Path:   $qPluginPath" -ForegroundColor Gray
        Write-Host "  Site:   $($qConfig.wordPressSiteURL)" -ForegroundColor Gray
        Write-Host ""
        & $quploadScript -jc $jsonConfigStr -a
    } else {
        Write-Host "ERROR: qupload-config.json not found at: $qConfigPath" -ForegroundColor Red
        Write-Host "Create it with pluginFolderPath, wordPressSiteURL, username, and appPassword." -ForegroundColor Yellow
        exit 1
    }

    exit 0
}
